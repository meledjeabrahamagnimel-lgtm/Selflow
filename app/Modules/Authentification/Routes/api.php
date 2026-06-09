<?php

use App\Modules\Authentification\Controleurs\Api\ConnexionApiControleur;
use Illuminate\Support\Facades\Route;

// Routes publiques
Route::post('/connexion', [ConnexionApiControleur::class, 'connecter']);

// Routes protégées par jeton API
Route::middleware('auth.api')->group(function () {
    Route::post('/deconnexion', [ConnexionApiControleur::class, 'deconnecter']);
});
