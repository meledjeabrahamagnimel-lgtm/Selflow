<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware AjouterEntetesSecurite
 *
 * Injecte les en-têtes de sécurité HTTP sur toutes les réponses.
 * Référence : Section 17.11 de la feuille de route Selflow.
 */
class AjouterEntetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Empêche le navigateur de deviner le type MIME (évite les uploads malveillants)
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // Protège contre le clickjacking (important pour un écran de caisse/paiement)
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // Limite les informations de référent envoyées à des tiers
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Désactive la sniffer de navigateur obsolète (IE)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Content-Security-Policy (politique de base — autoriser seulement les ressources du domaine)
        // En-tête permissif pour permettre les CDN utilisés (fonts Google, FontAwesome)
        // À durcir en production selon les besoins spécifiques
        $response->headers->set(
            'Content-Security-Policy',
            "default-src 'self'; " .
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; " .
            "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; " .
            "img-src 'self' data: blob:; " .
            "connect-src 'self';"
        );

        return $response;
    }
}
