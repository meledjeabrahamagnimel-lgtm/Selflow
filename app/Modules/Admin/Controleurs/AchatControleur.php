<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\AchatDetail;
use App\Modules\Admin\Modeles\Fournisseur;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\TresorerieJournal;
use App\Modules\Admin\Modeles\CodeJournal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Modules\Admin\Traits\JournaliseActions;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use App\Jobs\NormaliserAchatBapaJob;

class AchatControleur
{
    use JournaliseActions;

    public function nouveau(): View
    {
        $entreprise  = Auth::user()->entreprise;
        $fournisseurs = Fournisseur::obtenirFournisseursPrioritaires($entreprise->id);
        $produits     = Produit::where('entreprise_id', $entreprise->id)->orderBy('nom')->get();
        $pointDeVenteId = session('point_de_vente_actif_id') 
            ?? Auth::user()->point_de_vente_id 
            ?? (\App\Modules\Admin\Modeles\PointDeVente::firstOrCreate([
                'entreprise_id' => $entreprise->id,
                'nom'           => 'Siège',
            ], [
                'ville'         => 'Abidjan',
                'commune'       => 'Cocody',
                'responsable'   => 'Superviseur',
                'statut'        => 'Ouvert',
            ]))->id;
        $banques = CodeJournal::where('type', 'Banque')
            ->where('entreprise_id', $entreprise->id)
            ->orderBy('intitule')
            ->get();

        return view('admin::achats.nouveau', compact('fournisseurs', 'produits', 'pointDeVenteId', 'banques'));
    }

    public function enregistrer(Request $request): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;
        $pointDeVenteId = session('point_de_vente_actif_id') 
            ?? Auth::user()->point_de_vente_id 
            ?? (\App\Modules\Admin\Modeles\PointDeVente::firstOrCreate([
                'entreprise_id' => $entreprise->id,
                'nom'           => 'Siège',
            ], [
                'ville'         => 'Abidjan',
                'commune'       => 'Cocody',
                'responsable'   => 'Superviseur',
                'statut'        => 'Ouvert',
            ]))->id;

