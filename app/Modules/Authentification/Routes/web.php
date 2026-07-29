<?php

use App\Modules\Authentification\Controleurs\ConnexionControleur;
use Illuminate\Support\Facades\Route;

// -----------------------------------------------------------------------
// Routes publiques (accès sans authentification)
// -----------------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/connexion', [ConnexionControleur::class, 'afficher'])
        ->name('connexion');

    Route::post('/connexion', [ConnexionControleur::class, 'connecter'])
        ->name('connexion.traitement');

    // ── Inscription ──
    Route::get('/inscription', [\App\Modules\Authentification\Controleurs\InscriptionControleur::class, 'afficher'])
        ->name('inscription');

    Route::post('/inscription', [\App\Modules\Authentification\Controleurs\InscriptionControleur::class, 'inscrire'])
        ->name('inscription.traitement');

    // ── Google OAuth (Socialite) ──
    // Redirection vers Google
    Route::get('/auth/google', [\App\Modules\Authentification\Controleurs\GoogleAuthControleur::class, 'rediriger'])
        ->name('auth.google');

    // Callback local dev : http://127.0.0.1:8003/auth/google/callback
    Route::get('/auth/google/callback', [\App\Modules\Authentification\Controleurs\GoogleAuthControleur::class, 'callback'])
        ->name('auth.google.callback');

    // Callback production : https://selflow.dc-knowing.com/auth/callback
    Route::get('/auth/callback', [\App\Modules\Authentification\Controleurs\GoogleAuthControleur::class, 'callback'])
        ->name('auth.callback');

    // Page de contact & documentation d'informations DC-KNOWING
    Route::get('/contact', [ConnexionControleur::class, 'contact'])
        ->name('contact.info');

    // Mot de passe oublié
    Route::get('/mot-de-passe/oublie', [\App\Modules\Authentification\Controleurs\PasswordResetControleur::class, 'afficherDemande'])
        ->name('password.request');

    Route::post('/mot-de-passe/oublie', [\App\Modules\Authentification\Controleurs\PasswordResetControleur::class, 'envoyerLien'])
        ->name('password.email');

    Route::get('/mot-de-passe/reinitialiser/{token}', [\App\Modules\Authentification\Controleurs\PasswordResetControleur::class, 'afficherReset'])
        ->name('password.reset');

    Route::post('/mot-de-passe/reinitialiser', [\App\Modules\Authentification\Controleurs\PasswordResetControleur::class, 'reinitialiser'])
        ->name('password.update');
});

// -----------------------------------------------------------------------
// Déconnexion (accès authentifié uniquement)
// -----------------------------------------------------------------------
Route::post('/deconnexion', [ConnexionControleur::class, 'deconnecter'])
    ->name('deconnexion')
    ->middleware('auth');

// -----------------------------------------------------------------------
// Changement de mot de passe obligatoire (accès authentifié uniquement)
// -----------------------------------------------------------------------
Route::middleware('auth')->group(function () {
    Route::get('/changer-mot-de-passe', [\App\Modules\Authentification\Controleurs\ChangementMotDePasseControleur::class, 'afficher'])
        ->name('password.changer.afficher');

    Route::post('/changer-mot-de-passe', [\App\Modules\Authentification\Controleurs\ChangementMotDePasseControleur::class, 'traiter'])
        ->name('password.changer.traiter');
});

// -----------------------------------------------------------------------
// Redirection de la racine vers connexion ou tableau de bord
// -----------------------------------------------------------------------
Route::get('/', function () {
    if (auth()->check()) {
        $role = auth()->user()->role;
        return match ($role) {
            'superadmin' => redirect()->route('superadmin.tableau_de_bord'),
            'admin'      => redirect()->route('admin.tableau_de_bord'),
            'caissier'   => redirect()->route('caissier.tableau_de_bord'),
            default      => redirect()->route('connexion'),
        };
    }
    return redirect()->route('connexion');
})->name('accueil');
