<?php

namespace App\Modules\Authentification\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifierJetonHub
{
    public function handle(Request $request, Closure $next): Response
    {
        $jetonRecu = $request->header('X-Hub-Token');
        $jetonAttendu = config('services.hub.token');

        if (!$jetonRecu || !$jetonAttendu || !hash_equals($jetonAttendu, $jetonRecu)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Non autorisé. Token Hub invalide ou manquant.'
            ], 401);
        }

        return $next($request);
    }
}
