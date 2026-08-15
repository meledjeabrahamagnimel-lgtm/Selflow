<?php

namespace App\Modules\Authentification\Middleware;

use App\Modules\Authentification\Modeles\Utilisateur;
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

        // Une route qui demande `admin` accepte aussi les accès délégués.
        //
        // Ils n'avaient aucun espace : `role:admin` comparait à l'identique, et
        // un compte créé pour aider tombait sur un refus, ou sur la boucle de
        // redirection de la racine. Or déléguer, c'est précisément ouvrir son
        // propre espace à quelqu'un d'autre.
        //
        // **Cela n'ouvre rien par soi-même** : `habilitation` s'exécute juste
        // après, ferme par défaut, et un délégué n'a que ce qu'on lui a coché.
        // Ce middleware-ci dit dans quel espace on travaille ; l'autre dit ce
        // qu'on y fait.
        $acceptes = in_array('admin', $roles, true)
            ? array_merge($roles, Utilisateur::ROLES_DELEGUES)
            : $roles;

        if (! in_array($utilisateur->role, $acceptes, true)) {
            abort(403, 'Accès refusé. Vous n\'avez pas les droits nécessaires pour cette page.');
        }

        return $next($request);
    }
}
