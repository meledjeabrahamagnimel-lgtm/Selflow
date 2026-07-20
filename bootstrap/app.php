<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([

            'role'              => \App\Modules\Authentification\Middleware\VerifierRole::class,
            'apercu.readonly'   => \App\Modules\Authentification\Middleware\ApercuLectureSeule::class,
            'caissier.acces'   => \App\Modules\Authentification\Middleware\VerifierAccesCaissier::class,
            'habilitation'      => \App\Modules\Authentification\Middleware\VerifierHabilitationRoute::class,
            'auth.api'          => \App\Modules\Authentification\Middleware\AuthentificationApiParJeton::class,
            'periode'           => \App\Modules\Authentification\Middleware\GestionPeriode::class,
            'modules'           => \App\Modules\Authentification\Middleware\VerifierModulesActifs::class,
            'forcer.mdp'        => \App\Modules\Authentification\Middleware\ForcerChangementMotDePasse::class,

            'role' => \App\Modules\Authentification\Middleware\VerifierRole::class,
            'apercu.readonly' => \App\Modules\Authentification\Middleware\ApercuLectureSeule::class,
            'caissier.acces' => \App\Modules\Authentification\Middleware\VerifierAccesCaissier::class,
            'habilitation' => \App\Modules\Authentification\Middleware\VerifierHabilitationRoute::class,
            'auth.api' => \App\Modules\Authentification\Middleware\AuthentificationApiParJeton::class,
            'periode' => \App\Modules\Authentification\Middleware\GestionPeriode::class,
            'hub.token' => \App\Modules\Authentification\Middleware\VerifierJetonHub::class,

        ]);

        // En-têtes de sécurité HTTP sur toutes les réponses web (Section 17.11)
        $middleware->appendToGroup('web', \App\Http\Middleware\AjouterEntetesSecurite::class);

        // Forcer le changement de mot de passe à la 1ère connexion (Section 13)
        $middleware->appendToGroup('web', \App\Modules\Authentification\Middleware\ForcerChangementMotDePasse::class);

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

        $exceptions->render(function (\Throwable $e, Request $request) {
            // Uniquement pour les requêtes web non-API en production
            if (!config('app.debug') && !$request->is('api/*')) {
                // Tenter d'envoyer l'alerte email aux super-admins
                try {
                    $url = $request->fullUrl();
                    $msg = "Alerte Production : Panne détectée sur Selflow\n\n";
                    $msg .= "URL de la page : " . $url . "\n";
                    $msg .= "Erreur : " . $e->getMessage() . "\n";
                    $msg .= "Fichier : " . $e->getFile() . "\n";
                    $msg .= "Ligne : " . $e->getLine() . "\n\n";
                    $msg .= "Trace d'exécution :\n" . substr($e->getTraceAsString(), 0, 1500) . "\n";

                    \Illuminate\Support\Facades\Mail::raw($msg, function ($mail) {
                        $mail->to(['meledjeagnimel17@gmail.com', 'it.dcknowin@gmail.com'])
                             ->subject("[Selflow - Alerte Technique] Dysfonctionnement détecté en production");
                    });
                } catch (\Throwable $mailError) {
                    // Tolérance aux pannes : ignorer si l'envoi d'email échoue (ex: SMTP non configuré)
                }

                // Renvoyer la vue d'erreur 500 personnalisée
                return response()->view('errors.500', [], 500);
            }
        });
    })->create();
