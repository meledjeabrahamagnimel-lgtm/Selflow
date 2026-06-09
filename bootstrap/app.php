<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => \App\Modules\Authentification\Middleware\VerifierRole::class,
            'apercu.readonly' => \App\Modules\Authentification\Middleware\ApercuLectureSeule::class,
            'caissier.acces' => \App\Modules\Authentification\Middleware\VerifierAccesCaissier::class,
            'habilitation' => \App\Modules\Authentification\Middleware\VerifierHabilitationRoute::class,
            'auth.api' => \App\Modules\Authentification\Middleware\AuthentificationApiParJeton::class,
        ]);

        $middleware->redirectTo(function (Request $request) {
            if ($request->is('api/*')) {
                abort(response()->json([
                    'statut' => 'erreur',
                    'message' => 'Non authentifié.'
                ], 401));
            }
            return route('connexion');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
