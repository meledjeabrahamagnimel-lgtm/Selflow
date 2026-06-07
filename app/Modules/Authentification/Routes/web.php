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
});

// -----------------------------------------------------------------------
// Déconnexion (accès authentifié uniquement)
// -----------------------------------------------------------------------
Route::post('/deconnexion', [ConnexionControleur::class, 'deconnecter'])
    ->name('deconnexion')
    ->middleware('auth');

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