        $request->validate([
            'fournisseur_id'             => ['required', 'integer', 'exists:fournisseurs,id'],
            'date_achat'                 => ['required', 'date'],
            'mode_paiement'              => ['required', 'string'],
            'numero_facture_fournisseur' => ['nullable', 'string', 'max:100'],
            'articles'                   => ['required', 'array', 'min:1'],
            'articles.*.produit_id'      => ['nullable', 'integer', 'exists:produits,id'],
            'articles.*.libelle_virtuel' => ['nullable', 'string', 'max:255'],
            'articles.*.quantite'        => ['required', 'integer', 'min:1'],
            'articles.*.prix_unitaire'   => ['required', 'numeric', 'min:0'],
            'articles.*.unite'           => ['nullable', 'string', 'max:50'],
        ], [
            'fournisseur_id.required' => 'Veuillez sélectionner un fournisseur.',
            'articles.required'       => 'Veuillez ajouter au moins un article.',
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

        $achat = DB::transaction(function () use ($request, $pointDeVenteId, $entreprise) {
            $montantHt  = 0;
            $montantTva = 0;
            $etape = $request->input('etape', 'Facture');

            // --- Calcul HT et TVA ligne par ligne depuis les taux produits ---
            foreach ($request->articles as $article) {
                $ht = (float)$article['quantite'] * (float)$article['prix_unitaire'];
                $montantHt += $ht;

                // Récupérer le taux TVA du produit sélectionné
                if (!empty($article['produit_id'])) {
                    $produit = Produit::find($article['produit_id']);
                    $tauxTva = $produit ? (float)($produit->taux_tva ?? 0) : 0;
                    if ($tauxTva > 0) {
                        $montantTva += round($ht * ($tauxTva / 100), 2);
                    }
                }
                // Pour les lignes libres (sans produit), pas de TVA automatique
            }

            $montantTtc = $montantHt + $montantTva;

            // Déterminer le mode de paiement final
            $modePaiementFinal = $request->mode_paiement;
            if ($request->mode_paiement === 'Banque' && $request->filled('banque_id')) {
                $codeJournal = CodeJournal::findOrFail($request->banque_id);
                $modePaiementFinal = 'Banque : ' . $codeJournal->intitule;
            }

            // Générer le numéro de facture INTERNE (référentiel système)
            $numero = \App\Modules\Admin\Services\NumerotationService::genererNumeroAchat($entreprise->id, $etape);

            // Numéro de facture fournisseur (saisi manuellement pour achats externes)
            $numeroFournisseur = $request->filled('numero_facture_fournisseur')
                ? trim($request->numero_facture_fournisseur)
                : null;

            // Statut de départ de l'achat : "En attente de confirmation" par défaut
            $statutInitial = ($etape === 'Facture') ? (($request->mode_paiement === 'Crédit') ? 'Crédit' : 'Payé') : 'En attente de confirmation';

            $achat = Achat::create([
                'point_de_vente_id'          => $pointDeVenteId,
                'fournisseur_id'             => $request->fournisseur_id,
                'numero_facture'             => $numero,
                'numero_facture_fournisseur' => $numeroFournisseur,
                'date_achat'                 => $request->date_achat,
                'mode_paiement'              => $modePaiementFinal,
                'moyen_bancaire'             => $request->mode_paiement === 'Banque' ? $request->moyen_bancaire : null,
                'reference_paiement'         => $request->mode_paiement === 'Banque' ? $request->reference_paiement : null,
                'montant_ht'                 => $montantHt,
                'montant_tva'                => $montantTva,
                'montant_ttc'                => $montantTtc,
                'statut'                     => $statutInitial,
                'etape'                      => $etape,
            ]);

            foreach ($request->articles as $article) {
                $produit = !empty($article['produit_id']) ? Produit::lockForUpdate()->find($article['produit_id']) : null;
                $ht      = (float)$article['quantite'] * (float)$article['prix_unitaire'];
                $tvaDeLigne = 0;
                if ($produit) {
                    $tauxTvaProduit = (float)($produit->taux_tva ?? 0);
                    if ($tauxTvaProduit > 0) {
                        $tvaDeLigne = round($ht * ($tauxTvaProduit / 100), 2);
                    }
                }

                AchatDetail::create([
                    'achat_id'       => $achat->id,
                    'produit_id'     => $produit ? $produit->id : null,
                    'libelle_virtuel'=> $produit ? null : ($article['libelle_virtuel'] ?? 'Saisie libre'),
                    'quantite'       => $article['quantite'],
                    'unite'          => $article['unite'] ?? 'Unité',
                    'prix_unitaire'  => $article['prix_unitaire'],
                    'montant_tva'    => $tvaDeLigne,
                    'montant_ttc'    => $ht + $tvaDeLigne,
                ]);

                // Augmenter le stock + mouvement uniquement si Facture et stockable
                if ($produit && $etape === 'Facture' && $produit->estStockable()) {
                    $stockAvant = $produit->stockActuel($pointDeVenteId);
                    $produit->incrementStock($pointDeVenteId, $article['quantite']);

                    MouvementStock::create([
                        'produit_id'         => $produit->id,
                        'point_de_vente_id'  => $pointDeVenteId,
                        'type_mouvement'     => 'Entrée',
                        'quantite'           => $article['quantite'],
                        'stock_avant'        => $stockAvant,
                        'stock_apres'        => $stockAvant + $article['quantite'],
                        'reference_document' => $numero,
                    ]);
                }
            }

            // Trésorerie et Comptabilité (uniquement si Facture)
            if ($etape === 'Facture') {
                // Écriture comptable générale
                \App\Modules\Admin\Services\ComptabiliteService::genererEcritureFactureAchat($achat);

                $soldeActuel = TresorerieJournal::where('point_de_vente_id', $pointDeVenteId)
                    ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

                TresorerieJournal::create([
                    'point_de_vente_id'  => $pointDeVenteId,
                    'date_operation'     => $request->date_achat,
                    'type_operation'     => 'Décaissement',
                    'libelle'            => 'Achat — Facture ' . $numero,
                    'mode_paiement'      => $modePaiementFinal,
                    'moyen_bancaire'     => $request->mode_paiement === 'Banque' ? $request->moyen_bancaire : null,
                    'reference_paiement' => $request->mode_paiement === 'Banque' ? $request->reference_paiement : null,
                    'montant_entree'     => 0,
                    'montant_sortie'     => $montantTtc,
                    'solde_resultat'     => $soldeActuel - $montantTtc,
                    'reference_document' => $numero,
                ]);

                // Écriture comptable de règlement
                \App\Modules\Admin\Services\ComptabiliteService::genererEcritureReglementAchat(
                    $achat,
                    $montantTtc,
                    $modePaiementFinal,
                    $request->date_achat,
                    $request->mode_paiement === 'Banque' ? $request->moyen_bancaire : null,
                    $request->mode_paiement === 'Banque' ? $request->reference_paiement : null
                );
            }

            return $achat;
        });

        // Si c'est une facture finalisée et que le fournisseur n'a pas de NCC, normalisation BAPA asynchrone
        if ($achat && $achat->etape === 'Facture' && empty($achat->fournisseur?->ncc)) {
            NormaliserAchatBapaJob::dispatch($achat);
        }

        // Journaliser la création de l'achat
        $this->journaliser('creation_achat', 'Achat', null);

        return redirect()->route('admin.achats.factures')
            ->with('succes', 'Achat enregistré et facture générée avec succès.');
    }

    public function factures(): View
    {
        $entreprise = Auth::user()->entreprise;
        $pointDeVenteId = session('point_de_vente_actif_id') ?? Auth::user()->point_de_vente_id;

        $etapeActive = request('etape', 'Facture');
        $type = request('type');

        $baseQuery = Achat::with(['fournisseur', 'pointDeVente', 'details.produit'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id));

        if ($pointDeVenteId) {
            $baseQuery->where('point_de_vente_id', $pointDeVenteId);
        }

        if ($type === 'avoir') {
            $baseQuery->where('type_facture', 'avoir');
            $etapeActive = 'Facture';
        } else {
            $baseQuery->where(function($q) {
                $q->whereNull('type_facture')->orWhere('type_facture', '!=', 'avoir');
            });
            $baseQuery->where('etape', $etapeActive);
        }

        // Calcul des totaux par étape
        $compteQuery = Achat::whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->where(function($q) {
                $q->whereNull('type_facture')->orWhere('type_facture', '!=', 'avoir');
            });
        if ($pointDeVenteId) {
            $compteQuery->where('point_de_vente_id', $pointDeVenteId);
        }
        
        $totaux = $compteQuery->select('etape', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('etape')
            ->pluck('total', 'etape')
            ->toArray();

        $nbDP = $totaux['Demande de prix'] ?? 0;
        $nbBC = $totaux['Bon de commande'] ?? 0;
        $nbFacture = $totaux['Facture'] ?? 0;

        $achats = $baseQuery->latest()->paginate(20);

        $facturesDispo = collect();
        if ($type === 'avoir') {
            $facturesDispoQuery = Achat::with('fournisseur')
                ->whereHas('pointDeVente', fn($queryPdv) => $queryPdv->where('entreprise_id', $entreprise->id))
                ->where('etape', 'Facture')
                ->where(function($queryNum) {
                    $queryNum->where('numero_facture', 'LIKE', 'AC-%')
                             ->orWhere('numero_facture', 'LIKE', 'BA-%');
                })
                ->where(function($queryType) {
                    $queryType->whereNull('type_facture')->orWhere('type_facture', '!=', 'avoir');
                });
            if ($pointDeVenteId) {
                $facturesDispoQuery->where('point_de_vente_id', $pointDeVenteId);
            }
            $facturesDispo = $facturesDispoQuery->latest()->get();
        }

        return view('admin::achats.factures', compact('achats', 'etapeActive', 'nbDP', 'nbBC', 'nbFacture', 'type', 'facturesDispo'));
    }



    public function imprimer(Achat $achat): View
    {
        $this->autoriserAcces($achat);
        $achat->load(['fournisseur', 'pointDeVente.entreprise', 'details.produit']);
        $dejaPaye = \App\Modules\Admin\Modeles\TresorerieJournal::where('reference_document', $achat->numero_facture)->sum('montant_sortie');
        return view('admin::factures.achat', compact('achat', 'dejaPaye'));
    }

    public function imprimerBapa(Achat $achat): View
    {
        $this->autoriserAcces($achat);
        $achat->load(['fournisseur', 'pointDeVente.entreprise', 'details.produit']);
        $dejaPaye = \App\Modules\Admin\Modeles\TresorerieJournal::where('reference_document', $achat->numero_facture)->sum('montant_sortie');
        return view('admin::factures.bapa', compact('achat', 'dejaPaye'));
    }

    private function autoriserAcces(Achat $achat): void
    {
        $entrepriseId = Auth::user()->entreprise_id;
        abort_unless(
            $achat->pointDeVente->entreprise_id === $entrepriseId,
            403,
            'Accès non autorisé.'
        );
    }

    public function confirmerCommande(Achat $achat): RedirectResponse
    {
        $this->autoriserAcces($achat);
        if ($achat->etape !== 'Demande de prix') {
            return back()->with('info', 'Le document n\'est pas à l\'étape Demande de prix.');
        }

        $achat->update(['etape' => 'Bon de commande']);

        return back()->with('succes', 'Commande fournisseur confirmée.');
    }

    public function facturer(Achat $achat): RedirectResponse
    {
        $this->autoriserAcces($achat);
        if ($achat->etape === 'Facture') {
            return back()->with('info', 'Cette facture est déjà validée.');
        }

        DB::transaction(function () use ($achat) {
            $nouveauStatut = ($achat->mode_paiement === 'Crédit' || str_contains($achat->mode_paiement, 'Crédit')) ? 'Crédit' : 'Payé';
            $achat->update(['etape' => 'Facture', 'statut' => $nouveauStatut]);

            // 1. Incrémenter le stock uniquement pour les articles stockables
            foreach ($achat->details as $detail) {
                $produit = $detail->produit;
                if ($produit && $produit->estStockable()) {
                    $stockAvant = $produit->stockActuel($achat->point_de_vente_id);
                    $produit->incrementStock($achat->point_de_vente_id, $detail->quantite);

                    MouvementStock::create([
                        'produit_id'         => $produit->id,
                        'point_de_vente_id'  => $achat->point_de_vente_id,
                        'type_mouvement'     => 'Entrée',
                        'quantite'           => $detail->quantite,
                        'stock_avant'        => $stockAvant,
                        'stock_apres'        => $stockAvant + $detail->quantite,
                        'reference_document' => $achat->numero_facture,
                    ]);
                }
            }

            // 2. Écriture comptable de facturation
            \App\Modules\Admin\Services\ComptabiliteService::genererEcritureFactureAchat($achat);

            // 3. Trésorerie & Écriture de règlement
            $montantPaye = $achat->montant_ttc;
            $soldeActuel = TresorerieJournal::where('point_de_vente_id', $achat->point_de_vente_id)
                ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

            TresorerieJournal::create([
                'point_de_vente_id'  => $achat->point_de_vente_id,
                'date_operation'     => $achat->date_achat->toDateString(),
                'type_operation'     => 'Décaissement',
                'libelle'            => 'Achat — Facture ' . $achat->numero_facture,
                'mode_paiement'      => $achat->mode_paiement,
                'moyen_bancaire'     => $achat->moyen_bancaire,
                'reference_paiement' => $achat->reference_paiement,
                'montant_entree'     => 0,
                'montant_sortie'     => $montantPaye,
                'solde_resultat'     => $soldeActuel - $montantPaye,
                'reference_document' => $achat->numero_facture,
            ]);

            // Écriture de règlement
            \App\Modules\Admin\Services\ComptabiliteService::genererEcritureReglementAchat(
                $achat,
                $montantPaye,
                $achat->mode_paiement,
                $achat->date_achat->toDateString(),
                $achat->moyen_bancaire,
                $achat->reference_paiement
            );
        });

        // Si le fournisseur n'a pas de NCC, normalisation BAPA asynchrone
        if (empty($achat->fournisseur?->ncc)) {
            NormaliserAchatBapaJob::dispatch($achat);
        }


        return back()->with('succes', 'Facture d\'achat validée, stock mis à jour et écritures générées.');
    }

    /**
     * Générer un avoir sur une facture d'achat (Retour fournisseur)
     */
    public function creerAvoir(Request $request, Achat $achat): RedirectResponse
    {
        $this->autoriserAcces($achat);
        abort_if($achat->type_facture === 'avoir', 400, "Impossible de générer un avoir sur un avoir.");

        $request->validate([
            'raison' => ['required', 'string', 'max:255'],
        ]);

        $avoirId = null;

        DB::transaction(function () use ($achat, $request, &$avoirId) {
            $numAvoir = 'AV-' . $achat->numero_facture;

            // 1. Création de la facture d'avoir d'achat
            $avoir = Achat::create([
                'point_de_vente_id'          => $achat->point_de_vente_id,
                'fournisseur_id'             => $achat->fournisseur_id,
                'utilisateur_id'             => Auth::id(),
                'numero_facture'             => $numAvoir,
                'numero_facture_fournisseur' => $request->raison, // Raison/Ref de l'avoir fournisseur
                'date_achat'                 => now()->toDateString(),
                'mode_paiement'              => $achat->mode_paiement,
                'moyen_bancaire'             => $achat->moyen_bancaire,
                'reference_paiement'         => $request->raison,
                'montant_ht'                 => $achat->montant_ht,
                'montant_tva'                => $achat->montant_tva,
                'montant_ttc'                => $achat->montant_ttc,
                'statut'                     => 'Payé',
                'type_facture'               => 'avoir',
                'etape'                      => 'Facture',
            ]);

            // 2. Copie des détails et retour fournisseur (décrémentation stock)
            foreach ($achat->details as $detail) {
                \App\Modules\Admin\Modeles\AchatDetail::create([
                    'achat_id'      => $avoir->id,
                    'produit_id'    => $detail->produit_id,
                    'quantite'      => $detail->quantite,
                    'prix_unitaire' => $detail->prix_unitaire,
                    'montant_tva'   => $detail->montant_tva,
                    'montant_ttc'   => $detail->montant_ttc,
                ]);

                // Décrémenter le stock si le produit est stockable
                if ($detail->produit && $detail->produit->estStockable()) {
                    $stockAvant = $detail->produit->stockActuel($achat->point_de_vente_id);
                    $detail->produit->decrementStock($achat->point_de_vente_id, $detail->quantite);

                    MouvementStock::create([
                        'produit_id'         => $detail->produit_id,
                        'point_de_vente_id'  => $achat->point_de_vente_id,
                        'type_mouvement'     => 'Sortie', // Retour fournisseur
                        'quantite'           => $detail->quantite,
                        'stock_avant'        => $stockAvant,
                        'stock_apres'        => $stockAvant - $detail->quantite,
                        'reference_document' => $numAvoir,
                    ]);
                }
            }

            // 3. Écritures comptables
            \App\Modules\Admin\Services\ComptabiliteService::genererEcritureAvoirAchat($avoir);

            // 4. Si la facture d'origine était payée en espèces, on simule l'entrée en caisse du remboursement fournisseur
            if (str_contains(strtolower($achat->mode_paiement), 'espèces') || str_contains(strtolower($achat->mode_paiement), 'caisse')) {
                $soldeActuel = TresorerieJournal::where('point_de_vente_id', $achat->point_de_vente_id)
                    ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

                TresorerieJournal::create([
                    'point_de_vente_id'  => $achat->point_de_vente_id,
                    'date_operation'     => now()->toDateString(),
                    'type_operation'     => 'Encaissement', // Remboursement du fournisseur
                    'libelle'            => 'Remboursement Avoir fournisseur ' . $numAvoir,
                    'mode_paiement'      => $achat->mode_paiement,
                    'montant_entree'     => $achat->montant_ttc,
                    'montant_sortie'     => 0,
                    'solde_resultat'     => $soldeActuel + $achat->montant_ttc,
                    'reference_document' => $numAvoir,
                ]);
            }

            $avoirId = $avoir->id;
        });

        $this->journaliser('creation_avoir_achat', 'Achat', $avoirId);

        $routeRedirect = request()->routeIs('caissier.*') ? 'caissier.achats.factures' : 'admin.achats.factures';
        return redirect()->route($routeRedirect, ['type' => 'avoir'])
            ->with('succes', "Facture d'avoir fournisseur enregistrée ! Les stocks et écritures comptables d'annulation ont été validés.");
    }

    /**
     * Lot H : Normalisation manuelle DGI/BAPA.
     * Dispatch le job de normalisation pour un achat non encore normalisé.
     */
    public function normaliser(Achat $achat): RedirectResponse
    {
        if ($achat->normalise) {
            return back()->with('info', 'Cet achat est déjà normalisé.');
        }

        if ($achat->etape !== 'Facture') {
            return back()->with('erreur', 'Seules les factures finalisées peuvent être normalisées.');
        }

        NormaliserAchatBapaJob::dispatch($achat);

        $this->journaliser('normalisation_manuelle_achat', 'Achat', $achat->id);

        return back()->with('succes', 'La normalisation BAPA/DGI a été lancée avec succès. Elle sera traitée en arrière-plan.');
    }

    public function rechercherFacturesPourAvoir(Request $request): \Illuminate\Http\JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $q = $request->query('q');

        $query = Achat::with('fournisseur')
            ->whereHas('pointDeVente', fn($queryPdv) => $queryPdv->where('entreprise_id', $entreprise->id))
            ->where('etape', 'Facture')
            ->where(function($queryNum) {
                $queryNum->where('numero_facture', 'LIKE', 'AC-%')
                         ->orWhere('numero_facture', 'LIKE', 'BA-%');
            })
            ->where(function($queryType) {
                $queryType->whereNull('type_facture')->orWhere('type_facture', '!=', 'avoir');
            })
            ->where('archived', false);

        if ($q) {
            $query->where(function($querySearch) use ($q) {
                $querySearch->where('numero_facture', 'like', "%{$q}%")
                    ->orWhere('numero_facture_fournisseur', 'like', "%{$q}%")
                    ->orWhereHas('fournisseur', fn($queryFourn) => $queryFourn->where('nom', 'like', "%{$q}%"));
            });
        }

        $factures = $query->latest()->limit(10)->get()->map(function($f) {
            $fournNom = $f->fournisseur ? $f->fournisseur->nom : 'Fournisseur inconnu';
            return [
                'id' => $f->id,
                'text' => "{$f->numero_facture} - {$fournNom} (" . number_format($f->montant_ttc, 0, ',', ' ') . " XOF)"
            ];
        });

        return response()->json($factures);
    }

    public function detailsFacturePourAvoir(Achat $achat): \Illuminate\Http\JsonResponse
    {
        $this->autoriserAcces($achat);
        $achat->load(['details.produit', 'fournisseur']);

        return response()->json([
            'id' => $achat->id,
            'numero_facture' => $achat->numero_facture,
            'fournisseur_nom' => $achat->fournisseur ? $achat->fournisseur->nom : 'Fournisseur inconnu',
            'montant_ttc' => $achat->montant_ttc,
            'details' => $achat->details->map(function($d) {
                return [
                    'id' => $d->id,
                    'produit_id' => $d->produit_id,
                    'libelle' => $d->produit ? $d->produit->nom : 'Produit inconnu',
                    'quantite' => $d->quantite,
                    'prix_unitaire' => $d->prix_unitaire,
                    'montant_tva' => $d->montant_tva,
                    'montant_ttc' => $d->montant_ttc,
                    'unite' => $d->produit ? ($d->produit->unite ?? 'pcs') : 'pcs',
                    'est_stockable' => $d->produit ? $d->produit->estStockable() : false,
                ];
            })
        ]);
    }

    public function creerAvoirNouveau(Request $request): RedirectResponse
    {
        $request->validate([
            'parent_id' => ['required', 'exists:achats,id'],
            'raison'    => ['required', 'string', 'max:255'],
            'items'     => ['required', 'array'],
        ]);

        $parent = Achat::findOrFail($request->parent_id);
        $this->autoriserAcces($parent);
        abort_if($parent->type_facture === 'avoir', 400, "Impossible de générer un avoir sur un avoir.");

        $avoirId = null;

        DB::transaction(function () use ($parent, $request, &$avoirId) {
            $numAvoir = 'AV-' . $parent->numero_facture;

            // 1. Création de la facture d'avoir d'achat
            $avoir = Achat::create([
                'point_de_vente_id'          => $parent->point_de_vente_id,
                'fournisseur_id'             => $parent->fournisseur_id,
                'utilisateur_id'             => Auth::id(),
                'numero_facture'             => $numAvoir,
                'numero_facture_fournisseur' => $request->raison,
                'date_achat'                 => now()->toDateString(),
                'mode_paiement'              => $parent->mode_paiement,
                'moyen_bancaire'             => $parent->moyen_bancaire,
                'reference_paiement'         => $request->raison,
                'statut'                     => 'Payé',
                'type_facture'               => 'avoir',
                'etape'                      => 'Facture',
                'parent_id'                  => $parent->id,
                'raison_avoir'               => $request->raison,
                'montant_ht'                 => 0,
                'montant_tva'                => 0,
                'montant_ttc'                => 0,
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

                    \App\Modules\Admin\Modeles\AchatDetail::create([
                        'achat_id'        => $avoir->id,
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

                    // Action sur stock (décrémentation stock si retour physique marchandise)
                    if ($produit && $produit->estStockable()) {
                        $stockAction = $itemData['stock_action'] ?? 'none';
                        if ($stockAction === 'reinject') {
                            $stockAvant = $produit->stockActuel($parent->point_de_vente_id);
                            $produit->decrementStock($parent->point_de_vente_id, $qteAvoir);

                            MouvementStock::create([
                                'produit_id'         => $produitId,
                                'point_de_vente_id'  => $parent->point_de_vente_id,
                                'type_mouvement'     => 'Sortie',
                                'quantite'           => $qteAvoir,
                                'stock_avant'        => $stockAvant,
                                'stock_apres'        => $stockAvant - $qteAvoir,
                                'reference_document' => $numAvoir,
                                'notes'              => 'Retour fournisseur - Sortie physique de stock (Ajouté)',
                            ]);
                        }
                    }
                } else {
                    $detail = \App\Modules\Admin\Modeles\AchatDetail::where('achat_id', $parent->id)->where('id', $itemId)->first();
                    if (!$detail) continue;

                    $tvaRate = ($detail->montant_ttc - $detail->montant_ht) > 0 ? 0.18 : 0;

                    $itemHt = $qteAvoir * $prixUnit;
                    $itemTva = $itemHt * $tvaRate;
                    $itemTtc = $itemHt + $itemTva;

                    \App\Modules\Admin\Modeles\AchatDetail::create([
                        'achat_id'      => $avoir->id,
                        'produit_id'    => $detail->produit_id,
                        'quantite'      => $qteAvoir,
                        'prix_unitaire' => $prixUnit,
                        'montant_tva'   => $itemTva,
                        'montant_ttc'   => $itemTtc,
                    ]);

                    $totalHt += $itemHt;
                    $totalTva += $itemTva;
                    $totalTtc += $itemTtc;

                    // Action sur stock (décrémentation stock si retour physique marchandise)
                    if ($detail->produit && $detail->produit->estStockable()) {
                        $stockAction = $itemData['stock_action'] ?? 'none';
                        if ($stockAction === 'reinject') {
                            $stockAvant = $detail->produit->stockActuel($parent->point_de_vente_id);
                            $detail->produit->decrementStock($parent->point_de_vente_id, $qteAvoir);

                            MouvementStock::create([
                                'produit_id'         => $detail->produit_id,
                                'point_de_vente_id'  => $parent->point_de_vente_id,
                                'type_mouvement'     => 'Sortie',
                                'quantite'           => $qteAvoir,
                                'stock_avant'        => $stockAvant,
                                'stock_apres'        => $stockAvant - $qteAvoir,
                                'reference_document' => $numAvoir,
                                'notes'              => 'Retour fournisseur - Sortie physique de stock',
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
            \App\Modules\Admin\Services\ComptabiliteService::genererEcritureAvoirAchat($avoir);

            // 4. Encaissement trésorerie si remboursement fournisseur
            if (str_contains(strtolower($parent->mode_paiement), 'espèces') || str_contains(strtolower($parent->mode_paiement), 'caisse')) {
                $soldeActuel = TresorerieJournal::where('point_de_vente_id', $parent->point_de_vente_id)
                    ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

                TresorerieJournal::create([
                    'point_de_vente_id'  => $parent->point_de_vente_id,
                    'date_operation'     => now()->toDateString(),
                    'type_operation'     => 'Encaissement',
                    'libelle'            => 'Remboursement Avoir fournisseur ' . $numAvoir,
                    'mode_paiement'      => $parent->mode_paiement,
                    'montant_entree'     => $totalTtc,
                    'montant_sortie'     => 0,
                    'solde_resultat'     => $soldeActuel + $totalTtc,
                    'reference_document' => $numAvoir,
                ]);
            }

            $avoirId = $avoir->id;
        });

        $this->journaliser('creation_avoir_achat', 'Achat', $avoirId);

        $routeRedirect = request()->routeIs('caissier.*') ? 'caissier.achats.factures' : 'admin.achats.factures';
        return redirect()->route($routeRedirect, ['type' => 'avoir'])
            ->with('succes', "Facture d'avoir fournisseur enregistrée !");
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
