<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\TresorerieJournal;
use App\Modules\Admin\Modeles\OrdreProduction;
use App\Modules\Admin\Modeles\TransfertStock;
use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\TaxConfiguration;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Carbon\Carbon;

/**
 * Module <GESTION FNE> — 3 pages informatives (aucun traitement, uniquement
 * de la consultation/analyse) :
 *   1. Gestion FNE       : KPI restreints aux documents NORMALISÉS DGI
 *      (miroir de la plateforme FNE elle-même).
 *   2. Situation Générale : KPI sur TOUTE l'activité réelle de l'entreprise
 *      (normalisée + non normalisée), + mouvements de stock.
 *   3. Factures & Reçus   : registre complet, consultable/téléchargeable,
 *      TOUTES les factures (normalisées ou non — voir correctif demandé).
 *
 * Conventions retenues faute de concept "Reçu" natif dans Selflow (voir
 * commentaires inline) :
 *   - Une vente sans client identifié (client_id null, "client de passage")
 *     est traitée comme un "Reçu" plutôt qu'une "Facture".
 *   - Côté achat, un BAPA (type_facture='bapa') est traité comme un document
 *     "émis" par Selflow (l'entreprise établit elle-même le document faute
 *     de facture fournisseur), les factures normales reçues comme "reçues".
 *
 * Indicateurs 100% propres à la plateforme DGI (solde stickers, timbres —
 * données qu'on ne peut obtenir que via un futur appel API dédié, non
 * disponible actuellement) : affichés à 0, comme dans la maquette validée.
 */
class FneDashboardControleur
{
    // ─────────────────────────────────────────────────────────────────
    // PAGE 1 — GESTION FNE (documents normalisés uniquement)
    // ─────────────────────────────────────────────────────────────────

    public function gestion(Request $request): View
    {
        $entreprise    = Auth::user()->entreprise;
        $pointsDeVente = PointDeVente::where('entreprise_id', $entreprise->id)->orderBy('nom')->get();
        $taxConfig     = TaxConfiguration::where('entreprise_id', $entreprise->id)->first();

        return view('admin::fne.gestion', [
            'entreprise'    => $entreprise,
            'pointsDeVente' => $pointsDeVente,
            'taxConfig'     => $taxConfig,
            'kpis'          => $this->calculerKpisGestionFne($request, $entreprise->id),
        ]);
    }

    public function gestionJson(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        return response()->json($this->calculerKpisGestionFne($request, $entreprise->id));
    }

