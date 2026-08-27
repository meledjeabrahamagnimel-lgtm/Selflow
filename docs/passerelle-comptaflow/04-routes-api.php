<?php

/**
 * COMPTAFLOW — lignes à ajouter à routes/api.php.
 *
 * L'ordre des middlewares compte : `cle.entreprise` doit s'exécuter **avant**
 * le contrôleur, pour que `$request->attributes->get('entreprise_liee')` soit
 * posé quand celui-ci s'exécute.
 *
 * `provision` n'a pas ce middleware — il n'y a pas encore de clé à présenter.
 * C'est le seul appel de la passerelle dans ce cas, et c'est pour cela qu'il
 * doit rester derrière une limitation de débit serrée : c'est lui qui crée
 * des dossiers.
 *
 * // À VÉRIFIER : l'enregistrement de l'alias `cle.entreprise` dans
 * // bootstrap/app.php (Laravel 11+) ou app/Http/Kernel.php (Laravel 10).
 */

use App\Http\Controllers\Api\ExternalCompanyController;
use Illuminate\Support\Facades\Route;

Route::prefix('external')->group(function () {

    // ── Le dossier ──────────────────────────────────────────────────
    //
    // Six par minute : ouvrir un dossier est un geste rare, déclenché à la
    // main par un superadministrateur. Un martèlement sur cette route est
    // soit une erreur de boucle, soit une tentative.
    Route::post('/companies/provision', [ExternalCompanyController::class, 'provision'])
        ->middleware('throttle:6,1');

    Route::post('/companies/revoke', [ExternalCompanyController::class, 'revoke'])
        ->middleware(['cle.entreprise', 'throttle:20,1']);

    Route::post('/companies/verify', [ExternalCompanyController::class, 'verify'])
        ->middleware(['cle.entreprise', 'throttle:60,1']);

    // ── Les déversements existants prennent le middleware ───────────
    //
    // C'est ici que la clé sert vraiment : sans ce middleware sur ces deux
    // routes, le secret partagé continue de suffire à écrire dans n'importe
    // quel dossier, et tout le reste du lot ne sert à rien.
    //
    // Reprenez les deux lignes que vous avez déjà et ajoutez-leur le
    // middleware, plutôt que de les redéclarer ici :
    //
    // Route::post('/ecritures/deverser',   [...])->middleware('cle.entreprise');
    // Route::post('/referentiel/deverser', [...])->middleware('cle.entreprise');
});
