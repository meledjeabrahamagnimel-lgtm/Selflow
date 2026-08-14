<?php

use App\Modules\Authentification\Controleurs\ConnexionControleur;
use Illuminate\Support\Facades\Route;

// -----------------------------------------------------------------------
// La vitrine — page de présentation publique.
//
// Hors du groupe `guest` : elle s'adresse à tout le monde, y compris à un
// utilisateur déjà connecté qui reviendrait la lire. La mettre derrière
// `guest` l'aurait renvoyé vers son tableau de bord au lieu de la lui
// montrer.
// -----------------------------------------------------------------------
Route::get('/presentation', [\App\Modules\Admin\Controleurs\VitrineControleur::class, 'accueil'])
    ->name('vitrine');

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

    // Demander un lien envoie un courriel. Sans borne, on inonde la boîte d'un
    // utilisateur, on épuise le quota du serveur d'envoi, et l'on apprend au
    // passage quelles adresses existent.
    Route::post('/mot-de-passe/oublie', [\App\Modules\Authentification\Controleurs\PasswordResetControleur::class, 'envoyerLien'])
        ->middleware('throttle:mot-de-passe')
        ->name('password.email');

    Route::get('/mot-de-passe/reinitialiser/{token}', [\App\Modules\Authentification\Controleurs\PasswordResetControleur::class, 'afficherReset'])
        ->name('password.reset');

    // Le jeton de réinitialisation est une chaîne : sans borne, il s'éprouve.
    Route::post('/mot-de-passe/reinitialiser', [\App\Modules\Authentification\Controleurs\PasswordResetControleur::class, 'reinitialiser'])
        ->middleware('throttle:mot-de-passe')
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
    if (! auth()->check()) {
        return redirect()->route('connexion');
    }

    $role = auth()->user()->role;

    return match ($role) {
        'superadmin' => redirect()->route('superadmin.tableau_de_bord'),
        'admin'      => redirect()->route('admin.tableau_de_bord'),
        'caissier'   => redirect()->route('caissier.tableau_de_bord'),

        // **Ne jamais renvoyer un utilisateur connecté vers `connexion`.**
        // C'est une route réservée aux visiteurs : elle le renvoie aussitôt
        // ici, qui le renvoie là-bas, et le navigateur s'arrête au bout de
        // vingt allers-retours sur `ERR_TOO_MANY_REDIRECTS`.
        //
        // Le modèle porte cinq rôles — `estAdminSecondaire()` et
        // `estResponsablePdv()` en plus des trois aiguillés ci-dessus — mais
        // aucune route ne les accepte : `role:admin` et `role:admin,caissier`
        // comparent à l'identique. Ces deux comptes n'avaient donc aucun
        // espace où aller, et la boucle était le seul symptôme. Un message
        // vaut mieux qu'une boucle : il dit ce qui manque au lieu de laisser
        // croire à une panne du navigateur.
        default      => abort(403, "Votre compte porte le rôle « {$role} », auquel aucun "
            . "espace de travail n'est rattaché. Demandez à votre administrateur de vous "
            . "attribuer un rôle actif."),
    };
})->name('accueil');
