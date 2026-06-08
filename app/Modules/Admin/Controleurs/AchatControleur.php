<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\AchatDetail;
use App\Modules\Admin\Modeles\Fournisseur;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\TresorerieJournal;
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

        return view('admin::achats.nouveau', compact('fournisseurs', 'produits', 'pointDeVenteId'));
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
            'articles.*.produit_id'    => ['required', 'integer', 'exists:produits,id'],
            'articles.*.quantite'      => ['required', 'integer', 'min:1'],
            'articles.*.prix_unitaire' => ['required', 'numeric', 'min:0'],
        ], [
            'fournisseur_id.required' => 'Veuillez sélectionner un fournisseur.',
            'articles.required'       => 'Veuillez ajouter au moins un article.',
        ]);



        DB::transaction(function () use ($request, $pointDeVenteId) {
            $montantHt  = 0;
            $montantTva = 0;

            foreach ($request->articles as $article) {
                $ht          = $article['quantite'] * $article['prix_unitaire'];
                $tva         = $ht * 0.18;
                $montantHt  += $ht;
                $montantTva += $tva;
            }

            $montantTtc = $montantHt + $montantTva;

            // Générer le numéro de facture d'achat
            $numero = 'AC-' . now()->year . '-' . str_pad(
                Achat::whereYear('created_at', now()->year)->count() + 1,
                4, '0', STR_PAD_LEFT
            );

            $achat = Achat::create([
                'point_de_vente_id' => $pointDeVenteId,
                'fournisseur_id'    => $request->fournisseur_id,
                'numero_facture'    => $numero,
                'date_achat'        => $request->date_achat,
                'mode_paiement'     => $request->mode_paiement,
                'montant_ht'        => $montantHt,
                'montant_tva'       => $montantTva,
                'montant_ttc'       => $montantTtc,
                'statut'            => 'Payé',
            ]);

            foreach ($request->articles as $article) {
                $produit = Produit::lockForUpdate()->find($article['produit_id']);
                $ht      = $article['quantite'] * $article['prix_unitaire'];
                $tva     = $ht * 0.18;

                AchatDetail::create([
                    'achat_id'       => $achat->id,
                    'produit_id'     => $produit->id,
                    'quantite'       => $article['quantite'],
                    'prix_unitaire'  => $article['prix_unitaire'],
                    'montant_tva'    => $tva,
                    'montant_ttc'    => $ht + $tva,
                ]);

                // Augmenter le stock + mouvement
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

            // Enregistrement automatique en décaissement (trésorerie)
            $soldeActuel = TresorerieJournal::where('point_de_vente_id', $pointDeVenteId)
                ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

            TresorerieJournal::create([
                'point_de_vente_id'  => $pointDeVenteId,
                'date_operation'     => $request->date_achat,
                'type_operation'     => 'Décaissement',
                'libelle'            => 'Achat — Facture ' . $numero,
                'mode_paiement'      => $request->mode_paiement,
                'montant_entree'     => 0,
                'montant_sortie'     => $montantTtc,
                'solde_resultat'     => $soldeActuel - $montantTtc,
                'reference_document' => $numero,
            ]);
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
        return view('admin::factures.achat', compact('achat'));
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
}
