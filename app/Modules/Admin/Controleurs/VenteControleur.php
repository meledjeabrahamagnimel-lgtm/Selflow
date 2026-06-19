<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\TresorerieJournal;
use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Modeles\VenteDetail;
use App\Modules\Admin\Modeles\Banque;
use App\Modules\Admin\Modeles\CodeJournal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VenteControleur
{
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
        $clients        = Client::where('entreprise_id', $entreprise->id)->orderBy('nom')->get();
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

        DB::transaction(function () use ($request, $pointDeVenteId, &$venteId) {
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
            $numero = 'VT-' . now()->format('d-m-Y') . '-' . str_pad(
                Vente::whereYear('created_at', now()->year)->count() + 1,
                4, '0', STR_PAD_LEFT
            );

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

                VenteDetail::create([
                    'vente_id'        => $vente->id,
                    'produit_id'      => $produit ? $produit->id : null,
                    'libelle_virtuel' => $produit ? null : $nomElement,
                    'quantite'        => $article['quantite'],
                    'unite'           => $article['unite'] ?? 'Unité',
                    'prix_unitaire'   => $prix,
                    'montant_tva'     => $tva,
                    'montant_ttc'     => $ht + $tva,
                ]);

                if ($produit && $etape === 'Facture' && $produit->type === 'stockable') {
                    $stockAvant = $produit->stock_actuel;
                    $produit->decrement('stock_actuel', $article['quantite']);

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
                // Écriture de facturation
                \App\Modules\Admin\Services\ComptabiliteService::genererEcritureFactureVente($vente);

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

                    // Écriture comptable de règlement
                    \App\Modules\Admin\Services\ComptabiliteService::genererEcritureReglementVente(
                        $vente,
                        $montantPaye,
                        $modePaiementFinal,
                        now()->toDateString(),
                        $request->mode_paiement === 'Banque' ? $request->moyen_bancaire : null,
                        $request->mode_paiement === 'Banque' ? $request->reference_paiement : null
                    );
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

        // Déterminer la route de retour correcte selon le préfixe
        $routeRetour = request()->routeIs('caissier.*') ? 'caissier.ventes.imprimer' : 'admin.ventes.imprimer';

        return redirect()->route($routeRetour, $venteId)
            ->with('succes', 'Vente enregistrée ! La facture est prête.');
    }

    public function factures(): View
    {
        $entreprise = Auth::user()->entreprise;
        $pointDeVenteId = Auth::user()->estCaissier()
            ? Auth::user()->point_de_vente_id
            : session('point_de_vente_actif_id');

        $query = Vente::with(['client', 'pointDeVente', 'details.produit']);
        
        if ($pointDeVenteId) {
            $query->where('point_de_vente_id', $pointDeVenteId);
        } else {
            $query->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id));
        }

        $ventes = $query->latest()->paginate(20);

        return view('admin::ventes.factures', compact('ventes'));
    }

    public function historique(): View
    {
        $entreprise = Auth::user()->entreprise;
        $pointDeVenteId = Auth::user()->estCaissier()
            ? Auth::user()->point_de_vente_id
            : session('point_de_vente_actif_id');

        $query = Vente::with(['client', 'pointDeVente']);
        
        if ($pointDeVenteId) {
            $query->where('point_de_vente_id', $pointDeVenteId);
        } else {
            $query->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id));
        }

        $ventes = $query->latest()->paginate(30);

        return view('admin::ventes.historique', compact('ventes'));
    }

    public function imprimer(Vente $vente): View
    {
        abort_unless(
            $vente->pointDeVente->entreprise_id === Auth::user()->entreprise_id,
            403
        );
        $vente->load(['client', 'pointDeVente.entreprise', 'details.produit']);
        $vendeur = Auth::user();
        $dejaPaye = \App\Modules\Admin\Modeles\TresorerieJournal::where('reference_document', $vente->numero_facture)->sum('montant_entree');
        return view('admin::factures.vente', compact('vente', 'vendeur', 'dejaPaye'));
    }

    /**
     * Simuler la normalisation DGI d'une facture.
     */
    public function normaliser(Vente $vente): \Illuminate\Http\RedirectResponse
    {
        abort_unless(
            $vente->pointDeVente->entreprise_id === Auth::user()->entreprise_id,
            403
        );

        if ($vente->normalise) {
            return back()->with('info', 'Cette facture est d\'jà normalisée.');
        }

        // Simulation normalisation DGI — générer un code unique
        $qrData = implode('|', [
            $vente->numero_facture,
            now()->format('YmdHis'),
            'DGI-CI',
            strtoupper(substr(md5($vente->id . $vente->numero_facture . now()->timestamp), 0, 12)),
        ]);

        $vente->update([
            'normalise'     => true,
            'type_facture'  => 'normale',
            'qr_code_data'  => $qrData,
        ]);

        return redirect()->route(
            request()->routeIs('caissier.*') ? 'caissier.ventes.factures' : 'admin.ventes.factures'
        )->with('succes', 'Facture ' . $vente->numero_facture . ' normalisée avec succès.');
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

        $entreprise = Auth::user()->entreprise;
        $clients    = Client::where('entreprise_id', $entreprise->id)->orderBy('nom')->get();
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

            // 1. Restituer les stocks anciens
            $oldDetails = VenteDetail::where('vente_id', $vente->id)->with('produit')->get();
            foreach ($oldDetails as $oldDetail) {
                if ($oldDetail->produit) {
                    $oldDetail->produit->increment('stock_actuel', $oldDetail->quantite);
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

                if ($produit) {
                    $stockAvant = $produit->stock_actuel;
                    $produit->decrement('stock_actuel', $article['quantite']);

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
            $vente->update(['etape' => 'Facture']);

            // 1. Décrémenter le stock uniquement pour les articles stockables
            foreach ($vente->details as $detail) {
                $produit = $detail->produit;
                if ($produit && $produit->type === 'stockable') {
                    $stockAvant = $produit->stock_actuel;
                    $produit->decrement('stock_actuel', $detail->quantite);

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

            // 2. Générer l'écriture comptable de facturation
            \App\Modules\Admin\Services\ComptabiliteService::genererEcritureFactureVente($vente);

            // 3. Trésorerie et Écriture de règlement si payé
            // 3. Trésorerie et Écriture de règlement si payé
            if ($vente->statut !== 'Crédit') {
                $dejaPaye = TresorerieJournal::where('reference_document', $vente->numero_facture)->sum('montant_entree');
                $resteAPayer = max(0, $vente->montant_ttc - $dejaPaye);

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

                    // Écriture de règlement
                    \App\Modules\Admin\Services\ComptabiliteService::genererEcritureReglementVente(
                        $vente,
                        $resteAPayer,
                        $vente->mode_paiement,
                        now()->toDateString(),
                        $vente->moyen_bancaire,
                        $vente->reference_paiement
                    );
                }
            }
        });

        return back()->with('succes', 'Facture validée, stock mis à jour et écritures comptables générées.');
    }
}
