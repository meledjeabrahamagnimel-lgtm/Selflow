<?php

namespace App\Modules\Authentification\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifierInscriptionComplete
{
    /**
     * Empêcher les actions de ventes, achats et devis si l'inscription de l'entreprise est incomplète.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('connexion');
        }

        $utilisateur = Auth::user();

        // Le SuperAdmin n'est pas bloqué par cette restriction
        if ($utilisateur->estSuperAdmin()) {
            return $next($request);
        }

        $entreprise = $utilisateur->entreprise;

        if ($entreprise && !$entreprise->estInscriptionComplete()) {
            // Si le nom de l'entreprise est encore le nom temporaire d'onboarding, on laisse passer le middleware
            // (le modal d'onboarding bloquera l'écran de toute façon).
            if ($entreprise->nom === '[PENDING_ONBOARDING]') {
                return $next($request);
            }

            // Vérifier si l'URL courante correspond à des ventes ou achats (y compris devis)
            if ($request->is('admin/ventes*') || $request->is('admin/achats*')) {
                if ($request->expectsJson()) {
                    return response()->json([
                        'statut'  => 'incomplet',
                        'message' => 'Terminer votre inscription avant de continuer.'
                    ], 403);
                }

                return redirect()->route('admin.tableau_de_bord')
                    ->with('ouvrir_modale_incomplet', true);
            }
        }

        return $next($request);
    }
}
