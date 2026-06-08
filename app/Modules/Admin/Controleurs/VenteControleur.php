<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\TresorerieJournal;
use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Modeles\VenteDetail;
use App\Modules\Admin\Modeles\Banque;
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
        $clients        = Client::where('entreprise_id', $entreprise->id)->orderBy('nom')->get();
        $produits       = Produit::where('entreprise_id', $entreprise->id)
            ->orderBy('nom')
            ->get();

        $categories = $produits->pluck('categorie')->unique()->sort()->values();
        $banques    = Banque::where('entreprise_id', $entreprise->id)->orderBy('nom')->get();

        return view('admin::ventes.nouvelle', compact('clients', 'produits', 'categories', 'pointDeVenteId', 'banques'));
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
            'client_id'      => ['nullable', 'integer', 'exists:clients,id'],
            'mode_paiement'  => ['required', 'string'],
            'articles'       => ['required', 'array', 'min:1'],
            'articles.*.produit_id'      => ['nullable', 'integer', 'exists:produits,id'],
            'articles.*.libelle_virtuel' => ['nullable', 'string', 'max:255'],
            'articles.*.quantite'        => ['required', 'integer', 'min:1'],
            'articles.*.prix_unitaire'   => ['nullable', 'numeric', 'min:0'],
        ], [
            'articles.required' => 'Veuillez ajouter au moins un article au panier.',
        ]);

        if ($request->mode_paiement === 'Banque') {
            $request->validate([
                'banque_id' => ['required', 'integer', 'exists:banques,id'],
            ]);
        }

        $venteId = null;

        DB::transaction(function () use ($request, $pointDeVenteId, &$venteId) {
            $montantHt  = 0;
            $montantTva = 0;
            $tvaActive = $request->boolean('tva_active', false);

            // Précalcul des montants
            foreach ($request->articles as $article) {
                if (!empty($article['produit_id'])) {
                    $produit = Produit::findOrFail($article['produit_id']);
                    $prix = $produit->prix_vente;
                } else {
                    $prix = floatval($article['prix_unitaire'] ?? 0);
                }

                $ht = $article['quantite'] * $prix;
                $tva = $tvaActive ? ($ht * 0.18) : 0;
                $montantHt  += $ht;
                $montantTva += $tva;
            }

            $montantTtc = $montantHt + $montantTva;

            // Déterminer la valeur finale du mode de paiement pour l'enregistrement
            $modePaiementFinal = $request->mode_paiement;
            if ($request->mode_paiement === 'Banque' && $request->filled('banque_id')) {
                $banque = Banque::findOrFail($request->banque_id);
                $modePaiementFinal = 'Banque : ' . $banque->nom;
            }

            // Génération numéro de facture
            $numero = 'VT-' . now()->year . '-' . str_pad(
                Vente::whereYear('created_at', now()->year)->count() + 1,
                4, '0', STR_PAD_LEFT
            );

            // Statut de la vente
            $statutVente = $request->mode_paiement === 'Crédit' ? 'A crédit' : 'Payé';

            $vente = Vente::create([
                'point_de_vente_id' => $pointDeVenteId,
                'client_id'         => $request->client_id ?: null,
                'numero_facture'    => $numero,
                'date_vente'        => now()->toDateString(),
                'mode_paiement'     => $modePaiementFinal,
                'montant_ht'        => $montantHt,
                'montant_tva'       => $montantTva,
                'montant_ttc'       => $montantTtc,
                'statut'            => $statutVente,
            ]);

            foreach ($request->articles as $article) {
                if (!empty($article['produit_id'])) {
                    $produit = Produit::lockForUpdate()->findOrFail($article['produit_id']);
                    $prix = $produit->prix_vente;
                    $nomElement = $produit->nom;
                } else {
                    $produit = null;
                    $prix = floatval($article['prix_unitaire'] ?? 0);
                    $nomElement = $article['libelle_virtuel'] ?? 'Saisie libre';
                }

                $ht = $article['quantite'] * $prix;
                $tva = $tvaActive ? ($ht * 0.18) : 0;

                VenteDetail::create([
                    'vente_id'        => $vente->id,
                    'produit_id'      => $produit ? $produit->id : null,
                    'libelle_virtuel' => $produit ? null : $nomElement,
                    'quantite'        => $article['quantite'],
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
                        'reference_document' => $numero,
                    ]);
                }
            }

            // Encaissement automatique en trésorerie uniquement si réglé (Espèces ou Banque)
            if ($request->mode_paiement !== 'Crédit') {
                $montantEntree = $request->filled('montant_paye') ? floatval($request->montant_paye) : $montantTtc;

                $soldeActuel = TresorerieJournal::where('point_de_vente_id', $pointDeVenteId)
                    ->orderByDesc('created_at')->value('solde_resultat') ?? 0;

                TresorerieJournal::create([
                    'point_de_vente_id'  => $pointDeVenteId,
                    'date_operation'     => now()->toDateString(),
                    'type_operation'     => 'Encaissement',
                    'libelle'            => 'Vente — Facture ' . $numero,
                    'mode_paiement'      => $modePaiementFinal,
                    'montant_entree'     => $montantEntree,
                    'montant_sortie'     => 0,
                    'solde_resultat'     => $soldeActuel + $montantEntree,
                    'reference_document' => $numero,
                ]);
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
