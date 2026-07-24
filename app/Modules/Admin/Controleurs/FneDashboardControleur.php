<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\TresorerieJournal;
use App\Modules\Admin\Modeles\OrdreProduction;
use App\Modules\Admin\Modeles\TransfertStock;
use App\Modules\Admin\Modeles\EcritureComptable;
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
        $entreprise = Auth::user()->entreprise;
        $pointsDeVente = PointDeVente::where('entreprise_id', $entreprise->id)->orderBy('nom')->get();

        return view('admin::fne.gestion', [
            'entreprise' => $entreprise,
            'pointsDeVente' => $pointsDeVente,
            'kpis' => $this->calculerKpisGestionFne($request, $entreprise->id),
        ]);
    }

    public function gestionJson(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        return response()->json($this->calculerKpisGestionFne($request, $entreprise->id));
    }

    private function calculerKpisGestionFne(Request $request, int $entrepriseId): array
    {
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

            // Indicateurs propres à la plateforme DGI — non disponibles sans
            // appel API dédié (non implémenté). Affichés à 0 par choix validé.
            'stickers_solde' => 0,
            'stickers_achats' => 0,
            'stickers_consommes' => 0,
            'timbre_quittance' => 0,

            'ventes' => [
                'factures' => ['nombre' => (clone $facturesVente)->count(), 'montant' => (clone $facturesVente)->sum('montant_ttc')],
                'avoirs'   => ['nombre' => (clone $avoirsVente)->count(), 'montant' => (clone $avoirsVente)->sum('montant_ttc')],
                'recus'    => ['nombre' => (clone $recusVente)->count(), 'montant' => (clone $recusVente)->sum('montant_ttc')],
                'proforma' => ['nombre' => 0, 'montant' => 0], // Non applicable : Selflow ne gère pas de proforma vente
                'total_ht' => $totalHtVente,
                'total_tva' => $totalTvaVente,
                'total_ttc' => $totalTtcVente,
                'total_remises' => $totalRemiseVente,
            ],

            'achats' => [
                'factures' => ['nombre' => (clone $facturesAchat)->count(), 'montant' => (clone $facturesAchat)->sum('montant_ttc')],
                'avoirs'   => ['nombre' => (clone $avoirsAchat)->count(), 'montant' => (clone $avoirsAchat)->sum('montant_ttc')],
                'total_ht' => (clone $achatsQuery)->where('type_facture', '!=', 'avoir')->sum('montant_ht'),
                'total_tva_deductible' => (clone $achatsQuery)->where('type_facture', '!=', 'avoir')->sum('montant_tva'),
                'total_ttc' => (clone $achatsQuery)->where('type_facture', '!=', 'avoir')->sum('montant_ttc'),
            ],
        ];
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
            $query = Vente::with(['client', 'pointDeVente'])
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

            $documents = $query->orderByDesc('date_vente')->paginate($parPage);

            $lignes = $documents->getCollection()->map(function (Vente $v) {
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
                    'telechargement_url' => $v->normalise && $v->fichier_fne_pdf_url
                        ? $v->fichier_fne_pdf_url
                        : route('admin.ventes.imprimer', $v->id),
                ];
            });
        } else {
            $query = Achat::with(['fournisseur', 'pointDeVente'])
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

            $documents = $query->orderByDesc('date_achat')->paginate($parPage);

            $lignes = $documents->getCollection()->map(function (Achat $a) {
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
                    'telechargement_url' => $a->normalise && $a->fichier_fne_pdf_url
                        ? $a->fichier_fne_pdf_url
                        : route('admin.achats.imprimer', $a->id),
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
}
