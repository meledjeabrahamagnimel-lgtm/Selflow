<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Produit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ProduitControleur
{
    public function index(): View
    {
        $entreprise = Auth::user()->entreprise;
        $produits   = Produit::where('entreprise_id', $entreprise->id)
            ->orderBy('categorie')
            ->orderBy('nom')
            ->paginate(30);

        $comptes = \App\Modules\Admin\Modeles\PlanComptable::whereNull('entreprise_id')
            ->orWhere('entreprise_id', $entreprise->id)
            ->orderBy('numero')
            ->get();

        return view('admin::produits.index', compact('produits', 'comptes'));
    }

    public function creer(Request $request): RedirectResponse
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
            'type'          => ['required', 'string', 'in:stockable,consommable,service'],
            'categorie'     => ['nullable', 'string', 'max:100'],
            'prix_achat'    => ['required', 'numeric', 'min:0'],
            'prix_vente'    => ['required', 'numeric', 'min:0'],
            'taux_tva'      => ['required', 'numeric', 'min:0'],
            'compte_vente'  => [
                'required',
                'string',
                'max:20',
                \Illuminate\Validation\Rule::exists('plan_comptable', 'numero')->where(function ($q) use ($entreprise) {
                    $q->whereNull('entreprise_id')->orWhere('entreprise_id', $entreprise->id);
                })
            ],
            'compte_achat'  => [
                'required',
                'string',
                'max:20',
                \Illuminate\Validation\Rule::exists('plan_comptable', 'numero')->where(function ($q) use ($entreprise) {
                    $q->whereNull('entreprise_id')->orWhere('entreprise_id', $entreprise->id);
                })
            ],
            'stock_actuel'  => ['required', 'integer', 'min:0'],
            'stock_minimum' => ['required', 'integer', 'min:0'],
            'unite'         => ['nullable', 'string', 'max:20'],
        ]);

        Produit::create(array_merge(
            $request->only(['reference', 'nom', 'type', 'categorie', 'prix_achat', 'prix_vente', 'taux_tva', 'compte_vente', 'compte_achat', 'stock_actuel', 'stock_minimum', 'unite']),
            ['entreprise_id' => $entreprise->id]
        ));

        return back()->with('succes', 'Produit ajouté au catalogue avec succès.');
    }

    public function modifier(Request $request, Produit $produit): RedirectResponse
    {
        abort_unless($produit->entreprise_id === Auth::user()->entreprise_id, 403);
        $entreprise = Auth::user()->entreprise;

        $request->validate([
            'nom'           => ['required', 'string', 'max:200'],
            'type'          => ['required', 'string', 'in:stockable,consommable,service'],
            'categorie'     => ['nullable', 'string', 'max:100'],
            'prix_achat'    => ['required', 'numeric', 'min:0'],
            'prix_vente'    => ['required', 'numeric', 'min:0'],
            'taux_tva'      => ['required', 'numeric', 'min:0'],
            'compte_vente'  => [
                'required',
                'string',
                'max:20',
                \Illuminate\Validation\Rule::exists('plan_comptable', 'numero')->where(function ($q) use ($entreprise) {
                    $q->whereNull('entreprise_id')->orWhere('entreprise_id', $entreprise->id);
                })
            ],
            'compte_achat'  => [
                'required',
                'string',
                'max:20',
                \Illuminate\Validation\Rule::exists('plan_comptable', 'numero')->where(function ($q) use ($entreprise) {
                    $q->whereNull('entreprise_id')->orWhere('entreprise_id', $entreprise->id);
                })
            ],
            'stock_actuel'  => ['required', 'integer'],
            'stock_minimum' => ['required', 'integer', 'min:0'],
            'unite'         => ['nullable', 'string', 'max:20'],
        ]);

        $produit->update($request->only(['nom', 'type', 'categorie', 'prix_achat', 'prix_vente', 'taux_tva', 'compte_vente', 'compte_achat', 'stock_actuel', 'stock_minimum', 'unite']));

        return back()->with('succes', 'Produit mis à jour avec succès.');
    }
}
