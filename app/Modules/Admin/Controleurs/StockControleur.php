<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StockControleur
{
    public function index(): View
    {
        $entreprise = Auth::user()->entreprise;
        $produits   = Produit::where('entreprise_id', $entreprise->id)
            ->orderBy('categorie')
            ->orderBy('nom')
            ->get();

        $categories = $produits->pluck('categorie')->unique()->sort()->values();

        return view('admin::stock.index', compact('produits', 'categories'));
    }

    public function mouvements(): View
    {
        $entreprise  = Auth::user()->entreprise;
        $mouvements  = MouvementStock::with(['produit', 'pointDeVente'])
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->latest()
            ->paginate(30);

        return view('admin::stock.mouvements', compact('mouvements'));
    }
}
