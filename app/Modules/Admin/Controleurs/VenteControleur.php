<?php

namespace App\Modules\Admin\Controleurs;

use App\Jobs\NormaliserFactureFne;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\TresorerieJournal;
use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Modeles\VenteDetail;
use App\Modules\Admin\Modeles\Banque;
use App\Modules\Admin\Modeles\CodeJournal;
use App\Modules\Admin\Modeles\BonLivraison;
use App\Modules\Admin\Traits\JournaliseActions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VenteControleur
{
    use JournaliseActions;

    public function nouvelle(): View
    {
        $entreprise = Auth::user()->entreprise;
        $pointDeVenteId = Auth::user()->estCaissier()
            ? Auth::user()->point_de_vente_id
            : (session('point_de_vente_actif_id') 
                ?? Auth::user()->point_de_vente_id 
                ?? (\App\Modules\Admin\Modeles\PointDeVente::firstOrCreate([
                    'entreprise_id' => $entreprise->id,
                    'nom'           => 'Siège',
                ], [
                    'ville'         => 'Abidjan',
                    'commune'       => 'Cocody',
                    'responsable'   => 'Superviseur',
                    'statut'        => 'Ouvert',
                ]))->id);
        $clients        = Client::obtenirClientsPrioritaires($entreprise->id);
        $produits       = Produit::where('entreprise_id', $entreprise->id)
            ->orderBy('nom')
            ->get();

        $categories = $produits->pluck('categorie')->unique()->sort()->values();
        $banques    = CodeJournal::where('type', 'Banque')->where('entreprise_id', $entreprise->id)->orderBy('intitule')->get();

        return view('admin::ventes.nouvelle', compact('clients', 'produits', 'categories', 'pointDeVenteId', 'banques'));
    }

    public function enregistrer(Request $request): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;
        $pointDeVenteId = Auth::user()->estCaissier()
            ? Auth::user()->point_de_vente_id
            : (session('point_de_vente_actif_id') 
                ?? Auth::user()->point_de_vente_id 
                ?? (\App\Modules\Admin\Modeles\PointDeVente::firstOrCreate([
                    'entreprise_id' => $entreprise->id,
                    'nom'           => 'Siège',
                ], [
                    'ville'         => 'Abidjan',
                    'commune'       => 'Cocody',
                    'responsable'   => 'Superviseur',
                    'statut'        => 'Ouvert',
                ]))->id);

        $request->validate([
            'client_id'      => ['nullable', 'integer', 'exists:clients,id'],
            'mode_paiement'  => ['required', 'string'],
            'remise'         => ['nullable', 'numeric', 'min:0'],
            'articles'       => ['required', 'array', 'min:1'],
            'articles.*.produit_id'      => ['nullable', 'integer', 'exists:produits,id'],
            'articles.*.libelle_virtuel' => ['nullable', 'string', 'max:255'],
            'articles.*.quantite'        => ['required', 'integer', 'min:1'],
            'articles.*.unite'           => ['nullable', 'string', 'max:50'],
            'articles.*.prix_unitaire'   => ['nullable', 'numeric', 'min:0'],
            'articles.*.tva'             => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'articles.required' => 'Veuillez ajouter au moins un article au panier.',
        ]);

        if ($request->mode_paiement === 'Banque') {
            $request->validate([
                'banque_id'          => ['required', 'integer', 'exists:codes_journaux,id'],
                'moyen_bancaire'     => ['required', 'string', 'in:carte,virement,cheque'],
                'reference_paiement' => ['required', 'string', 'max:255'],
            ], [
                'banque_id.required'          => 'Veuillez sélectionner la banque.',
                'moyen_bancaire.required'     => 'Veuillez sélectionner le moyen de paiement bancaire.',
                'reference_paiement.required' => 'Veuillez saisir le numéro ou référence de paiement.',
            ]);
        }

        if ($request->mode_paiement !== 'Crédit') {
            $request->validate([
                'montant_paye' => ['nullable', 'numeric', 'min:0'],
            ], [
                'montant_paye.numeric' => 'Le montant payé doit être un nombre.',
                'montant_paye.min' => 'Le montant payé doit être supérieur ou égal à 0.',
            ]);
        }

        $venteId = null;
        $etapePrevue = $request->input('etape', 'Facture');

        // Vérification de disponibilité AVANT toute écriture en base : une
        // facturation (décrémentation immédiate du stock) ne doit jamais
        // pouvoir dépasser le stock réellement disponible. Un Devis ou Bon
        // de commande, eux, ne décrémentent rien et ne sont donc pas bloqués
        // ici (ils peuvent réserver plus que le stock actuel, à charge pour
        // l'utilisateur de réapprovisionner avant facturation).
        if ($etapePrevue === 'Facture') {
            foreach ($request->articles as $article) {
                if (empty($article['produit_id'])) continue;

                $produit = Produit::find($article['produit_id']);
                if (!$produit || !$produit->estStockable()) continue;

                $stockDisponible = $produit->stockActuel($pointDeVenteId);
                if ($stockDisponible < $article['quantite']) {
                    return back()->withInput()->with('error',
                        "❌ Stock insuffisant pour « {$produit->nom} » : disponible {$stockDisponible}, demandé {$article['quantite']}."
                    );
                }
            }
        }

        DB::transaction(function () use ($request, $pointDeVenteId, &$venteId, $entreprise) {
            $montantHt  = 0;
            $remise = floatval($request->input('remise', 0));
            $etape = $request->input('etape', 'Facture');

            // 1. Précalcul du montant HT total
            foreach ($request->articles as $article) {
                if (!empty($article['produit_id'])) {
                    $produit = Produit::findOrFail($article['produit_id']);
                    $prix = $produit->prix_vente;
                } else {
                    $prix = floatval($article['prix_unitaire'] ?? 0);
                }

                $ht = $article['quantite'] * $prix;
                $montantHt  += $ht;
            }

            $montantHtNet = max(0, $montantHt - $remise);
            $ratio = $montantHt > 0 ? $montantHtNet / $montantHt : 0;

            // 2. Calcul du montant de TVA total (somme de la TVA nette de chaque article)
            $montantTva = 0;
            foreach ($request->articles as $article) {
                if (!empty($article['produit_id'])) {
                    $produit = Produit::findOrFail($article['produit_id']);
                    $tvaRate = $produit->taux_tva;
                    $prix = $produit->prix_vente;
                } else {
                    $tvaRate = floatval($article['tva'] ?? 18);
                    $prix = floatval($article['prix_unitaire'] ?? 0);
                }
                
                $itemHt = $article['quantite'] * $prix;
                $itemHtNet = $itemHt * $ratio;
                $itemTva = $itemHtNet * ($tvaRate / 100);
                $montantTva += $itemTva;
            }

            $montantTtc = $montantHtNet + $montantTva;

            // Déterminer la valeur finale du mode de paiement pour l'enregistrement
            $modePaiementFinal = $request->mode_paiement;
            if ($request->mode_paiement === 'Banque' && $request->filled('banque_id')) {
                $codeJournal = CodeJournal::findOrFail($request->banque_id);
                $modePaiementFinal = 'Banque : ' . $codeJournal->intitule;
            }

            // Génération numéro de facture
            $numero = \App\Modules\Admin\Services\NumerotationService::genererNumeroVente($entreprise->id, $etape);

            // Calcul du montant payé et statut de la vente
            $montantPaye = 0;
            if ($request->mode_paiement === 'Crédit') {
                $statutVente = 'Crédit';
            } else {
                $montantPaye = $request->filled('montant_paye') ? floatval($request->montant_paye) : $montantTtc;
                if ($montantPaye <= 0) {
                    $statutVente = 'Crédit';
                    $montantPaye = 0;
                } elseif ($montantPaye >= $montantTtc) {
                    $statutVente = 'Payé';
                } else {
                    $statutVente = 'Avance';
                }
            }

            $vente = Vente::create([
                'point_de_vente_id' => $pointDeVenteId,
                'client_id'         => $request->client_id ?: null,
                'numero_facture'    => $numero,
                'date_vente'        => now()->toDateString(),
                'mode_paiement'     => $modePaiementFinal,
                'moyen_bancaire'    => $request->mode_paiement === 'Banque' ? $request->moyen_bancaire : null,
                'reference_paiement'=> $request->mode_paiement === 'Banque' ? $request->reference_paiement : null,
                'montant_ht'        => $montantHt,
                'montant_tva'       => $montantTva,
                'remise'            => $remise,
                'montant_ttc'       => $montantTtc,
                'statut'            => $statutVente,
                'etape'             => $etape,
            ]);

            foreach ($request->articles as $article) {
                if (!empty($article['produit_id'])) {
                    $produit = Produit::lockForUpdate()->findOrFail($article['produit_id']);
                    $prix = $produit->prix_vente;
                    $nomElement = $produit->nom;
                    $tvaRate = $produit->taux_tva;
                } else {
                    $produit = null;
                    $prix = floatval($article['prix_unitaire'] ?? 0);
                    $nomElement = $article['libelle_virtuel'] ?? 'Saisie libre';
                    $tvaRate = floatval($article['tva'] ?? 18);
                }

                $ht = $article['quantite'] * $prix;
                $tva = $ht * ($tvaRate / 100);

                $detail = VenteDetail::create([
                    'vente_id'        => $vente->id,
                    'produit_id'      => $produit ? $produit->id : null,
                    'libelle_virtuel' => $produit ? null : $nomElement,
                    'quantite'        => $article['quantite'],
                    'unite'           => $article['unite'] ?? 'Unité',
                    'prix_unitaire'   => $prix,
                    'montant_tva'     => $tva,
                    'montant_ttc'     => $ht + $tva,
                ]);

                if ($produit && $etape === 'Facture' && $produit->estStockable()) {
                    $stockAvant = $produit->stockActuel($pointDeVenteId);

                    // Contrôle final sous verrou (Produit::lockForUpdate() ci-dessus) :
                    // seconde vérification, autoritaire cette fois, qui protège contre
                    // les ventes concurrentes passées entre la pré-vérification et ici.
                    if ($stockAvant < $article['quantite']) {
                        throw new \InvalidArgumentException(
                            "Stock insuffisant pour « {$produit->nom} » (Disponible: {$stockAvant}, Demandé: {$article['quantite']})."
                        );
                    }

                    $produit->decrementStock($pointDeVenteId, $article['quantite']);

                    // Marquer la ligne comme totalement livrée : la facturation directe
                    // implique une sortie de stock immédiate. Sans cela, cette même
                    // ligne réapparaîtrait dans la file de "Livraisons à valider"
                    // (StockControleur::livraisons()) et son stock serait décrémenté
                    // UNE SECONDE FOIS si un utilisateur validait une "livraison" dessus.
                    $detail->update(['quantite_livree' => $article['quantite']]);

                    MouvementStock::create([
                        'produit_id'         => $produit->id,
                        'point_de_vente_id'  => $pointDeVenteId,
                        'type_mouvement'     => 'Sortie',
                        'quantite'           => $article['quantite'],
                        'stock_avant'        => $stockAvant,
                        'stock_apres'        => $stockAvant - $article['quantite'],
                        'reference_document' => $numero,
                    ]);
                }
            }

            // Trésorerie et Comptabilité (uniquement si validé en étape Facture)
            // Trésorerie et Comptabilité (uniquement si validé en étape Facture)
            if ($etape === 'Facture') {
                // Appel au service FNE pour la Côte d'Ivoire
                // Si pas de client enregistré ou client divers -> RNE (Reçu), sinon EV (Facture)
                $estRne = empty($request->client_id) || ($request->client_id == 'divers');

                // Normalisation FNE en arrière-plan (Section 18.5) — n'attendons pas la réponse HTTP
                NormaliserFactureFne::dispatch($vente, $estRne);

                // Écritures de facturation (+ règlement immédiat le cas échéant).
                // genererEcrituresVente() décide seule si la vente est comptant
                // (aucune ligne 411) ou à crédit total/partiel (411 pour le solde
                // réellement non couvert) — voir ComptabiliteService pour le détail.
                \App\Modules\Admin\Services\ComptabiliteService::genererEcrituresVente(
                    $vente,
                    $statutVente === 'Crédit' ? 0 : $montantPaye,
                    $modePaiementFinal,
                    now()->toDateString(),
                    $request->mode_paiement === 'Banque' ? $request->moyen_bancaire : null,
                    $request->mode_paiement === 'Banque' ? $request->reference_paiement : null
                );

                if ($statutVente !== 'Crédit' && $montantPaye > 0) {
                    $soldeActuel = TresorerieJournal::where('point_de_vente_id', $pointDeVenteId)
                        ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

                    TresorerieJournal::create([
                        'point_de_vente_id'  => $pointDeVenteId,
                        'date_operation'     => now()->toDateString(),
                        'type_operation'     => 'Encaissement',
                        'libelle'            => 'Vente — Facture ' . $numero,
                        'mode_paiement'      => $modePaiementFinal,
                        'moyen_bancaire'     => $request->mode_paiement === 'Banque' ? $request->moyen_bancaire : null,
                        'reference_paiement' => $request->mode_paiement === 'Banque' ? $request->reference_paiement : null,
                        'montant_entree'     => $montantPaye,
                        'montant_sortie'     => 0,
                        'solde_resultat'     => $soldeActuel + $montantPaye,
                        'reference_document' => $numero,
                    ]);
                }
            } else {
                // Étape Devis : Enregistrer le règlement (acompte) dans la trésorerie si présent
                if ($statutVente !== 'Crédit' && $montantPaye > 0) {
                    $soldeActuel = TresorerieJournal::where('point_de_vente_id', $pointDeVenteId)
                        ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

                    TresorerieJournal::create([
                        'point_de_vente_id'  => $pointDeVenteId,
                        'date_operation'     => now()->toDateString(),
                        'type_operation'     => 'Encaissement',
                        'libelle'            => 'Acompte Vente — Devis ' . $numero,
                        'mode_paiement'      => $modePaiementFinal,
                        'montant_entree'     => $montantPaye,
                        'montant_sortie'     => 0,
                        'solde_resultat'     => $soldeActuel + $montantPaye,
                        'reference_document' => $numero,
                    ]);
                }
            }

            $venteId = $vente->id;
        });

        // Journaliser la création de la vente
        $this->journaliser('creation_vente', 'Vente', $venteId ?? null);

        $routeRetour = request()->routeIs('caissier.*') ? 'caissier.ventes.factures' : 'admin.ventes.factures';
        $etape = $request->input('etape', 'Facture');
        return redirect()->route($routeRetour, ['etape' => $etape])
            ->with('succes', $etape . ' enregistré(e) avec succès.');
    }

    public function factures(): View
    {
        $entreprise = Auth::user()->entreprise;
        $pointDeVenteId = Auth::user()->estCaissier()
            ? Auth::user()->point_de_vente_id
            : session('point_de_vente_actif_id');

        $etapeActive = request('etape', 'Facture');
        $type = request('type');
        $voirArchives = request('archives') === '1';

        // Calcul des totaux par étape (non archivés seulement)
        $compteQuery = Vente::where(function($q) {
                $q->whereNull('type_facture')->orWhere('type_facture', '!=', 'avoir');
            })->where('archived', false);
        if ($pointDeVenteId) {
            $compteQuery->where('point_de_vente_id', $pointDeVenteId);
        } else {
            $compteQuery->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id));
        }
        
        $totaux = $compteQuery->select('etape', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('etape')
            ->pluck('total', 'etape')
            ->toArray();

        $nbDV = $totaux['Devis'] ?? 0;
        $nbBC = $totaux['Bon de commande'] ?? 0;
        $nbFacture = $totaux['Facture'] ?? 0;

        // Calcul du total des Bons de Livraison actifs
        $blCompteQuery = BonLivraison::whereNotIn('statut', ['facture']);
        if ($pointDeVenteId) {
            $blCompteQuery->where('point_de_vente_id', $pointDeVenteId);
        } else {
            $blCompteQuery->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id));
        }
        $nbBL = $blCompteQuery->count();

        if ($etapeActive === 'Bon de livraison') {
            $blQuery = BonLivraison::with(['bonDeCommande', 'client', 'pointDeVente', 'facture'])
                ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id));
            if ($pointDeVenteId) {
                $blQuery->where('point_de_vente_id', $pointDeVenteId);
            }
            if (request()->filled('statut')) {
                $blQuery->where('statut', request('statut'));
            }
            $ventes = $blQuery->latest()->paginate(20);
        } else {
            $baseQuery = Vente::with(['client', 'pointDeVente', 'details.produit']);
            
            if ($type === 'avoir') {
                $baseQuery->where('type_facture', 'avoir');
                $etapeActive = 'Facture';
            } else {
                $baseQuery->where(function($q) {
                    $q->whereNull('type_facture')->orWhere('type_facture', '!=', 'avoir');
                });
                $baseQuery->where('etape', $etapeActive);
            }

            // Filtrage archives : par défaut on exclut les archivés
            if ($voirArchives) {
                $baseQuery->where('archived', true);
            } else {
                $baseQuery->where('archived', false);
            }

            if ($pointDeVenteId) {
                $baseQuery->where('point_de_vente_id', $pointDeVenteId);
            } else {
                $baseQuery->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id));
            }

            $ventes = $baseQuery->latest()->paginate(20);
        }

        $facturesDispo = collect();
        if ($type === 'avoir') {
            $facturesDispoQuery = Vente::with('client')
                ->whereHas('pointDeVente', fn($queryPdv) => $queryPdv->where('entreprise_id', $entreprise->id))
                ->where('etape', 'Facture')
                ->where(function($queryNum) {
                    // Accepte l'ancien préfixe (VT-) ET le nouveau (VTE-,
                    // depuis le changement de convention de numérotation).
                    $queryNum->where('numero_facture', 'LIKE', 'VT-%')
                             ->orWhere('numero_facture', 'LIKE', 'VTE-%');
                })
                ->where(function($queryType) {
                    $queryType->whereNull('type_facture')->orWhere('type_facture', '!=', 'avoir');
                })
                ->where('archived', false);
            if ($pointDeVenteId) {
                $facturesDispoQuery->where('point_de_vente_id', $pointDeVenteId);
            }
            $facturesDispo = $facturesDispoQuery->latest()->get();
        }

        $banques = \App\Modules\Admin\Modeles\CodeJournal::where('type', 'Banque')->where('entreprise_id', $entreprise->id)->orderBy('intitule')->get();

        return view('admin::ventes.factures', compact('ventes', 'etapeActive', 'nbDV', 'nbBC', 'nbBL', 'nbFacture', 'type', 'voirArchives', 'facturesDispo', 'banques'));
    }



    public function imprimer(Vente $vente): View
    {
        abort_unless(
            $vente->pointDeVente->entreprise_id === Auth::user()->entreprise_id,
            403
        );

        $vendeur = $vente->utilisateur;
        
        // Calculer ce qui a été effectivement payé
        $dejaPaye = TresorerieJournal::where('reference_document', $vente->numero_facture)->sum('montant_entree');

        return view('admin::factures.vente', compact('vente', 'vendeur', 'dejaPaye'));
    }

    public function imprimerTicket(Vente $vente): View
    {
        abort_unless(
            $vente->pointDeVente->entreprise_id === Auth::user()->entreprise_id,
            403
        );

        $vendeur = $vente->utilisateur;
        $dejaPaye = TresorerieJournal::where('reference_document', $vente->numero_facture)->sum('montant_entree');

        $bl = null;
        if (request()->filled('bl')) {
            $bl = BonLivraison::with('details.produit')->find(request('bl'));
        }

        return view('admin::factures.ticket', compact('vente', 'vendeur', 'dejaPaye', 'bl'));
    }


    /**
     * Formulaire de modification d'une vente.
     */
    public function modifierFormulaire(Vente $vente): View
    {
        abort_unless(
            $vente->pointDeVente->entreprise_id === Auth::user()->entreprise_id,
            403
        );

        if ($vente->normalise) {
            abort(403, 'Cette facture a été normalisée et ne peut plus être modifiée.');
        }

        $entreprise = Auth::user()->entreprise;
        $clients    = Client::obtenirClientsPrioritaires($entreprise->id);
        $produits   = Produit::where('entreprise_id', $entreprise->id)->orderBy('nom')->get();
        $categories = $produits->pluck('categorie')->unique()->sort()->values();
        $banques    = CodeJournal::where('type', 'Banque')->where('entreprise_id', $entreprise->id)->orderBy('intitule')->get();

        // Load details with products
        $vente->load(['details.produit', 'client']);

        return view('admin::ventes.modifier', compact('vente', 'clients', 'produits', 'categories', 'banques'));
    }

    /**
     * Enregistrer la modification d'une vente.
     */
    public function enregistrerModification(Vente $vente, Request $request): RedirectResponse
    {
        abort_unless(
            $vente->pointDeVente->entreprise_id === Auth::user()->entreprise_id,
            403
        );

        if ($vente->normalise) {
            abort(403, 'Cette facture a été normalisée et ne peut plus être modifiée.');
        }

        $request->validate([
            'client_id'      => ['nullable', 'integer', 'exists:clients,id'],
            'mode_paiement'  => ['required', 'string'],
            'remise'         => ['nullable', 'numeric', 'min:0'],
            'articles'       => ['required', 'array', 'min:1'],
            'articles.*.produit_id'      => ['nullable', 'integer', 'exists:produits,id'],
            'articles.*.libelle_virtuel' => ['nullable', 'string', 'max:255'],
            'articles.*.quantite'        => ['required', 'integer', 'min:1'],
            'articles.*.unite'           => ['nullable', 'string', 'max:50'],
            'articles.*.prix_unitaire'   => ['nullable', 'numeric', 'min:0'],
            'articles.*.tva'             => ['nullable', 'numeric', 'min:0', 'max:100'],
        ], [
            'articles.required' => 'Veuillez ajouter au moins un article au panier.',
        ]);

        if ($request->mode_paiement === 'Banque') {
            $request->validate([
                'banque_id'          => ['required', 'integer', 'exists:codes_journaux,id'],
                'moyen_bancaire'     => ['required', 'string', 'in:carte,virement,cheque'],
                'reference_paiement' => ['required', 'string', 'max:255'],
            ], [
                'banque_id.required'          => 'Veuillez sélectionner la banque.',
                'moyen_bancaire.required'     => 'Veuillez sélectionner le moyen de paiement bancaire.',
                'reference_paiement.required' => 'Veuillez saisir le numéro ou référence de paiement.',
            ]);
        }

        DB::transaction(function () use ($vente, $request) {
            $pointDeVenteId = $vente->point_de_vente_id;

            $etaitFacturee = $vente->etape === 'Facture';

            // 1. Restituer les stocks anciens — UNIQUEMENT si la vente était déjà
            //    facturée (le stock n'a jamais été décrémenté pour un Devis ou un
            //    Bon de commande ; l'incrémenter ici serait une fuite de stock fictive).
            $oldDetails = VenteDetail::where('vente_id', $vente->id)->with('produit')->get();
            if ($etaitFacturee) {
                foreach ($oldDetails as $oldDetail) {
                    if ($oldDetail->produit && $oldDetail->produit->estStockable()) {
                        $oldDetail->produit->incrementStock($pointDeVenteId, $oldDetail->quantite);
                    }
                }
            }

            // 2. Supprimer les anciens détails, mouvements de stock et écritures de trésorerie
            VenteDetail::where('vente_id', $vente->id)->delete();
            MouvementStock::where('reference_document', $vente->numero_facture)->delete();
            TresorerieJournal::where('reference_document', $vente->numero_facture)->delete();

            // 3. Recalculer avec les nouveaux articles
            $montantHt  = 0;
            $remise = floatval($request->input('remise', 0));

            // 3.1 Précalcul du montant HT total
            foreach ($request->articles as $article) {
                if (!empty($article['produit_id'])) {
                    $produit = Produit::findOrFail($article['produit_id']);
                    $prix = $produit->prix_vente;
                } else {
                    $prix = floatval($article['prix_unitaire'] ?? 0);
                }
                $ht = $article['quantite'] * $prix;
                $montantHt  += $ht;
            }

            $montantHtNet = max(0, $montantHt - $remise);
            $ratio = $montantHt > 0 ? $montantHtNet / $montantHt : 0;

            // 3.2 Calcul du montant de TVA total
            $montantTva = 0;
            foreach ($request->articles as $article) {
                if (!empty($article['produit_id'])) {
                    $produit = Produit::findOrFail($article['produit_id']);
                    $tvaRate = $produit->taux_tva;
                    $prix = $produit->prix_vente;
                } else {
                    $tvaRate = floatval($article['tva'] ?? 18);
                    $prix = floatval($article['prix_unitaire'] ?? 0);
                }
                
                $itemHt = $article['quantite'] * $prix;
                $itemHtNet = $itemHt * $ratio;
                $itemTva = $itemHtNet * ($tvaRate / 100);
                $montantTva += $itemTva;
            }

            $montantTtc = $montantHtNet + $montantTva;

            // Déterminer le mode de paiement final
            $modePaiementFinal = $request->mode_paiement;
            if ($request->mode_paiement === 'Banque' && $request->filled('banque_id')) {
                $codeJournal = CodeJournal::findOrFail($request->banque_id);
                $modePaiementFinal = 'Banque : ' . $codeJournal->intitule;
            }

            // Statut et montants
            $montantPaye = 0;
            if ($request->mode_paiement === 'Crédit') {
                $statutVente = 'Crédit';
            } else {
                $montantPaye = $request->filled('montant_paye') ? floatval($request->montant_paye) : $montantTtc;
                if ($montantPaye <= 0) {
                    $statutVente = 'Crédit';
                    $montantPaye = 0;
                } elseif ($montantPaye >= $montantTtc) {
                    $statutVente = 'Payé';
                } else {
                    $statutVente = 'Avance';
                }
            }

            // Mettre à jour la vente
            $vente->update([
                'client_id'         => $request->client_id ?: null,
                'mode_paiement'     => $modePaiementFinal,
                'moyen_bancaire'    => $request->mode_paiement === 'Banque' ? $request->moyen_bancaire : null,
                'reference_paiement'=> $request->mode_paiement === 'Banque' ? $request->reference_paiement : null,
                'montant_ht'        => $montantHt,
                'montant_tva'       => $montantTva,
                'remise'            => $remise,
                'montant_ttc'       => $montantTtc,
                'statut'            => $statutVente,
            ]);

            // Re-créer les détails et mouvements
            foreach ($request->articles as $article) {
                if (!empty($article['produit_id'])) {
                    $produit = Produit::lockForUpdate()->findOrFail($article['produit_id']);
                    $prix = $produit->prix_vente;
                    $nomElement = $produit->nom;
                    $tvaRate = $produit->taux_tva;
                } else {
                    $produit = null;
                    $prix = floatval($article['prix_unitaire'] ?? 0);
                    $nomElement = $article['libelle_virtuel'] ?? 'Saisie libre';
                    $tvaRate = floatval($article['tva'] ?? 18);
                }

                $ht = $article['quantite'] * $prix;
                $tva = $ht * ($tvaRate / 100);

                VenteDetail::create([
                    'coupon_id'       => null,
                    'vente_id'        => $vente->id,
                    'produit_id'      => $produit ? $produit->id : null,
                    'libelle_virtuel' => $produit ? null : $nomElement,
                    'quantite'        => $article['quantite'],
                    'unite'           => $article['unite'] ?? 'Unité',
                    'prix_unitaire'   => $prix,
                    'montant_tva'     => $tva,
                    'montant_ttc'     => $ht + $tva,
                ]);

                if ($produit && $etaitFacturee && $produit->estStockable()) {
                    $stockAvant = $produit->stockActuel($pointDeVenteId);

                    if ($stockAvant < $article['quantite']) {
                        throw new \InvalidArgumentException(
                            "Stock insuffisant pour « {$produit->nom} » (Disponible: {$stockAvant}, Demandé: {$article['quantite']})."
                        );
                    }

                    $produit->decrementStock($pointDeVenteId, $article['quantite']);
                    // Voir explication dans store() : évite le double décrément via la
                    // file "Livraisons à valider" (StockControleur::livraisons()).
                    VenteDetail::where('vente_id', $vente->id)
                        ->where('produit_id', $produit->id)
                        ->latest('id')
                        ->limit(1)
                        ->update(['quantite_livree' => $article['quantite']]);

                    MouvementStock::create([
                        'produit_id'         => $produit->id,
                        'point_de_vente_id'  => $pointDeVenteId,
                        'type_mouvement'     => 'Sortie',
                        'quantite'           => $article['quantite'],
                        'stock_avant'        => $stockAvant,
                        'stock_apres'        => $stockAvant - $article['quantite'],
                        'reference_document' => $vente->numero_facture,
                    ]);
                }
            }

            // Trésorerie
            if ($statutVente !== 'Crédit' && $montantPaye > 0) {
                $soldeActuel = TresorerieJournal::where('point_de_vente_id', $pointDeVenteId)
                    ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

                TresorerieJournal::create([
                    'point_de_vente_id'  => $pointDeVenteId,
                    'date_operation'     => now()->toDateString(),
                    'type_operation'     => 'Encaissement',
                    'libelle'            => 'Modification Vente — Facture ' . $vente->numero_facture,
                    'mode_paiement'      => $modePaiementFinal,
                    'moyen_bancaire'     => $request->mode_paiement === 'Banque' ? $request->moyen_bancaire : null,
                    'reference_paiement' => $request->mode_paiement === 'Banque' ? $request->reference_paiement : null,
                    'montant_entree'     => $montantPaye,
                    'montant_sortie'     => 0,
                    'solde_resultat'     => $soldeActuel + $montantPaye,
                    'reference_document' => $vente->numero_facture,
                ]);
            }
        });

        $routeRetour = request()->routeIs('caissier.*') ? 'caissier.ventes.factures' : 'admin.ventes.factures';

        return redirect()->route($routeRetour)
            ->with('succes', 'Facture ' . $vente->numero_facture . ' modifiée avec succès.');
    }

    public function confirmerCommande(Vente $vente): RedirectResponse
    {
        abort_unless($vente->pointDeVente->entreprise_id === Auth::user()->entreprise_id, 403);
        if ($vente->etape !== 'Devis') {
            return back()->with('info', 'Le document n\'est pas à l\'étape Devis.');
        }

        $vente->update(['etape' => 'Bon de commande']);

        return back()->with('succes', 'Commande client confirmée avec succès.');
    }

    public function facturer(Vente $vente): RedirectResponse
    {
        abort_unless($vente->pointDeVente->entreprise_id === Auth::user()->entreprise_id, 403);
        if ($vente->etape === 'Facture') {
            return back()->with('info', 'Cette facture est déjà validée.');
        }

        DB::transaction(function () use ($vente) {
            // Vérification de disponibilité AVANT toute décrémentation.
            foreach ($vente->details as $detail) {
                if ($detail->produit && $detail->produit->estStockable()) {
                    $dispo = $detail->produit->stockActuel($vente->point_de_vente_id);
                    if ($dispo < $detail->quantite) {
                        throw new \InvalidArgumentException(
                            "Stock insuffisant pour « {$detail->produit->nom} » (Disponible: {$dispo}, Demandé: {$detail->quantite})."
                        );
                    }
                }
            }

            $vente->update(['etape' => 'Facture']);

            // 1. Décrémenter le stock uniquement pour les articles stockables
            foreach ($vente->details as $detail) {
                // Verrou de ligne pour éviter toute décrémentation concurrente incohérente
                $produit = $detail->produit ? \App\Modules\Admin\Modeles\Produit::lockForUpdate()->find($detail->produit->id) : null;
                if ($produit && $produit->estStockable()) {
                    $stockAvant = $produit->stockActuel($vente->point_de_vente_id);
                    $produit->decrementStock($vente->point_de_vente_id, $detail->quantite);

                    // Évite le double décrément via la file "Livraisons à valider"
                    // (StockControleur::livraisons()) — voir explication dans store().
                    $detail->update(['quantite_livree' => $detail->quantite]);

                    MouvementStock::create([
                        'produit_id'         => $produit->id,
                        'point_de_vente_id'  => $vente->point_de_vente_id,
                        'type_mouvement'     => 'Sortie',
                        'quantite'           => $detail->quantite,
                        'stock_avant'        => $stockAvant,
                        'stock_apres'        => $stockAvant - $detail->quantite,
                        'reference_document' => $vente->numero_facture,
                    ]);
                }
            }

            // 2. Normalisation FNE en arrière-plan (Section 18.5)
            $estRne = empty($vente->client_id) || ($vente->client_id == 'divers');
            NormaliserFactureFne::dispatch($vente, $estRne);

            // 3. Trésorerie : enregistrer le solde encaissé aujourd'hui, si la facture
            //    est totalement soldée (un éventuel acompte a pu être versé plus tôt,
            //    au stade Devis — voir le bloc "Étape Devis" dans store()).
            $dejaPayeAvant = TresorerieJournal::where('reference_document', $vente->numero_facture)->sum('montant_entree');
            $resteAPayer = $vente->statut === 'Payé' ? max(0, $vente->montant_ttc - $dejaPayeAvant) : 0;

            if ($resteAPayer > 0) {
                $soldeActuel = TresorerieJournal::where('point_de_vente_id', $vente->point_de_vente_id)
                    ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

                TresorerieJournal::create([
                    'point_de_vente_id'  => $vente->point_de_vente_id,
                    'date_operation'     => now()->toDateString(),
                    'type_operation'     => 'Encaissement',
                    'libelle'            => 'Vente — Règlement solde Facture ' . $vente->numero_facture,
                    'mode_paiement'      => $vente->mode_paiement,
                    'moyen_bancaire'     => $vente->moyen_bancaire,
                    'reference_paiement' => $vente->reference_paiement,
                    'montant_entree'     => $resteAPayer,
                    'montant_sortie'     => 0,
                    'solde_resultat'     => $soldeActuel + $resteAPayer,
                    'reference_document' => $vente->numero_facture,
                ]);
            }

            // 4. Écritures comptables de facturation, en une seule opération cohérente :
            //    - le montant total déjà encaissé à ce jour (acompte devis + solde ci-dessus)
            //      est transmis à genererEcrituresVente(), qui décide seule s'il s'agit
            //      d'une facture intégralement soldée (aucune ligne 411) ou d'une facture
            //      à crédit total/partiel (411 pour la part réellement non couverte).
            $montantPayeTotal = $dejaPayeAvant + $resteAPayer;

            \App\Modules\Admin\Services\ComptabiliteService::genererEcrituresVente(
                $vente,
                $montantPayeTotal,
                $vente->mode_paiement,
                now()->toDateString(),
                $vente->moyen_bancaire,
                $vente->reference_paiement
            );
        });

        return back()->with('succes', 'Facture validée, stock mis à jour et écritures comptables générées.');
    }

    /**
     * Générer un avoir sur une facture de vente
     */
    public function creerAvoir(Request $request, Vente $vente): RedirectResponse
    {
        abort_unless($vente->pointDeVente->entreprise_id === Auth::user()->entreprise_id, 403);
        abort_if($vente->type_facture === 'avoir', 400, "Impossible de générer un avoir sur une facture d'avoir.");

        $request->validate([
            'raison' => ['required', 'string', 'max:255'],
        ]);

        $avoirId = null;

        DB::transaction(function () use ($vente, $request, &$avoirId) {
            $numAvoir = \App\Modules\Admin\Services\NumerotationService::genererNumeroVente(
                $vente->pointDeVente->entreprise_id, 'Facture', 'avoir'
            );

            // 1. Création de la facture d'avoir
            $avoir = Vente::create([
                'point_de_vente_id' => $vente->point_de_vente_id,
                'client_id'         => $vente->client_id,
                'utilisateur_id'    => Auth::id(),
                'numero_facture'    => $numAvoir,
                'date_vente'        => now()->toDateString(),
                'mode_paiement'     => $vente->mode_paiement,
                'moyen_bancaire'    => $vente->moyen_bancaire,
                'reference_paiement'=> $request->raison, // Raison de l'avoir
                'montant_ht'        => $vente->montant_ht,
                'montant_tva'       => $vente->montant_tva,
                'remise'            => $vente->remise,
                'montant_ttc'       => $vente->montant_ttc,
                'statut'            => 'Payé',
                'type_facture'      => 'avoir',
                'etape'             => 'Facture',
            ]);

            // 2. Copie des détails et retour en stock
            foreach ($vente->details as $detail) {
                VenteDetail::create([
                    'vente_id'        => $avoir->id,
                    'produit_id'      => $detail->produit_id,
                    'libelle_virtuel' => $detail->libelle_virtuel,
                    'quantite'        => $detail->quantite,
                    'unite'           => $detail->unite,
                    'prix_unitaire'   => $detail->prix_unitaire,
                    'montant_tva'     => $detail->montant_tva,
                    'montant_ttc'     => $detail->montant_ttc,
                ]);

                // Ré-incrémenter le stock si le produit est stockable
                if ($detail->produit && $detail->produit->estStockable()) {
                    $stockAvant = $detail->produit->stockActuel($vente->point_de_vente_id);
                    $detail->produit->incrementStock($vente->point_de_vente_id, $detail->quantite);

                    MouvementStock::create([
                        'produit_id'         => $detail->produit_id,
                        'point_de_vente_id'  => $vente->point_de_vente_id,
                        'type_mouvement'     => 'Entrée', // Retour client
                        'quantite'           => $detail->quantite,
                        'stock_avant'        => $stockAvant,
                        'stock_apres'        => $stockAvant + $detail->quantite,
                        'reference_document' => $numAvoir,
                    ]);
                }
            }

            // 3. Écritures comptables
            \App\Modules\Admin\Services\ComptabiliteService::genererEcritureAvoirVente($avoir);

            // 4. Si la facture d'origine était déjà payée en espèces, on simule la sortie de caisse du remboursement
            if (str_contains(strtolower($vente->mode_paiement), 'espèces') || str_contains(strtolower($vente->mode_paiement), 'caisse')) {
                $soldeActuel = TresorerieJournal::where('point_de_vente_id', $vente->point_de_vente_id)
                    ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

                TresorerieJournal::create([
                    'point_de_vente_id'  => $vente->point_de_vente_id,
                    'date_operation'     => now()->toDateString(),
                    'type_operation'     => 'Décaissement', // Remboursement client
                    'libelle'            => 'Remboursement Avoir client ' . $numAvoir,
                    'mode_paiement'      => $vente->mode_paiement,
                    'montant_entree'     => 0,
                    'montant_sortie'     => $vente->montant_ttc,
                    'solde_resultat'     => $soldeActuel - $vente->montant_ttc,
                    'reference_document' => $numAvoir,
                ]);
            }

            $avoirId = $avoir->id;
        });

        $this->journaliser('creation_avoir_vente', 'Vente', $avoirId);

        return redirect()->route(request()->routeIs('caissier.*') ? 'caissier.ventes.imprimer' : 'admin.ventes.imprimer', $avoirId)
            ->with('succes', "Facture d'avoir générée avec succès ! Les stocks et écritures comptables inverses ont été validés.");
    }

    /**
     * Lot H : Normalisation manuelle DGI/FNE.
     * Dispatch le job de normalisation pour une facture de vente non encore normalisée.
     */
    public function normaliser(Vente $vente): RedirectResponse
    {
        if ($vente->normalise) {
            return back()->with('info', 'Cette facture est déjà normalisée.');
        }

        if ($vente->etape !== 'Facture') {
            return back()->with('erreur', 'Seules les factures finalisées peuvent être normalisées.');
        }

        NormaliserFactureFne::dispatch($vente);

        $this->journaliser('normalisation_manuelle_vente', 'Vente', $vente->id);

        return back()->with('succes', 'La normalisation DGI a été lancée avec succès. Elle sera traitée en arrière-plan.');
    }

    // ──────────────────────────────────────────────────────────────────────────
    // WORKFLOW DEVIS → COMMANDE → FACTURE
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Passer un devis/commande au statut Envoyé.
     */
    public function envoyer(Vente $vente): RedirectResponse
    {
        abort_unless($vente->pointDeVente->entreprise_id === Auth::user()->entreprise_id, 403);
        abort_unless(in_array($vente->etape, ['Devis', 'Bon de commande']), 403);

        $vente->update(['statut' => 'Envoyé']);

        return back()->with('succes', ucfirst(strtolower($vente->etape)) . ' marqué comme envoyé.');
    }

    /**
     * Convertir un devis en bon de commande.
     * L'original est archivé, un clone est créé à l'étape Bon de commande.
     */
    public function convertirEnCommande(Vente $vente): RedirectResponse
    {
        abort_unless($vente->pointDeVente->entreprise_id === Auth::user()->entreprise_id, 403);
        if ($vente->etape !== 'Devis') {
            return back()->with('erreur', 'Ce document n\'est pas un devis.');
        }

        DB::transaction(function () use ($vente) {
            // 1. Archiver le devis d'origine
            $vente->update(['archived' => true]);

            // 2. Cloner en Bon de commande
            $entrepriseId = $vente->pointDeVente->entreprise_id;
            $nouveauNumero = \App\Modules\Admin\Services\NumerotationService::genererNumeroVente($entrepriseId, 'Bon de commande');

            $clone = $vente->replicate(['archived', 'normalise', 'numero_fne', 'signature_dgi', 'qr_code_data']);
            $clone->numero_facture = $nouveauNumero;
            $clone->etape          = 'Bon de commande';
            $clone->statut         = 'Brouillon';
            $clone->archived       = false;
            $clone->normalise      = false;
            $clone->numero_fne     = null;
            $clone->signature_dgi  = null;
            $clone->qr_code_data   = null;
            $clone->save();

            // 3. Cloner les lignes de détail
            foreach ($vente->details as $detail) {
                $newDetail = $detail->replicate();
                $newDetail->vente_id = $clone->id;
                $newDetail->save();
            }
        });

        return back()->with('succes', 'Le devis a été converti en bon de commande et archivé.');
    }

    /**
     * Convertir un bon de commande en facture à finaliser.
     * L'original est archivé, un clone Facture est créé et l'utilisateur est
     * redirigé vers sa fiche de modification pour saisir le paiement.
     */
    public function convertirEnFacture(Vente $vente): RedirectResponse
    {
        abort_unless($vente->pointDeVente->entreprise_id === Auth::user()->entreprise_id, 403);
        if ($vente->etape !== 'Bon de commande') {
            return back()->with('erreur', 'Ce document n\'est pas un bon de commande.');
        }

        $nouvelleFactureId = null;

        DB::transaction(function () use ($vente, &$nouvelleFactureId) {
            // 1. Archiver le bon de commande d'origine
            $vente->update(['archived' => true]);

            // 2. Cloner en Facture (statut Crédit par défaut, en attente de finalisation)
            $entrepriseId = $vente->pointDeVente->entreprise_id;
            $nouveauNumero = \App\Modules\Admin\Services\NumerotationService::genererNumeroVente($entrepriseId, 'Facture');

            $clone = $vente->replicate(['archived', 'normalise', 'numero_fne', 'signature_dgi', 'qr_code_data']);
            $clone->numero_facture = $nouveauNumero;
            $clone->etape          = 'Facture';
            $clone->statut         = 'Crédit';
            $clone->archived       = false;
            $clone->normalise      = false;
            $clone->numero_fne     = null;
            $clone->signature_dgi  = null;
            $clone->qr_code_data   = null;
            $clone->save();

            // 3. Cloner les lignes
            foreach ($vente->details as $detail) {
                $newDetail = $detail->replicate();
                $newDetail->vente_id = $clone->id;
                $newDetail->save();
            }

            $nouvelleFactureId = $clone->id;
        });

        // Rediriger vers la modification pour finaliser le paiement
        $route = request()->routeIs('caissier.*') ? 'caissier.ventes.modifier' : 'admin.ventes.modifier';
        return redirect()->route($route, $nouvelleFactureId)
            ->with('succes', 'Bon de commande converti en facture. Veuillez renseigner le mode de paiement et valider.');
    }

    /**
     * Supprimer définitivement une vente archivée.
     */
    public function supprimer(Vente $vente): RedirectResponse
    {
        abort_unless($vente->pointDeVente->entreprise_id === Auth::user()->entreprise_id, 403);
        abort_if($vente->type_facture === 'avoir', 403, "Impossible de supprimer une facture d'avoir.");
        abort_unless($vente->archived, 403, 'Seuls les documents archivés peuvent être supprimés.');

        $etape = $vente->etape;
        $vente->details()->delete();
        $vente->delete();

        $routeParam = match($etape) {
            'Devis'           => '?etape=Devis&archives=1',
            'Bon de commande' => '?etape=Bon+de+commande&archives=1',
            default           => '?archives=1',
        };

        $baseRoute = request()->routeIs('caissier.*') ? 'caissier.ventes.factures' : 'admin.ventes.factures';
        return redirect()->route($baseRoute)->withInput(['etape' => $etape, 'archives' => '1'])
            ->with('succes', 'Document supprimé définitivement.');
    }

    public function rechercherFacturesPourAvoir(Request $request): \Illuminate\Http\JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $q = $request->query('q');

        $query = Vente::with('client')
            ->whereHas('pointDeVente', fn($queryPdv) => $queryPdv->where('entreprise_id', $entreprise->id))
            ->where('etape', 'Facture')
            ->where(function($queryNum) {
                $queryNum->where('numero_facture', 'LIKE', 'VT-%')
                         ->orWhere('numero_facture', 'LIKE', 'VTE-%');
            })
            ->where(function($queryType) {
                $queryType->whereNull('type_facture')->orWhere('type_facture', '!=', 'avoir');
            })
            ->where('archived', false);

        if ($q) {
            $query->where(function($querySearch) use ($q) {
                $querySearch->where('numero_facture', 'like', "%{$q}%")
                    ->orWhere('numero_fne', 'like', "%{$q}%")
                    ->orWhereHas('client', fn($queryClient) => $queryClient->where('nom', 'like', "%{$q}%"));
            });
        }

        $factures = $query->latest()->limit(10)->get()->map(function($f) {
            $clientNom = $f->client ? $f->client->nom : 'Client de passage';
            return [
                'id' => $f->id,
                'text' => "{$f->numero_facture} - {$clientNom} (" . number_format($f->montant_ttc, 0, ',', ' ') . " XOF)"
            ];
        });

        return response()->json($factures);
    }

    public function detailsFacturePourAvoir(Vente $vente): \Illuminate\Http\JsonResponse
    {
        abort_unless($vente->pointDeVente->entreprise_id === Auth::user()->entreprise_id, 403);
        $vente->load(['details.produit', 'client']);

        return response()->json([
            'id' => $vente->id,
            'numero_facture' => $vente->numero_facture,
            'client_nom' => $vente->client ? $vente->client->nom : 'Client de passage',
            'montant_ttc' => $vente->montant_ttc,
            'details' => $vente->details->map(function($d) {
                return [
                    'id' => $d->id,
                    'produit_id' => $d->produit_id,
                    'libelle' => $d->produit ? $d->produit->nom : $d->libelle_virtuel,
                    'quantite' => $d->quantite,
                    'prix_unitaire' => $d->prix_unitaire,
                    'montant_tva' => $d->montant_tva,
                    'montant_ttc' => $d->montant_ttc,
                    'unite' => $d->unite ?? 'pcs',
                    'est_stockable' => $d->produit ? $d->produit->estStockable() : false,
                ];
            })
        ]);
    }

    public function creerAvoirNouveau(Request $request): RedirectResponse
    {
        $request->validate([
            'parent_id' => ['required', 'exists:ventes,id'],
            'raison'    => ['required', 'string', 'max:255'],
            'items'     => ['required', 'array'],
        ]);

        $parent = Vente::findOrFail($request->parent_id);
        abort_unless($parent->pointDeVente->entreprise_id === Auth::user()->entreprise_id, 403);
        abort_if($parent->type_facture === 'avoir', 400, "Impossible de générer un avoir sur une facture d'avoir.");

        $avoirId = null;

        DB::transaction(function () use ($parent, $request, &$avoirId) {
            $numAvoir = \App\Modules\Admin\Services\NumerotationService::genererNumeroVente(
                $parent->pointDeVente->entreprise_id, 'Facture', 'avoir'
            );

            // 1. Création de la facture d'avoir
            $avoir = Vente::create([
                'point_de_vente_id' => $parent->point_de_vente_id,
                'client_id'         => $parent->client_id,
                'utilisateur_id'    => Auth::id(),
                'numero_facture'    => $numAvoir,
                'date_vente'        => now()->toDateString(),
                'mode_paiement'     => $parent->mode_paiement,
                'moyen_bancaire'    => $parent->moyen_bancaire,
                'reference_paiement'=> $request->raison,
                'statut'            => 'Payé',
                'type_facture'      => 'avoir',
                'etape'             => 'Facture',
                'parent_id'         => $parent->id,
                'raison_avoir'      => $request->raison,
                'montant_ht'        => 0,
                'montant_tva'       => 0,
                'remise'            => 0,
                'montant_ttc'       => 0,
            ]);

            $totalHt = 0;
            $totalTva = 0;
            $totalTtc = 0;

            // 2. Traitement des lignes
            foreach ($request->items as $itemId => $itemData) {
                $isNouveau = isset($itemData['est_nouveau']) && $itemData['est_nouveau'] == 1;
                $qteAvoir = floatval($itemData['quantite']);
                $prixUnit = floatval($itemData['prix_unitaire']);

                if ($qteAvoir <= 0) continue;

                if ($isNouveau) {
                    $produitId = $itemData['produit_id'] ?? null;
                    $libelle = $itemData['libelle_virtuel'] ?? 'Article';
                    
                    $produit = null;
                    if ($produitId) {
                        $produit = \App\Modules\Admin\Modeles\Produit::find($produitId);
                    }

                    $tvaRate = (floatval($itemData['taux_tva'] ?? 18.0)) / 100;
                    $unite = $produit ? $produit->unite : 'pcs';
                    
                    $itemHt = $qteAvoir * $prixUnit;
                    $itemTva = $itemHt * $tvaRate;
                    $itemTtc = $itemHt + $itemTva;

                    VenteDetail::create([
                        'vente_id'        => $avoir->id,
                        'produit_id'      => $produitId,
                        'libelle_virtuel' => $libelle,
                        'quantite'        => $qteAvoir,
                        'unite'           => $unite,
                        'prix_unitaire'   => $prixUnit,
                        'montant_tva'     => $itemTva,
                        'montant_ttc'     => $itemTtc,
                    ]);

                    $totalHt += $itemHt;
                    $totalTva += $itemTva;
                    $totalTtc += $itemTtc;

                    // Action sur stock pour produit catalogue stockable
                    if ($produit && $produit->estStockable()) {
                        $stockAction = $itemData['stock_action'] ?? 'none';
                        if ($stockAction === 'reinject') {
                            $stockAvant = $produit->stockActuel($parent->point_de_vente_id);
                            $produit->incrementStock($parent->point_de_vente_id, $qteAvoir);

                            MouvementStock::create([
                                'produit_id'         => $produitId,
                                'point_de_vente_id'  => $parent->point_de_vente_id,
                                'type_mouvement'     => 'Entrée',
                                'quantite'           => $qteAvoir,
                                'stock_avant'        => $stockAvant,
                                'stock_apres'        => $stockAvant + $qteAvoir,
                                'reference_document' => $numAvoir,
                                'notes'              => 'Retour client - Réinjecté en stock vendable (Ajouté)',
                            ]);
                        } elseif ($stockAction === 'scrap') {
                            MouvementStock::create([
                                'produit_id'         => $produitId,
                                'point_de_vente_id'  => $parent->point_de_vente_id,
                                'type_mouvement'     => 'Entrée',
                                'quantite'           => $qteAvoir,
                                'stock_avant'        => 0,
                                'stock_apres'        => 0,
                                'reference_document' => $numAvoir,
                                'notes'              => 'Retour client défectueux - Mis au rebut (Ajouté)',
                            ]);
                        }
                    }
                } else {
                    $detail = VenteDetail::where('vente_id', $parent->id)->where('id', $itemId)->first();
                    if (!$detail) continue;

                    $tvaRate = ($detail->montant_ttc - $detail->montant_ht) > 0 ? 0.18 : 0;

                    $itemHt = $qteAvoir * $prixUnit;
                    $itemTva = $itemHt * $tvaRate;
                    $itemTtc = $itemHt + $itemTva;

                    VenteDetail::create([
                        'vente_id'        => $avoir->id,
                        'produit_id'      => $detail->produit_id,
                        'libelle_virtuel' => $detail->libelle_virtuel,
                        'quantite'        => $qteAvoir,
                        'unite'           => $detail->unite,
                        'prix_unitaire'   => $prixUnit,
                        'montant_tva'     => $itemTva,
                        'montant_ttc'     => $itemTtc,
                    ]);

                    $totalHt += $itemHt;
                    $totalTva += $itemTva;
                    $totalTtc += $itemTtc;

                    // Action sur stock
                    if ($detail->produit && $detail->produit->estStockable()) {
                        $stockAction = $itemData['stock_action'] ?? 'none';
                        if ($stockAction === 'reinject') {
                            $stockAvant = $detail->produit->stockActuel($parent->point_de_vente_id);
                            $detail->produit->incrementStock($parent->point_de_vente_id, $qteAvoir);

                            MouvementStock::create([
                                'produit_id'         => $detail->produit_id,
                                'point_de_vente_id'  => $parent->point_de_vente_id,
                                'type_mouvement'     => 'Entrée',
                                'quantite'           => $qteAvoir,
                                'stock_avant'        => $stockAvant,
                                'stock_apres'        => $stockAvant + $qteAvoir,
                                'reference_document' => $numAvoir,
                                'notes'              => 'Retour client - Réinjecté en stock vendable',
                            ]);
                        } elseif ($stockAction === 'scrap') {
                            MouvementStock::create([
                                'produit_id'         => $detail->produit_id,
                                'point_de_vente_id'  => $parent->point_de_vente_id,
                                'type_mouvement'     => 'Entrée',
                                'quantite'           => $qteAvoir,
                                'stock_avant'        => 0,
                                'stock_apres'        => 0,
                                'reference_document' => $numAvoir,
                                'notes'              => 'Retour client défectueux - Mis au rebut',
                            ]);
                        }
                    }
                }
            }

            $avoir->update([
                'montant_ht'  => $totalHt,
                'montant_tva' => $totalTva,
                'montant_ttc' => $totalTtc,
            ]);

            // 3. Écritures comptables
            \App\Modules\Admin\Services\ComptabiliteService::genererEcritureAvoirVente($avoir);

            // 4. Sortie de trésorerie si remboursement caisse
            if (str_contains(strtolower($parent->mode_paiement), 'espèces') || str_contains(strtolower($parent->mode_paiement), 'caisse')) {
                $soldeActuel = TresorerieJournal::where('point_de_vente_id', $parent->point_de_vente_id)
                    ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

                TresorerieJournal::create([
                    'point_de_vente_id'  => $parent->point_de_vente_id,
                    'date_operation'     => now()->toDateString(),
                    'type_operation'     => 'Décaissement',
                    'libelle'            => 'Remboursement Avoir client ' . $numAvoir,
                    'mode_paiement'      => $parent->mode_paiement,
                    'montant_entree'     => 0,
                    'montant_sortie'     => $totalTtc,
                    'solde_resultat'     => $soldeActuel - $totalTtc,
                    'reference_document' => $numAvoir,
                ]);
            }

            $avoirId = $avoir->id;
        });

        $this->journaliser('creation_avoir_vente', 'Vente', $avoirId);

        $route = request()->routeIs('caissier.*') ? 'caissier.ventes.factures' : 'admin.ventes.factures';
        return redirect()->route($route, ['type' => 'avoir'])
            ->with('succes', "Facture d'avoir générée avec succès.");
    }

    public function produitsParCategorie(): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();
        $entrepriseId = $user->entreprise_id;

        $produits = \App\Modules\Admin\Modeles\Produit::with('category')
            ->where('entreprise_id', $entrepriseId)
            ->where('statut', 'actif')
            ->get();

        $grouped = [];
        foreach ($produits as $p) {
            $catNom = $p->category ? $p->category->nom : 'Non Catégorisé';
            $grouped[$catNom][] = [
                'id' => $p->id,
                'nom' => $p->nom,
                'prix_vente' => floatval($p->prix_vente),
                'prix_achat' => floatval($p->prix_achat),
                'unite' => $p->unite ?? 'pcs',
                'est_stockable' => $p->estStockable(),
                'taux_tva' => floatval($p->taux_tva ?? 18.0),
            ];
        }

        return response()->json($grouped);
    }
}
