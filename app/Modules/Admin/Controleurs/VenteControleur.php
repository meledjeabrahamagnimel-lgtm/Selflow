<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\TresorerieJournal;
use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Modeles\VenteDetail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VenteControleur
{
    public function nouvelle(): View
    {
        $entreprise     = Auth::user()->entreprise;
        $pointDeVenteId = session('point_de_vente_actif_id');
        $clients        = Client::where('entreprise_id', $entreprise->id)->orderBy('nom')->get();
        $produits       = Produit::where('entreprise_id', $entreprise->id)
            ->where('stock_actuel', '>', 0)
            ->orderBy('nom')
            ->get();

        $categories = $produits->pluck('categorie')->unique()->sort()->values();

        return view('admin::ventes.nouvelle', compact('clients', 'produits', 'categories', 'pointDeVenteId'));
    }

    public function enregistrer(Request $request): RedirectResponse
    {
        $pointDeVenteId = session('point_de_vente_actif_id');

        $request->validate([
            'client_id'    => ['nullable', 'integer', 'exists:clients,id'],
            'mode_paiement'=> ['required', 'string'],
            'articles'     => ['required', 'array', 'min:1'],
            'articles.*.produit_id' => ['required', 'integer', 'exists:produits,id'],
            'articles.*.quantite'   => ['required', 'integer', 'min:1'],
        ], [
            'articles.required' => 'Veuillez ajouter au moins un article au panier.',
        ]);

        if (! $pointDeVenteId) {
            return back()->withErrors(['general' => 'Aucun point de vente actif.']);
        }

        $venteId = null;

        DB::transaction(function () use ($request, $pointDeVenteId, &$venteId) {
            $montantHt  = 0;
            $montantTva = 0;

            // Précalcul et vérification des stocks
            foreach ($request->articles as $article) {
                $produit = Produit::lockForUpdate()->findOrFail($article['produit_id']);
                if ($produit->stock_actuel < $article['quantite']) {
                    throw new \Exception("Stock insuffisant pour {$produit->nom}.");
                }
                $ht          = $article['quantite'] * $produit->prix_vente;
                $tva         = $ht * 0.18;
                $montantHt  += $ht;
                $montantTva += $tva;
            }

            $montantTtc = $montantHt + $montantTva;

            // Génération numéro de facture
            $numero = 'VT-' . now()->year . '-' . str_pad(
                Vente::whereYear('created_at', now()->year)->count() + 1,
                4, '0', STR_PAD_LEFT
            );

            $vente = Vente::create([
                'point_de_vente_id' => $pointDeVenteId,
                'client_id'         => $request->client_id ?: null,
                'numero_facture'    => $numero,
                'date_vente'        => now()->toDateString(),
                'mode_paiement'     => $request->mode_paiement,
                'montant_ht'        => $montantHt,
                'montant_tva'       => $montantTva,
                'montant_ttc'       => $montantTtc,
                'statut'            => 'Payé',
            ]);

            foreach ($request->articles as $article) {
                $produit = Produit::lockForUpdate()->findOrFail($article['produit_id']);
                $ht      = $article['quantite'] * $produit->prix_vente;
                $tva     = $ht * 0.18;

                VenteDetail::create([
                    'vente_id'      => $vente->id,
                    'produit_id'    => $produit->id,
                    'quantite'      => $article['quantite'],
                    'prix_unitaire' => $produit->prix_vente,
                    'montant_tva'   => $tva,
                    'montant_ttc'   => $ht + $tva,
                ]);

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

            // Encaissement automatique en trésorerie
            $soldeActuel = TresorerieJournal::where('point_de_vente_id', $pointDeVenteId)
                ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

            TresorerieJournal::create([
                'point_de_vente_id'  => $pointDeVenteId,
                'date_operation'     => now()->toDateString(),
                'type_operation'     => 'Encaissement',
                'libelle'            => 'Vente — Facture ' . $numero,
                'mode_paiement'      => $request->mode_paiement,
                'montant_entree'     => $montantTtc,
                'montant_sortie'     => 0,
                'solde_resultat'     => $soldeActuel + $montantTtc,
                'reference_document' => $numero,
            ]);

            $venteId = $vente->id;
        });

        return redirect()->route('admin.ventes.imprimer', $venteId)
            ->with('succes', 'Vente enregistrée ! La facture est prête.');
    }

    public function factures(): View
    {
        $entreprise = Auth::user()->entreprise;
        $ventes = Vente::with(['client', 'pointDeVente', 'details.produit'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->latest()
            ->paginate(20);

        return view('admin::ventes.factures', compact('ventes'));
    }

    public function historique(): View
    {
        $entreprise = Auth::user()->entreprise;
        $ventes = Vente::with(['client', 'pointDeVente'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->latest()
            ->paginate(30);

        return view('admin::ventes.historique', compact('ventes'));
    }

    public function imprimer(Vente $vente): View
    {
        abort_unless(
            $vente->pointDeVente->entreprise_id === Auth::user()->entreprise_id,
            403
        );
        $vente->load(['client', 'pointDeVente.entreprise', 'details.produit']);
        return view('admin::factures.vente', compact('vente'));
    }
}
