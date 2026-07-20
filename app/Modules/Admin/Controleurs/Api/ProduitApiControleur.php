<?php

namespace App\Modules\Admin\Controleurs\Api;

use App\Modules\Admin\Modeles\Produit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProduitApiControleur
{
    public function index(): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $produits   = Produit::where('produits.entreprise_id', $entreprise->id)
            ->leftJoin('categories', 'produits.categorie_id', '=', 'categories.id')
            ->select('produits.*')
            ->orderBy('categories.nom')
            ->orderBy('produits.nom')
            ->paginate(30);

        return response()->json([
            'statut' => 'succes',
            'produits' => $produits
        ]);
    }

    public function creer(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;

        $request->validate([
            'nom'           => ['required', 'string', 'max:200'],
            'categorie_id'  => ['nullable', 'integer', 'exists:categories,id'],
            'sous_categorie_id' => ['nullable', 'integer', 'exists:sous_categories,id'],
            'prix_achat'    => ['required', 'numeric', 'min:0'],
            'prix_vente'    => ['required', 'numeric', 'min:0'],
            'stock_actuel'  => ['required', 'integer', 'min:0'],
            'stock_minimum' => ['required', 'integer', 'min:0'],
            'unite'         => ['nullable', 'string', 'max:20'],
        ]);

        $produit = Produit::create(array_merge(
            $request->only(['nom', 'categorie_id', 'sous_categorie_id', 'prix_achat', 'prix_vente', 'unite']),
            ['entreprise_id' => $entreprise->id]
        ));

        // Initialisation automatique des stocks par point de vente
        $pdvs = $entreprise->pointsDeVente;
        $defaultPdvId = session('point_de_vente_actif_id') 
            ?? auth()->user()->point_de_vente_id 
            ?? ($pdvs->first()->id ?? null);

        foreach ($pdvs as $pdv) {
            $isDefault = ($pdv->id == $defaultPdvId);
            \App\Modules\Admin\Modeles\Stock::create([
                'produit_id'          => $produit->id,
                'point_de_vente_id'   => $pdv->id,
                'quantite_disponible' => $isDefault ? $request->input('stock_actuel', 0) : 0,
                'stock_minimum'       => $isDefault ? $request->input('stock_minimum', 5) : 5,
                'stock_maximum'       => 100,
            ]);
        }

        return response()->json([
            'statut' => 'succes',
            'message' => 'Produit ajouté au catalogue avec succès. Référence : ' . $produit->reference,
            'produit' => $produit
        ], 201);
    }

    public function modifier(Request $request, Produit $produit): JsonResponse
    {
        if ($produit->entreprise_id !== Auth::user()->entreprise_id) {
            return response()->json([
                'statut' => 'erreur',
                'message' => 'Accès non autorisé.'
            ], 403);
        }

        $request->validate([
            'nom'           => ['required', 'string', 'max:200'],
            'categorie_id'  => ['nullable', 'integer', 'exists:categories,id'],
            'sous_categorie_id' => ['nullable', 'integer', 'exists:sous_categories,id'],
            'prix_achat'    => ['required', 'numeric', 'min:0'],
            'prix_vente'    => ['required', 'numeric', 'min:0'],
            'stock_minimum' => ['required', 'integer', 'min:0'],
            'unite'         => ['nullable', 'string', 'max:20'],
        ]);

        $produit->update($request->only(['nom', 'categorie_id', 'sous_categorie_id', 'prix_achat', 'prix_vente', 'unite']));

        $pdvId = session('point_de_vente_actif_id') 
            ?? auth()->user()->point_de_vente_id 
            ?? (Auth::user()->entreprise->pointsDeVente()->first()->id ?? null);

        if ($pdvId) {
            \App\Modules\Admin\Modeles\Stock::updateOrCreate([
                'produit_id'        => $produit->id,
                'point_de_vente_id' => $pdvId,
            ], [
                'stock_minimum' => $request->input('stock_minimum', 5),
            ]);
        }

        return response()->json([
            'statut' => 'succes',
            'message' => 'Produit mis à jour avec succès.',
            'produit' => $produit
        ]);
    }
}