    private function calculerKpisGestionFne(Request $request, int $entrepriseId): array
    {
        $ent = \App\Modules\Admin\Modeles\Entreprise::find($entrepriseId);
        $taxConfig = TaxConfiguration::where('entreprise_id', $entrepriseId)->first();

        // 1. Calculer les stickers consommés (quantité) à vie (toutes périodes confondues)
        $totalVentesNormalisees = Vente::whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entrepriseId))
            ->where('etape', 'Facture')
            ->where('normalise', true)
            ->count();

        $totalAchatsNormalises = Achat::whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entrepriseId))
            ->where('type_facture', 'bapa')
            ->where('etape', 'Facture')
            ->where('normalise', true)
            ->count();

        $stickers_consommes = $totalVentesNormalisees + $totalAchatsNormalises;

        // 2. Solde des stickers (quantité) stocké en base
        $stickers_solde = intval($ent?->fne_sticker_balance ?? 0);

        // 3. Stickers achetés (quantité) = Solde + Consommés
        $stickers_achats = $stickers_solde + $stickers_consommes;

        [$debut, $fin] = $this->resoudrePeriode($request);
        $pdvId = $request->input('pdv_id');

        $ventesQuery = Vente::whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entrepriseId))
            ->whereBetween('date_vente', [$debut, $fin])
            ->where('etape', 'Facture')
            ->where('normalise', true);
        if ($pdvId && $pdvId !== 'tous') $ventesQuery->where('point_de_vente_id', $pdvId);

        $achatsQuery = Achat::whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entrepriseId))
            ->whereBetween('date_achat', [$debut, $fin])
            ->where('etape', 'Facture')
            ->where('normalise', true);
        if ($pdvId && $pdvId !== 'tous') $achatsQuery->where('point_de_vente_id', $pdvId);

        // 4. Calculer le Timbre de quittance pour la période sélectionnée
        $timbre_montant = 0;
        $timbre_quantite = 0;
        if ($taxConfig && $taxConfig->tdt_active) {
            $ventesEspeces = (clone $ventesQuery)
                ->where('mode_paiement', 'Caisse')
                ->where('montant_ttc', '>', (float) $taxConfig->tdt_seuil)
                ->get();
            
            $timbre_quantite = $ventesEspeces->count();
            $timbre_montant = $ventesEspeces->sum(function($v) use ($taxConfig) {
                return round($v->montant_ttc * ($taxConfig->tdt_taux / 100), 2);
            });
        }

        // Ventes normalisées, hors avoirs / reçus (facture + client identifié)
        $facturesVente = (clone $ventesQuery)->where(function($q) { $q->whereNull('type_facture')->orWhere('type_facture', '!=', 'avoir'); })
            ->whereNotNull('client_id');
        $recusVente = (clone $ventesQuery)->where(function($q) { $q->whereNull('type_facture')->orWhere('type_facture', '!=', 'avoir'); })
            ->whereNull('client_id');
        $avoirsVente = (clone $ventesQuery)->where('type_facture', 'avoir');

        $facturesAchat = (clone $achatsQuery)->where(function($q) { $q->whereNull('type_facture')->orWhereNotIn('type_facture', ['avoir', 'bapa']); });
        $avoirsAchat = (clone $achatsQuery)->where('type_facture', 'avoir');

        $totalHtVente = (clone $ventesQuery)->where('type_facture', '!=', 'avoir')->sum('montant_ht');
        $totalTvaVente = (clone $ventesQuery)->where('type_facture', '!=', 'avoir')->sum('montant_tva');
        $totalTtcVente = (clone $ventesQuery)->where('type_facture', '!=', 'avoir')->sum('montant_ttc');
        $totalRemiseVente = (clone $ventesQuery)->where('type_facture', '!=', 'avoir')->sum('remise');

        return [
            'periode' => ['debut' => $debut->toDateString(), 'fin' => $fin->toDateString()],

            // Indicateurs propres à la plateforme DGI
            'stickers_solde'    => $stickers_solde,
            'stickers_achats'   => $stickers_achats,
            'stickers_consommes'=> $stickers_consommes,
            'timbre_quittance'  => $timbre_montant,
            'timbre_quantite'   => $timbre_quantite,

            'ventes' => [
                'factures' => ['nombre' => (clone $facturesVente)->count(), 'montant' => (clone $facturesVente)->sum('montant_ttc')],
                'avoirs'   => ['nombre' => (clone $avoirsVente)->count(),   'montant' => (clone $avoirsVente)->sum('montant_ttc')],
                'recus'    => ['nombre' => (clone $recusVente)->count(),     'montant' => (clone $recusVente)->sum('montant_ttc')],
                'proforma' => ['nombre' => 0, 'montant' => 0],
                'total_ht'      => $totalHtVente,
                'total_tva'     => $totalTvaVente,
                'total_ttc'     => $totalTtcVente,
                'total_remises' => $totalRemiseVente,
            ],

            'achats' => [
                'factures'           => ['nombre' => (clone $facturesAchat)->count(), 'montant' => (clone $facturesAchat)->sum('montant_ttc')],
                'avoirs'             => ['nombre' => (clone $avoirsAchat)->count(),   'montant' => (clone $avoirsAchat)->sum('montant_ttc')],
                'total_ht'           => (clone $achatsQuery)->where('type_facture', '!=', 'avoir')->sum('montant_ht'),
                'total_tva_deductible'=> (clone $achatsQuery)->where('type_facture', '!=', 'avoir')->sum('montant_tva'),
                'total_ttc'          => (clone $achatsQuery)->where('type_facture', '!=', 'avoir')->sum('montant_ttc'),
                'ca_ht'              => (clone $achatsQuery)->where('type_facture', '!=', 'avoir')->sum('montant_ht'),
                'ca_ttc'             => (clone $achatsQuery)->where('type_facture', '!=', 'avoir')->sum('montant_ttc'),
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // CONFIGURATION FISCALE (TSE / TDT / TVA)
    // ─────────────────────────────────────────────────────────────────

    /**
     * Retourner la configuration fiscale courante en JSON.
     */
    public function obtenirConfig(): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $config     = TaxConfiguration::where('entreprise_id', $entreprise->id)->first();

        $regime    = $config?->regime     ?? $entreprise->regime_imposition;
        $categorie = TaxConfiguration::categorieDepuisRegime($regime);

        return response()->json([
            'regime'     => $regime,
            'categorie'  => $categorie,
            'label_categorie' => $categorie === 'CAS_A' ? 'CAS A — Assujetti TVA (RNI / RSI)' : 'CAS B — Non-assujetti (TEE / RNE)',
            'tva_active' => $config?->tva_active ?? false,
            'tva_taux'   => $config?->tva_taux   ?? 18.00,
            'tse_active' => $config?->tse_active ?? false,
            'tse_taux'   => $config?->tse_taux   ?? 0.10,
            'tdt_active' => $config?->tdt_active ?? false,
            'tdt_taux'   => $config?->tdt_taux   ?? 1.50,
            'tdt_seuil'  => $config?->tdt_seuil  ?? 5000.00,
        ]);
    }

    /**
     * Sauvegarder la configuration fiscale (POST AJAX).
     */
    public function sauvegarderConfig(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;

        $validated = $request->validate([
            'tva_active' => ['boolean'],
            'tva_taux'   => ['numeric', 'min:0', 'max:100'],
            'tse_active' => ['boolean'],
            'tse_taux'   => ['numeric', 'min:0', 'max:100'],
            'tdt_active' => ['boolean'],
            'tdt_taux'   => ['numeric', 'min:0', 'max:100'],
            'tdt_seuil'  => ['numeric', 'min:0'],
        ]);

        $regime    = $entreprise->regime_imposition;
        $categorie = TaxConfiguration::categorieDepuisRegime($regime);

        // Règle métier : TSE interdite en CAS B (TEE / RNE)
        if ($categorie === 'CAS_B') {
            $validated['tse_active'] = false;
        }

        // Règle métier : TVA masquée en CAS B
        if ($categorie === 'CAS_B') {
            $validated['tva_active'] = false;
        }

        $config = TaxConfiguration::updateOrCreate(
            ['entreprise_id' => $entreprise->id],
            array_merge($validated, [
                'regime'    => $regime,
                'categorie' => $categorie,
            ])
        );

        return response()->json([
            'success'   => true,
            'message'   => 'Configuration fiscale enregistrée avec succès.',
            'categorie' => $categorie,
            'config'    => [
                'tva_active' => $config->tva_active,
                'tse_active' => $config->tse_active,
                'tdt_active' => $config->tdt_active,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // PAGE 2 — SITUATION GÉNÉRALE (toute l'activité réelle)
    // ─────────────────────────────────────────────────────────────────

    public function situation(Request $request): View
    {
        $entreprise = Auth::user()->entreprise;
        $pointsDeVente = PointDeVente::where('entreprise_id', $entreprise->id)->orderBy('nom')->get();

        return view('admin::fne.situation', [
            'entreprise' => $entreprise,
            'pointsDeVente' => $pointsDeVente,
            'kpis' => $this->calculerKpisSituation($request, $entreprise->id),
        ]);
    }

    public function situationJson(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        return response()->json($this->calculerKpisSituation($request, $entreprise->id));
    }

    private function calculerKpisSituation(Request $request, int $entrepriseId): array
    {
        [$debut, $fin] = $this->resoudrePeriode($request);
        $pdvId = $request->input('pdv_id');

        $ventesQuery = Vente::whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entrepriseId))
            ->whereBetween('date_vente', [$debut, $fin])
            ->where('etape', 'Facture');
        if ($pdvId && $pdvId !== 'tous') $ventesQuery->where('point_de_vente_id', $pdvId);

        $achatsQuery = Achat::whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entrepriseId))
            ->whereBetween('date_achat', [$debut, $fin])
            ->where('etape', 'Facture');
        if ($pdvId && $pdvId !== 'tous') $achatsQuery->where('point_de_vente_id', $pdvId);

        $caReel = (clone $ventesQuery)->where('type_facture', '!=', 'avoir')->sum('montant_ttc')
                - (clone $ventesQuery)->where('type_facture', 'avoir')->sum('montant_ttc');
        $achatsReel = (clone $achatsQuery)->where('type_facture', '!=', 'avoir')->sum('montant_ttc')
                    - (clone $achatsQuery)->where('type_facture', 'avoir')->sum('montant_ttc');

        // Trésorerie nette encaissée sur la période (entrées - sorties réellement mouvementées)
        $tresoQuery = TresorerieJournal::whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entrepriseId))
            ->whereBetween('date_operation', [$debut, $fin]);
        if ($pdvId && $pdvId !== 'tous') $tresoQuery->where('point_de_vente_id', $pdvId);
        $entrees = (clone $tresoQuery)->sum('montant_entree');
        $sorties = (clone $tresoQuery)->sum('montant_sortie');

        $ventesNormalisees = (clone $ventesQuery)->where('normalise', true)->where('type_facture', '!=', 'avoir')->sum('montant_ttc');
        $tauxConformite = $caReel > 0 ? round(($ventesNormalisees / $caReel) * 100, 1) : 0;

        // Déclaré vs non déclaré (vente)
        $venteNormaliseesCount = (clone $ventesQuery)->where('normalise', true)->where('type_facture', '!=', 'avoir')->count();
        $venteNonNormaliseesCount = (clone $ventesQuery)->where('normalise', false)->where('type_facture', '!=', 'avoir')->count();
        $venteNonNormaliseesMontant = (clone $ventesQuery)->where('normalise', false)->where('type_facture', '!=', 'avoir')->sum('montant_ttc');

        // Déclaré vs non déclaré (achat)
        $achatNormaliseesCount = (clone $achatsQuery)->where('normalise', true)->where('type_facture', '!=', 'avoir')->count();
        $achatNonNormaliseesCount = (clone $achatsQuery)->where('normalise', false)->where('type_facture', '!=', 'avoir')->count();
        $achatNonNormaliseesMontant = (clone $achatsQuery)->where('normalise', false)->where('type_facture', '!=', 'avoir')->sum('montant_ttc');

        // Stock : ordres de production (comptés + valorisés via les écritures de production)
        $ordresProduction = OrdreProduction::whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entrepriseId))
            ->whereBetween('date_production', [$debut, $fin]);
        if ($pdvId && $pdvId !== 'tous') $ordresProduction->where('point_de_vente_id', $pdvId);
        $nbOrdresProduction = (clone $ordresProduction)->count();

        $valeurProduite = EcritureComptable::whereHas('operation', function ($q) use ($entrepriseId, $debut, $fin) {
                $q->where('entreprise_id', $entrepriseId)
                  ->where('type_operation', 'Production')
                  ->whereBetween('date_operation', [$debut, $fin]);
            })
            ->where('compte_debit', '351100')
            ->sum('debit');

        $transfertsQuery = TransfertStock::whereHas('produit', fn($q) => $q->where('entreprise_id', $entrepriseId))
            ->where('statut', 'Validé')
            ->whereBetween('approuve_le', [$debut, $fin]);
        $nbTransferts = (clone $transfertsQuery)->count();

        return [
            'periode' => ['debut' => $debut->toDateString(), 'fin' => $fin->toDateString()],

            'ca_reel' => $caReel,
            'achats_reel' => $achatsReel,
            'tresorerie_nette' => $entrees - $sorties,
            'tresorerie_entrees' => $entrees,
            'tresorerie_sorties' => $sorties,
            'taux_conformite_fne' => $tauxConformite,

            'declaration' => [
                'ventes' => [
                    'normalisees' => ['nombre' => $venteNormaliseesCount, 'montant' => $ventesNormalisees],
                    'non_normalisees' => ['nombre' => $venteNonNormaliseesCount, 'montant' => $venteNonNormaliseesMontant],
                    'total' => ['nombre' => $venteNormaliseesCount + $venteNonNormaliseesCount, 'montant' => $caReel],
                ],
                'achats' => [
                    'normalises' => ['nombre' => $achatNormaliseesCount, 'montant' => (clone $achatsQuery)->where('normalise', true)->where('type_facture', '!=', 'avoir')->sum('montant_ttc')],
                    'non_normalises' => ['nombre' => $achatNonNormaliseesCount, 'montant' => $achatNonNormaliseesMontant],
                    'total' => ['nombre' => $achatNormaliseesCount + $achatNonNormaliseesCount, 'montant' => $achatsReel],
                ],
            ],

            'stock' => [
                'nb_ordres_production' => $nbOrdresProduction,
                'valeur_produite' => (float) $valeurProduite,
                'nb_transferts' => $nbTransferts,
                'pertes' => 0, // Non tracké dans Selflow actuellement (aucun type de mouvement "Perte" dédié)
            ],
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // PAGE 3 — FACTURES & REÇUS (registre complet, normalisé ou non)
    // ─────────────────────────────────────────────────────────────────

    public function factures(Request $request): View
    {
        $entreprise = Auth::user()->entreprise;
        $pointsDeVente = PointDeVente::where('entreprise_id', $entreprise->id)->orderBy('nom')->get();

        return view('admin::fne.factures', [
            'entreprise' => $entreprise,
            'pointsDeVente' => $pointsDeVente,
        ]);
    }

    /**
     * Endpoint JSON paginé/filtré consommé par la page Factures & Reçus
     * (rendu instantané côté client, sans recharger la page).
     *
     * Paramètres attendus : flux (ventes|achats), categorie, periode_type,
     * date, pdv_id, recherche, page.
     */
    public function facturesJson(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        [$debut, $fin] = $this->resoudrePeriode($request);
        $flux = $request->input('flux', 'ventes');
        $categorie = $request->input('categorie', 'emis');
        $pdvId = $request->input('pdv_id');
        $recherche = trim((string) $request->input('recherche', ''));
        $parPage = 20;

        if ($flux === 'ventes') {
            $query = Vente::with(['client', 'pointDeVente', 'parent'])
                ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
                ->whereBetween('date_vente', [$debut, $fin])
                ->where('etape', 'Facture');

            $query = match ($categorie) {
                'avoir_client' => $query->where('type_facture', 'avoir'),
                'recu_recu'    => $query->where(function($q) { $q->whereNull('type_facture')->orWhere('type_facture', '!=', 'avoir'); })->whereNull('client_id'),
                default        => $query->where(function($q) { $q->whereNull('type_facture')->orWhere('type_facture', '!=', 'avoir'); })->whereNotNull('client_id'),
            };

            if ($pdvId && $pdvId !== 'tous') $query->where('point_de_vente_id', $pdvId);
            if ($recherche !== '') {
                $query->where(function ($q) use ($recherche) {
                    $q->where('numero_facture', 'like', "%{$recherche}%")
                      ->orWhere('numero_fne', 'like', "%{$recherche}%")
                      ->orWhereHas('client', fn($qc) => $qc->where('nom', 'like', "%{$recherche}%"));
                });
            }

            $documents = $query->orderByDesc('date_vente')->orderByDesc('id')->paginate($parPage);

            $lignes = $documents->getCollection()->map(function (Vente $v) {
                $dgiUrl = $v->fichier_fne_pdf_url;

                return [
                    'id' => $v->id,
                    'type_doc' => $v->type_facture === 'avoir' ? 'Facture Avoir' : ($v->client_id ? 'Facture' : 'Reçu'),
                    'is_recu' => !$v->client_id,
                    'num_piece' => $v->numero_facture,
                    'num_fne' => $v->numero_fne,
                    'tiers' => $v->client?->nom ?? 'Client de passage',
                    'ht' => (float) $v->montant_ht,
                    'tva' => (float) $v->montant_tva,
                    'ttc' => (float) $v->montant_ttc,
                    'normalise' => (bool) $v->normalise,
                    'date' => $v->date_vente?->toDateString(),
                    'pdv' => $v->pointDeVente?->nom,
                    'facture_origine' => $v->type_facture === 'avoir' ? $v->parent?->numero_facture : null,
                    'voir_url' => $dgiUrl,
                    'telechargement_url' => $dgiUrl,
                    'local_url' => route('admin.ventes.imprimer', $v->id),
                    'normaliser_url' => route('admin.ventes.normaliser', $v->id),
                ];
            });
        } else {
            $query = Achat::with(['fournisseur', 'pointDeVente', 'parent'])
                ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
                ->whereBetween('date_achat', [$debut, $fin])
                ->where('etape', 'Facture');

            $query = match ($categorie) {
                'avoir_fournisseur' => $query->where('type_facture', 'avoir'),
                'emis'              => $query->where('type_facture', 'bapa'),
                default             => $query->where(function($q) { $q->whereNull('type_facture')->orWhereNotIn('type_facture', ['avoir', 'bapa']); }),
            };

            if ($pdvId && $pdvId !== 'tous') $query->where('point_de_vente_id', $pdvId);
            if ($recherche !== '') {
                $query->where(function ($q) use ($recherche) {
                    $q->where('numero_facture', 'like', "%{$recherche}%")
                      ->orWhere('numero_fne', 'like', "%{$recherche}%")
                      ->orWhereHas('fournisseur', fn($qf) => $qf->where('nom', 'like', "%{$recherche}%"));
                });
            }

            $documents = $query->orderByDesc('date_achat')->orderByDesc('id')->paginate($parPage);

            $lignes = $documents->getCollection()->map(function (Achat $a) {
                $dgiUrl = $a->fichier_fne_pdf_url;

                return [
                    'id' => $a->id,
                    'type_doc' => $a->type_facture === 'avoir' ? 'Facture Avoir' : ($a->type_facture === 'bapa' ? 'BAPA' : 'Facture'),
                    'is_recu' => false,
                    'num_piece' => $a->numero_facture,
                    'num_fne' => $a->numero_fne,
                    'tiers' => $a->fournisseur?->nom ?? '—',
                    'ht' => (float) $a->montant_ht,
                    'tva' => (float) $a->montant_tva,
                    'ttc' => (float) $a->montant_ttc,
                    'normalise' => (bool) $a->normalise,
                    'date' => $a->date_achat?->toDateString(),
                    'pdv' => $a->pointDeVente?->nom,
                    'facture_origine' => $a->type_facture === 'avoir' ? $a->parent?->numero_facture : null,
                    'voir_url' => $dgiUrl,
                    'telechargement_url' => $dgiUrl,
                    'local_url' => route('admin.achats.imprimer', $a->id),
                    'normaliser_url' => route('admin.achats.normaliser', $a->id),
                ];
            });
        }

        return response()->json([
            'documents' => $lignes,
            'pagination' => [
                'page_courante' => $documents->currentPage(),
                'derniere_page' => $documents->lastPage(),
                'total' => $documents->total(),
                'de' => $documents->firstItem() ?? 0,
                'a' => $documents->lastItem() ?? 0,
            ],
            'totaux' => [
                'ht' => $lignes->sum('ht'),
                'ttc' => $lignes->sum('ttc'),
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────
    // HELPER PARTAGÉ — résolution de la période sélectionnée par les filtres
    // ─────────────────────────────────────────────────────────────────

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resoudrePeriode(Request $request): array
    {
        $type = $request->input('periode_type', 'mois');
        $date = $request->input('date', now()->toDateString());

        try {
            $carbon = Carbon::parse($date);
        } catch (\Throwable $e) {
            $carbon = now();
        }

        return match ($type) {
            'jour'   => [$carbon->copy()->startOfDay(), $carbon->copy()->endOfDay()],
            'semaine'=> [$carbon->copy()->startOfWeek(), $carbon->copy()->endOfWeek()],
            'annee'  => [$carbon->copy()->startOfYear(), $carbon->copy()->endOfYear()],
            default  => [$carbon->copy()->startOfMonth(), $carbon->copy()->endOfMonth()],
        };
    }

    // ─────────────────────────────────────────────────────────────────
    // TRAITEMENTS PAR LOT & PLANIFICATION
    // ─────────────────────────────────────────────────────────────────

    public function batchNormaliser(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $ids = $request->input('ids', []);
        $flux = $request->input('flux', 'ventes');

        if (empty($ids) || !is_array($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune facture sélectionnée.'
            ], 400);
        }

        if (count($ids) > 15) {
            return response()->json([
                'success' => false,
                'message' => 'La sélection ne peut pas dépasser 15 factures.'
            ], 400);
        }

        $successCount = 0;
        $errors = [];

        foreach ($ids as $id) {
            try {
                if ($flux === 'ventes') {
                    $vente = Vente::whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
                        ->find($id);

                    if (!$vente) {
                        $errors[] = "Vente #{$id} introuvable.";
                        break;
                    }

                    if ($vente->normalise) {
                        $successCount++;
                        continue;
                    }

                    $estRne = ($vente->type_facture === 'RNE');
                    $fneResult = \App\Modules\Admin\Services\FneService::normaliserFacture($vente, $estRne);

                    if ($fneResult['success']) {
                        $updateData = [
                            'normalise'     => true,
                            'numero_fne'    => $fneResult['numero_recu'],
                            'signature_dgi' => $fneResult['signature'] ?? null,
                            'qr_code_data'  => $fneResult['qr_code_data'],
                            'fichier_fne_pdf_url' => $fneResult['pdf_url'] ?? null,
                        ];

                        if ($estRne) {
                            $updateData['type_facture'] = 'RNE';
                        } elseif ($vente->type_facture !== 'avoir') {
                            $updateData['type_facture'] = 'normale';
                        }

                        if (!empty($fneResult['invoice_id'])) {
                            $updateData['fne_invoice_id'] = $fneResult['invoice_id'];
                        }

                        $vente->update($updateData);

                        if (!empty($fneResult['fne_item_ids']) && is_array($fneResult['fne_item_ids'])) {
                            foreach ($fneResult['fne_item_ids'] as $detailId => $itemId) {
                                \App\Modules\Admin\Modeles\VenteDetail::where('id', $detailId)->update(['fne_invoice_item_id' => $itemId]);
                            }
                        }

                        $successCount++;
                    } else {
                        $errors[] = "Erreur sur la facture {$vente->numero_facture} : " . ($fneResult['message'] ?? 'Erreur inconnue');
                        break;
                    }
                } else {
                    $achat = Achat::whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
                        ->find($id);

                    if (!$achat) {
                        $errors[] = "Achat #{$id} introuvable.";
                        break;
                    }

                    if ($achat->normalise) {
                        $successCount++;
                        continue;
                    }

                    $fneResult = \App\Modules\Admin\Services\FneService::normaliserAchatBapa($achat);

                    if ($fneResult['success']) {
                        $achat->update([
                            'normalise'     => true,
                            'numero_fne'    => $fneResult['numero_recu'],
                            'signature_dgi' => $fneResult['signature'] ?? null,
                            'qr_code_data'  => $fneResult['qr_code_data'],
                            'fichier_fne_pdf_url' => $fneResult['pdf_url'] ?? null,
                        ]);
                        $successCount++;
                    } else {
                        $errors[] = "Erreur sur l'achat {$achat->numero_facture} : " . ($fneResult['message'] ?? 'Erreur inconnue');
                        break;
                    }
                }
            } catch (\Exception $e) {
                $errors[] = "Exception : " . $e->getMessage();
                break;
            }
        }

        return response()->json([
            'success' => empty($errors),
            'success_count' => $successCount,
            'total' => count($ids),
            'errors' => $errors
        ]);
    }

    public function scheduleBatch(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $flux = $request->input('flux', 'ventes');
        $dateDebut = $request->input('date_debut');
        $dateFin = $request->input('date_fin');
        $batchSize = intval($request->input('batch_size', 15));

        if (empty($dateDebut) || empty($dateFin)) {
            return response()->json([
                'success' => false,
                'message' => 'Les dates de début et de fin sont obligatoires.'
            ], 400);
        }

        if ($batchSize <= 0 || $batchSize > 100) {
            $batchSize = 15;
        }

        // Compter tout de suite les pièces éligibles : autant le dire à
        // l'utilisateur avant de lancer quoi que ce soit. `withoutGlobalScopes`
        // neutralise le filtre de période stocké en session, qui restreindrait
        // la plage de dates saisie dans le formulaire.
        $eligibles = $flux === 'ventes'
            ? Vente::withoutGlobalScopes()
                ->where('normalise', false)
                ->where('etape', 'Facture')
                ->whereHas('pointDeVente', fn ($q) => $q->where('entreprise_id', $entreprise->id))
                ->whereBetween('date_vente', [$dateDebut . ' 00:00:00', $dateFin . ' 23:59:59'])
                ->count()
            : Achat::withoutGlobalScopes()
                ->where('normalise', false)
                ->where('type_facture', 'bapa')
                ->where('etape', 'Facture')
                ->whereHas('pointDeVente', fn ($q) => $q->where('entreprise_id', $entreprise->id))
                ->whereBetween('date_achat', [$dateDebut . ' 00:00:00', $dateFin . ' 23:59:59'])
                ->count();

        if ($eligibles === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun document non normalisé sur cette période.',
            ], 422);
        }

        // Le suivi est écrit AVANT la mise en file : sans cela, tant qu'aucun
        // worker n'a pris le job, le fichier de statut n'existe pas et
        // l'interface conclut « idle », donnant l'impression que le bouton ne
        // fait rien.
        $statusPath = storage_path("app/fne_batch_{$entreprise->id}.json");
        file_put_contents($statusPath, json_encode([
            'status'           => 'queued',
            'flux'             => $flux,
            'total_to_process' => min($eligibles, $batchSize),
            'processed_count'  => 0,
            'current_invoice'  => null,
            'error'            => null,
            'queued_at'        => now()->toDateTimeString(),
            'last_updated'     => now()->toDateTimeString(),
        ]));

        \App\Jobs\BatchNormalisationJob::dispatch(
            $entreprise->id,
            $flux,
            $dateDebut,
            $dateFin,
            $batchSize
        );

        return response()->json([
            'success' => true,
            'total_to_process' => min($eligibles, $batchSize),
            'message' => 'La normalisation en arrière-plan a été lancée.'
        ]);
    }

    public function batchStatus(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $statusPath = storage_path("app/fne_batch_{$entreprise->id}.json");

        if ($request->input('cancel') === '1') {
            if (file_exists($statusPath)) {
                $data = json_decode(file_get_contents($statusPath), true);
                $data['status'] = 'cancelled';
                $data['last_updated'] = now()->toDateTimeString();
                file_put_contents($statusPath, json_encode($data));
                return response()->json(['success' => true, 'message' => 'Demande d\'annulation prise en compte.']);
            }

            return response()->json(['success' => true, 'message' => 'Aucun traitement en cours.']);
        }

        // Purge du suivi : permet a l'interface de repartir d'un etat neutre
        // apres un traitement termine, annule ou en echec.
        if ($request->input('reset') === '1') {
            if (file_exists($statusPath)) {
                unlink($statusPath);
            }

            return response()->json(['status' => 'idle', 'processed_count' => 0, 'total_to_process' => 0]);
        }

        if (!file_exists($statusPath)) {
            return response()->json([
                'status' => 'idle',
                'processed_count' => 0,
                'total_to_process' => 0
            ]);
        }

        $data = json_decode(file_get_contents($statusPath), true) ?: [];

        // Un lot qui reste « queued » signifie qu'aucun worker de file
        // d'attente ne tourne : le job attendra indefiniment. On le dit
        // clairement plutot que de laisser la barre de progression tourner.
        if (($data['status'] ?? null) === 'queued' && !empty($data['queued_at'])) {
            $attenteSecondes = now()->diffInSeconds(\Carbon\Carbon::parse($data['queued_at']));
            $data['waiting_seconds'] = $attenteSecondes;

            if ($attenteSecondes > 30 && config('queue.default') !== 'sync') {
                $data['worker_missing'] = true;
                $data['error'] = 'Le traitement est en file d\'attente mais aucun worker ne le consomme. '
                    . 'Lancez « php artisan queue:work » sur le serveur, ou passez QUEUE_CONNECTION=sync.';
            }
        }

        return response()->json($data);
    }
}
