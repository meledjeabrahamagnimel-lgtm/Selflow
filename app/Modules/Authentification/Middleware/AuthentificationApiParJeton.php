<?php

namespace App\Modules\Authentification\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Modules\Authentification\Modeles\Utilisateur;
use Symfony\Component\HttpFoundation\Response;

class AuthentificationApiParJeton
{
    /**
     * Gérer la requête d'API sécurisée par jeton.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authorization = $request->header('Authorization');

        if (!$authorization || !str_starts_with($authorization, 'Bearer ')) {
            return response()->json([
                'statut' => 'erreur',
                'message' => 'Non authentifié. Jeton manquant ou mal formé.'
            ], 401);
        }

        $jeton = substr($authorization, 7);

        // Recherche de l'utilisateur avec ce jeton
        $utilisateur = Utilisateur::where('jeton_api', $jeton)
            ->where('statut', 'actif')
            ->first();

        if (!$utilisateur) {
            return response()->json([
                'statut' => 'erreur',
                'message' => 'Non authentifié. Jeton d\'accès invalide ou compte inactif.'
            ], 401);
        }

        // Authentifier l'utilisateur pour la requête courante
        Auth::login($utilisateur);

        return $next($request);
    }
}
