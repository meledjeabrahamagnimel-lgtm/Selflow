<?php

namespace App\Modules\Authentification\Controleurs;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class ChangementMotDePasseControleur extends Controller
{
    /**
     * Afficher le formulaire de changement de mot de passe obligatoire.
     */
    public function afficher(): View
    {
        return view('authentification::changer_mot_de_passe');
    }

    /**
     * Traiter le changement de mot de passe obligatoire.
     */
    public function traiter(Request $request): RedirectResponse
    {
        $request->validate([
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'password.required'     => 'Le nouveau mot de passe est obligatoire.',
            'password.min'          => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed'    => 'Les deux mots de passe ne correspondent pas.',
        ]);

        $utilisateur = auth()->user();

        // Vérifier que le nouveau mot de passe est différent du mot de passe provisoire
        // (optionnel mais recommandé pour vraiment forcer un changement)

        // Mettre à jour le mot de passe et désactiver le flag
        $utilisateur->update([
            'password'              => Hash::make($request->password),
            'doit_changer_password' => false,
        ]);

        return redirect()->intended(
            match ($utilisateur->role) {
                'superadmin' => route('superadmin.tableau_de_bord'),
                'admin'      => route('admin.tableau_de_bord'),
                'caissier'   => route('caissier.tableau_de_bord'),
                default      => route('connexion'),
            }
        )->with('succes', 'Votre mot de passe a été mis à jour avec succès !');
    }
}
