<?php

namespace App\Modules\Admin\Controleurs;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class EntrepriseControleur
{
    /**
     * Afficher la page des paramètres de l'entreprise.
     */
    public function parametres(): View
    {
        $entreprise = Auth::user()->entreprise;
        return view('admin::entreprise.parametres', compact('entreprise'));
    }

    /**
     * Enregistrer les modifications des paramètres de l'entreprise.
     */
    public function enregistrerParametres(Request $request): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;

        $request->validate([
            'nom'               => ['required', 'string', 'max:150'],
            'adresse'           => ['nullable', 'string', 'max:255'],
            'telephone'         => ['nullable', 'string', 'max:30'],
            'email'             => ['nullable', 'email', 'max:150'],
            'rccm'              => ['nullable', 'string', 'max:100'],
            'compte_contribuable' => ['nullable', 'string', 'max:100'],
            'ncc'               => ['nullable', 'string', 'max:50'],
            'regime_imposition' => ['nullable', 'string', 'max:100'],
            'centre_impots'     => ['nullable', 'string', 'max:150'],
            'ref_bancaire'      => ['nullable', 'string', 'max:1000'],
            'logo'              => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'logo_fne'          => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
        ]);

        $data = $request->only([
            'nom', 'adresse', 'telephone', 'email', 'rccm',
            'compte_contribuable', 'ncc', 'regime_imposition',
            'centre_impots', 'ref_bancaire',
        ]);

        // Traitement du logo principal
        if ($request->hasFile('logo')) {
            // Supprimer l'ancien logo s'il existe
            if ($entreprise->logo_path && Storage::disk('public')->exists($entreprise->logo_path)) {
                Storage::disk('public')->delete($entreprise->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos/entreprises', 'public');
        }

        // Traitement du logo FNE / secondaire
        if ($request->hasFile('logo_fne')) {
            if ($entreprise->logo_fne_path && Storage::disk('public')->exists($entreprise->logo_fne_path)) {
                Storage::disk('public')->delete($entreprise->logo_fne_path);
            }
            $data['logo_fne_path'] = $request->file('logo_fne')->store('logos/entreprises', 'public');
        }

        $entreprise->update($data);

        return back()->with('succes', 'Paramètres de l\'entreprise mis à jour avec succès.');
    }
}
