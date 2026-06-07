<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Fournisseur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FournisseurControleur
{
    public function index(): View
    {
        $entreprise   = Auth::user()->entreprise;
        $fournisseurs = Fournisseur::where('entreprise_id', $entreprise->id)
            ->withCount('achats')
            ->orderBy('nom')
            ->paginate(30);

        return view('admin::fournisseurs.index', compact('fournisseurs'));
    }

    public function creer(Request $request): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;

        $request->validate([
            'nom'       => ['required', 'string', 'max:150'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email'     => ['nullable', 'email', 'max:150'],
            'adresse'   => ['nullable', 'string', 'max:255'],
            'secteur'   => ['nullable', 'string', 'max:100'],
        ]);

        Fournisseur::create(array_merge(
            $request->only(['nom', 'telephone', 'email', 'adresse', 'secteur']),
            ['entreprise_id' => $entreprise->id]
        ));

        return back()->with('succes', 'Fournisseur ajouté avec succès.');
    }
}
