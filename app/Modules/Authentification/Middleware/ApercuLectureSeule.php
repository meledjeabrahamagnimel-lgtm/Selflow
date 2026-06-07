<?php

namespace App\Modules\Authentification\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApercuLectureSeule
{
    /**
     * Gérer la requête entrante.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Si nous sommes en mode aperçu et que la requête n'est pas un GET (donc une modification/suppression/création),
        // nous bloquons l'action, sauf si c'est la route pour quitter l'aperçu.
        if (session()->has('apercu_pdv_id')) {
            if (! $request->isMethod('GET') && ! $request->routeIs('admin.pdv.desactiver_apercu')) {
                return back()->withErrors(['general' => 'Action impossible en mode aperçu (lecture seule).']);
            }
        }

        return $next($request);
    }
}
