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
        $produits   = Produit::where('entreprise_id', $entreprise->id)
            ->orderBy('categorie')
            ->orderBy('nom')
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
            'reference'     => [
                'required',
                'string',
                'max:50',
                \Illuminate\Validation\Rule::unique('produits', 'reference')->where('entreprise_id', $entreprise->id)
            ],
            'nom'           => ['required', 'string', 'max:200'],
            'categorie'     => ['nullable', 'string', 'max:100'],
            'prix_achat'    => ['required', 'numeric', 'min:0'],
            'prix_vente'    => ['required', 'numeric', 'min:0'],
            'stock_actuel'  => ['required', 'integer', 'min:0'],
            'stock_minimum' => ['required', 'integer', 'min:0'],
            'unite'         => ['nullable', 'string', 'max:20'],
        ]);

        $produit = Produit::create(array_merge(
            $request->only(['reference', 'nom', 'categorie', 'prix_achat', 'prix_vente', 'stock_actuel', 'stock_minimum', 'unite']),
            ['entreprise_id' => $entreprise->id]
        ));

        return response()->json([
            'statut' => 'succes',
            'message' => 'Produit ajouté au catalogue avec succès.',
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
            'categorie'     => ['nullable', 'string', 'max:100'],
            'prix_achat'    => ['required', 'numeric', 'min:0'],
            'prix_vente'    => ['required', 'numeric', 'min:0'],
            'stock_minimum' => ['required', 'integer', 'min:0'],
            'unite'         => ['nullable', 'string', 'max:20'],
        ]);

        $produit->update($request->only(['nom', 'categorie', 'prix_achat', 'prix_vente', 'stock_minimum', 'unite']));

        return response()->json([
            'statut' => 'succes',
            'message' => 'Produit mis à jour avec succès.',
            'produit' => $produit
        ]);
    }
}
