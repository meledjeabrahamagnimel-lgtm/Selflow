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
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AchatControleur
{
    public function nouveau(): View
    {
        $entreprise  = Auth::user()->entreprise;
        $fournisseurs = Fournisseur::where('entreprise_id', $entreprise->id)->orderBy('nom')->get();
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
            'fournisseur_id' => ['required', 'integer', 'exists:fournisseurs,id'],
            'date_achat'     => ['required', 'date'],
            'mode_paiement'  => ['required', 'string'],
            'articles'       => ['required', 'array', 'min:1'],
            'articles.*.produit_id'    => ['nullable', 'integer', 'exists:produits,id'],
            'articles.*.libelle_virtuel' => ['nullable', 'string', 'max:255'],
            'articles.*.quantite'      => ['required', 'integer', 'min:1'],
            'articles.*.prix_unitaire' => ['required', 'numeric', 'min:0'],
            'articles.*.unite'         => ['nullable', 'string', 'max:50'],
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

        DB::transaction(function () use ($request, $pointDeVenteId) {
            $montantHt  = 0;
            $montantTva = 0;
            $etape = $request->input('etape', 'Facture');

            foreach ($request->articles as $article) {
                $ht          = $article['quantite'] * $article['prix_unitaire'];
                $montantHt  += $ht;
            }

            $montantTtc = $montantHt; // No VAT

            // Déterminer le mode de paiement final
            $modePaiementFinal = $request->mode_paiement;
            if ($request->mode_paiement === 'Banque' && $request->filled('banque_id')) {
                $codeJournal = CodeJournal::findOrFail($request->banque_id);
                $modePaiementFinal = 'Banque : ' . $codeJournal->intitule;
            }

            // Générer le numéro de facture d'achat
            $numero = 'AC-' . now()->format('d-m-Y') . '-' . str_pad(
                Achat::whereYear('created_at', now()->year)->count() + 1,
                4, '0', STR_PAD_LEFT
            );

            // Statut de départ de l'achat : "En attente de confirmation" par défaut
            $statutInitial = ($etape === 'Facture') ? (($request->mode_paiement === 'Crédit') ? 'Crédit' : 'Payé') : 'En attente de confirmation';

            $achat = Achat::create([
                'point_de_vente_id' => $pointDeVenteId,
                'fournisseur_id'    => $request->fournisseur_id,
                'numero_facture'    => $numero,
                'date_achat'        => $request->date_achat,
                'mode_paiement'     => $modePaiementFinal,
                'moyen_bancaire'    => $request->mode_paiement === 'Banque' ? $request->moyen_bancaire : null,
                'reference_paiement'=> $request->mode_paiement === 'Banque' ? $request->reference_paiement : null,
                'montant_ht'        => $montantHt,
                'montant_tva'       => 0, // No VAT
                'montant_ttc'       => $montantTtc,
                'statut'            => $statutInitial,
                'etape'             => $etape,
            ]);

            foreach ($request->articles as $article) {
                $produit = !empty($article['produit_id']) ? Produit::lockForUpdate()->find($article['produit_id']) : null;
                $ht      = $article['quantite'] * $article['prix_unitaire'];

                AchatDetail::create([
                    'achat_id'       => $achat->id,
                    'produit_id'     => $produit ? $produit->id : null,
                    'libelle_virtuel'=> $produit ? null : ($article['libelle_virtuel'] ?? 'Saisie libre'),
                    'quantite'       => $article['quantite'],
                    'unite'          => $article['unite'] ?? 'Unité',
                    'prix_unitaire'  => $article['prix_unitaire'],
                    'montant_tva'    => 0,
                    'montant_ttc'    => $ht,
                ]);

                // Augmenter le stock + mouvement uniquement si Facture et stockable
                if ($produit && $etape === 'Facture' && $produit->type === 'stockable') {
                    $stockAvant = $produit->stock_actuel;
                    $produit->increment('stock_actuel', $article['quantite']);

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
        });

        return redirect()->route('admin.achats.factures')
            ->with('succes', 'Achat enregistré et facture générée avec succès.');
    }

    public function factures(): View
    {
        $entreprise = Auth::user()->entreprise;
        $achats = Achat::with(['fournisseur', 'pointDeVente', 'details.produit'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->latest()
            ->paginate(20);

        return view('admin::achats.factures', compact('achats'));
    }

    public function historique(): View
    {
        $entreprise = Auth::user()->entreprise;
        $achats = Achat::with(['fournisseur', 'pointDeVente'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->latest()
            ->paginate(30);

        return view('admin::achats.historique', compact('achats'));
    }

    public function imprimer(Achat $achat): View
    {
        $this->autoriserAcces($achat);
        $achat->load(['fournisseur', 'pointDeVente.entreprise', 'details.produit']);
        $dejaPaye = \App\Modules\Admin\Modeles\TresorerieJournal::where('reference_document', $achat->numero_facture)->sum('montant_sortie');
        return view('admin::factures.achat', compact('achat', 'dejaPaye'));
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
                if ($produit && $produit->type === 'stockable') {
                    $stockAvant = $produit->stock_actuel;
                    $produit->increment('stock_actuel', $detail->quantite);

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

        return back()->with('succes', 'Facture d\'achat validée, stock mis à jour et écritures générées.');
    }
}
