<?php

namespace App\Modules\Admin\Controleurs\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class EntrepriseApiControleur
{
    /**
     * Obtenir les paramètres de l'entreprise.
     */
    public function parametres(): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;

        return response()->json([
            'statut' => 'succes',
            'donnees' => [
                'nom' => $entreprise->nom,
                'adresse' => $entreprise->adresse,
                'telephone' => $entreprise->telephone,
                'email' => $entreprise->email,
                'rccm' => $entreprise->rccm,
                'compte_contribuable' => $entreprise->compte_contribuable,
                'ncc' => $entreprise->ncc,
                'regime_imposition' => $entreprise->regime_imposition,
                'centre_impots' => $entreprise->centre_impots,
                'ref_bancaire' => $entreprise->ref_bancaire,
                'logo_path' => $entreprise->logo_path ? asset('storage/' . $entreprise->logo_path) : null,
                'logo_fne_path' => $entreprise->logo_fne_path ? asset('storage/' . $entreprise->logo_fne_path) : null,
                'quota_points_de_vente' => $entreprise->quota_points_de_vente,
                'plan_abonnement' => $entreprise->plan_abonnement
            ]
        ]);
    }

    /**
     * Enregistrer les modifications des paramètres.
     */
    public function enregistrerParametres(Request $request): JsonResponse
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

        return response()->json([
            'statut' => 'succes',
            'message' => 'Paramètres de l\'entreprise mis à jour avec succès.'
        ]);
    }
}
