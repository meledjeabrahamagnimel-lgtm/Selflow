<?php

namespace App\Modules\Authentification\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifierAccesCaissier
{
    /**
     * Gérer la requête entrante et vérifier l'accès à l'interface caissier.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('connexion');
        }

        $utilisateur = Auth::user();

        // Les caissiers ont toujours accès à l'interface caissier
        if ($utilisateur->estCaissier()) {
            return $next($request);
        }

        // Les administrateurs n'ont accès à l'interface caissier que si le mode aperçu est actif en session
        if (($utilisateur->estAdmin() || $utilisateur->estSuperAdmin()) && session()->has('apercu_pdv_id')) {
            return $next($request);
        }

        // Rediriger vers l'administration avec un message d'erreur
        return redirect()->route('admin.tableau_de_bord')
            ->withErrors(['general' => 'Accès refusé. Veuillez activer l\'aperçu d\'un point de vente pour accéder à cette interface.']);
    }
}
