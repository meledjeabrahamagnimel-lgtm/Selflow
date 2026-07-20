<?php

namespace App\Modules\Authentification\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifierModulesActifs
{
    /**
     * Vérifier que le module demandé est actif pour l'entreprise de l'utilisateur.
     *
     * @param string $module Le module requis (ex: stock, production, comptabilite, etc.)
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        if (! Auth::check()) {
            return redirect()->route('connexion');
        }

        $utilisateur = Auth::user();

        // Le SuperAdmin a tous les accès aux modules
        if ($utilisateur->estSuperAdmin()) {
            return $next($request);
        }

        $entreprise = $utilisateur->entreprise;

        if (! $entreprise) {
            abort(403, 'Accès refusé. Vous n\'êtes rattaché à aucune entreprise.');
        }

        $modulesActifs = $entreprise->modules_actifs;
        if (empty($modulesActifs)) {
            $modulesActifs = ['principal', 'ventes', 'achats', 'stock', 'production', 'comptabilite', 'points_de_vente', 'produits', 'tiers', 'rapports', 'b2b', 'fne'];
        }

        if (! in_array(strtolower($module), $modulesActifs, true)) {
            abort(403, "Accès refusé. Le module « " . ucfirst($module) . " » n'est pas activé pour votre entreprise.");
        }

        return $next($request);
    }
}
