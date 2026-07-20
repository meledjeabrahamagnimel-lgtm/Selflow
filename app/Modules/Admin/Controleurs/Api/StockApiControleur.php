<?php

namespace App\Modules\Admin\Controleurs\Api;

use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\Produit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StockApiControleur
{
    public function index(): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $produits   = Produit::where('produits.entreprise_id', $entreprise->id)
            ->with(['stocks'])
            ->leftJoin('categories', 'produits.categorie_id', '=', 'categories.id')
            ->select('produits.*')
            ->orderBy('categories.nom')
            ->orderBy('produits.nom')
            ->get()
            ->map(function ($p) {
                return [
                    'produit_id' => $p->id,
                    'nom' => $p->nom,
                    'categorie' => $p->categorie,
                    'stock_actuel' => $p->stock_actuel,
                    'stock_minimum' => $p->stock_minimum,
                    'etat' => $p->etatStock()
                ];
            });

        return response()->json([
            'statut' => 'succes',
            'stock' => $produits
        ]);
    }

    public function mouvements(): JsonResponse
    {
        $entreprise  = Auth::user()->entreprise;
        $mouvements  = MouvementStock::with(['produit', 'pointDeVente'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->latest()
            ->paginate(30);

        return response()->json([
            'statut' => 'succes',
            'mouvements' => $mouvements
        ]);
    }
}
