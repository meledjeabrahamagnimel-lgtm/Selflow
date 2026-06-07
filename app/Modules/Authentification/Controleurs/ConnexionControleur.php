<?php

namespace App\Modules\Authentification\Controleurs;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConnexionControleur
{
    /**
     * Afficher le formulaire de connexion.
     */
    public function afficher(): View
    {
        return view('authentification::connexion');
    }

    /**
     * Traiter la tentative de connexion de manière sécurisée.
     *
     * @throws ValidationException
     */
    public function connecter(Request $request): RedirectResponse
    {
        $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required'    => 'L\'adresse email est obligatoire.',
            'email.email'       => 'L\'adresse email n\'est pas valide.',
            'password.required' => 'Le mot de passe est obligatoire.',
        ]);

        // Protection contre les attaques par force brute (5 tentatives / minute)
        $this->verifierLimiteTentatives($request);

        $identifiants = [
            'email'    => $request->email,
            'password' => $request->password,
        ];

        if (! Auth::attempt($identifiants, $request->boolean('se_souvenir'))) {
            // Incrémenter le compteur de tentatives
            RateLimiter::hit($this->cléThrottling($request));

            throw ValidationException::withMessages([
                'email' => 'Identifiants incorrects. Veuillez vérifier votre email et mot de passe.',
            ]);
        }

        // Vérifier que le compte est actif
        if (Auth::user()->statut !== 'actif') {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => 'Votre compte est désactivé. Contactez votre administrateur.',
            ]);
        }

        // Réinitialiser le compteur de tentatives après une connexion réussie
        RateLimiter::clear($this->cléThrottling($request));

        $request->session()->regenerate();

        // Rediriger selon le rôle de l'utilisateur
        $role = Auth::user()->role;

        return match ($role) {
            'superadmin' => redirect()->intended(route('superadmin.tableau_de_bord')),
            'admin'      => redirect()->intended(route('admin.tableau_de_bord')),
            'caissier'   => redirect()->intended(route('caissier.tableau_de_bord')),
            default      => redirect()->route('connexion'),
        };
    }

    /**
     * Déconnecter l'utilisateur de l'application.
     */
    public function deconnecter(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('connexion');
    }

    /**
     * Vérifier la limite de tentatives de connexion.
     *
     * @throws ValidationException
     */
    private function verifierLimiteTentatives(Request $request): void
    {
        $cle = $this->cléThrottling($request);

        if (! RateLimiter::tooManyAttempts($cle, 5)) {
            return;
        }

        $secondes = RateLimiter::availableIn($cle);

        throw ValidationException::withMessages([
            'email' => sprintf(
                'Trop de tentatives de connexion. Veuillez réessayer dans %d secondes.',
                $secondes
            ),
        ]);
    }

    /**
     * Générer une clé unique pour le throttling.
     */
    private function cléThrottling(Request $request): string
    {
        return Str::transliterate(
            Str::lower($request->input('email')) . '|' . $request->ip()
        );
    }
}
