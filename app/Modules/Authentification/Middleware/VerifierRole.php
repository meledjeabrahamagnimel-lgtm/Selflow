<?php

namespace App\Modules\Authentification\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifierRole
{
    /**
     * Vérifier que l'utilisateur authentifié possède le rôle requis.
     *
     * @param string ...$roles Rôles autorisés (séparés par des virgules)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            return redirect()->route('connexion');
        }

        $utilisateur = Auth::user();

        if (! in_array($utilisateur->role, $roles, true)) {
            abort(403, 'Accès refusé. Vous n\'avez pas les droits nécessaires pour cette page.');
        }

        return $next($request);
    }
}
