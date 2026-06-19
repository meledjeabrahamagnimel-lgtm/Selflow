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
        $periodes = \App\Modules\Admin\Modeles\Periode::where('entreprise_id', $entreprise->id)
            ->orderBy('date_debut', 'desc')
            ->get();
        return view('admin::entreprise.parametres', compact('entreprise', 'periodes'));
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

    /**
     * Changer de période active.
     */
    public function switchPeriode(Request $request): RedirectResponse
    {
        $request->validate([
            'periode_id' => ['required', 'integer', 'exists:periodes,id'],
        ]);

        $entreprise = Auth::user()->entreprise;
        $periode = \App\Modules\Admin\Modeles\Periode::where('entreprise_id', $entreprise->id)
            ->findOrFail($request->periode_id);

        session([
            'active_periode_id'    => $periode->id,
            'active_periode_nom'   => $periode->nom,
            'active_periode_debut' => $periode->date_debut instanceof \Carbon\Carbon ? $periode->date_debut->toDateString() : (is_string($periode->date_debut) ? $periode->date_debut : \Carbon\Carbon::parse($periode->date_debut)->toDateString()),
            'active_periode_fin'   => $periode->date_fin instanceof \Carbon\Carbon ? $periode->date_fin->toDateString() : (is_string($periode->date_fin) ? $periode->date_fin : \Carbon\Carbon::parse($periode->date_fin)->toDateString()),
        ]);

        return back()->with('succes', "Exercice basculé sur {$periode->nom}.");
    }

    /**
     * Créer manuellement une période.
     */
    public function creerPeriode(Request $request): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;

        $request->validate([
            'date_debut' => ['required', 'date'],
            'date_fin'   => ['required', 'date', 'after_or_equal:date_debut'],
        ], [
            'date_debut.required' => 'La date de début est requise.',
            'date_fin.required'   => 'La date de fin est requise.',
            'date_fin.after_or_equal' => 'La date de fin doit être après ou égale à la date de début.',
        ]);

        $year = date('Y', strtotime($request->date_debut));
        $nom = "Exercice " . $year;

        // Si c'est déjà utilisé, on peut l'appeler Période Année ou Exercice Année
        // Par exemple: Exercice 2026
        // Créer la période
        \App\Modules\Admin\Modeles\Periode::create([
            'entreprise_id' => $entreprise->id,
            'nom'           => $nom,
            'date_debut'    => $request->date_debut,
            'date_fin'      => $request->date_fin,
            'est_active'    => false,
        ]);

        return back()->with('succes', "La période « {$nom} » a été créée avec succès.");
    }
}
