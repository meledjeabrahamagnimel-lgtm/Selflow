<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\Vente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * FneControleur — Recherche de documents fiscaux ENTRANTS (DGI/FNE).
 *
 * Différent de FneService (qui NORMALISE les documents ÉMIS par l'entreprise
 * — factures de vente, BAPA). Ici, il s'agit de RETROUVER un document déjà
 * normalisé par un tiers (ex : une facture d'achat reçue d'un fournisseur,
 * dont on veut vérifier la référence FNE auprès de la DGI).
 *
 * Connecté le 23/07/2026 au système de clés par entreprise (FneCredential —
 * voir /PLAN/FNE-gestion-des-cles.md). Reste en mode STUB fonctionnel tant
 * qu'aucune clé n'est configurée pour l'entreprise : répond alors clairement
 * que la vérification n'est pas encore possible, sans jamais planter.
 *
 * Pour activer réellement la recherche dès que le format exact de l'API DGI
 * est confirmé : ajuster l'URL/chemin ci-dessous (actuellement une
 * supposition raisonnable calquée sur FneService, GET /documents/{ref}) et
 * le mapping de la réponse JSON.
 */
class FneControleur
{
    /**
     * Recherche un document fiscal par sa référence FNE, avec la clé
     * DGI propre à l'entreprise de l'utilisateur connecté.
     *
     * Exemple de requête :
     *   POST /admin/fne/rechercher
     *   { "reference_fne": "FNE-CI-2025-0001234" }
     */
    public function rechercherDocumentFiscal(Request $request): JsonResponse
    {
        $request->validate([
            'reference_fne' => 'required|string|min:5|max:100',
        ]);

        $referenceFne = $request->input('reference_fne');
        $entreprise = Auth::user()->entreprise;
        $credential = $entreprise->fneCredential;

        // Pas de clé configurée pour cette entreprise : stub explicite,
        // jamais d'erreur brute — comportement inchangé par rapport à avant.
        if (!$credential || !$credential->estConfiguree()) {
            return response()->json([
                'succes'  => false,
                'stub'    => true,
                'message' => "Aucune clé FNE n'est configurée pour votre entreprise. Contactez votre administrateur Selflow pour activer la vérification DGI. La référence '{$referenceFne}' a bien été enregistrée.",
                'reference_fne' => $referenceFne,
            ]);
        }

        $apiKey = $credential->cleActive();
        $apiUrl = $credential->statut === 'validee'
            ? config('selflow.fne_api_url_production', 'https://fne.dgi.gouv.ci')
            : config('selflow.fne_api_url_sandbox', 'https://fne-sandbox.dgi.gouv.ci');

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Accept'        => 'application/json',
            ])->timeout(10)->get($apiUrl . '/api/v1/documents/' . $referenceFne);

            if ($response->failed()) {
                Log::warning("[FNE] Recherche document '{$referenceFne}' échouée pour l'entreprise #{$entreprise->id} : HTTP {$response->status()}");

                return response()->json([
                    'succes'  => false,
                    'message' => 'Document FNE introuvable ou API DGI indisponible pour le moment.',
                    'reference_fne' => $referenceFne,
                ], 404);
            }

            return response()->json([
                'succes'   => true,
                'document' => $response->json('document') ?? $response->json(),
                'reference_fne' => $referenceFne,
            ]);
        } catch (\Throwable $e) {
            Log::error("[FNE] Erreur réseau recherche document '{$referenceFne}' entreprise #{$entreprise->id} : " . $e->getMessage());

            return response()->json([
                'succes'  => false,
                'message' => "Impossible de joindre le serveur DGI pour le moment. Réessayez plus tard.",
                'reference_fne' => $referenceFne,
            ], 503);
        }
    }

    /**
     * Attache une référence FNE à une facture de vente existante.
     */
    public function attacherFneVente(Request $request, Vente $vente): JsonResponse
    {
        // Sécurité (faille corrigée le 23/07/2026) : la vente référencée par
        // ID doit appartenir à l'entreprise de l'utilisateur connecté — même
        // règle que partout ailleurs dans l'application (voir MEMOIRE-SELFLOW,
        // section 3, "Sécurité multi-tenant (IDOR)").
        abort_unless($vente->pointDeVente->entreprise_id === Auth::user()->entreprise_id, 403);

        $request->validate([
            'numero_fne' => 'required|string|min:5|max:100',
        ]);

        $vente->update(['numero_fne' => $request->numero_fne]);

        return response()->json([
            'succes'    => true,
            'message'   => "Référence FNE '{$request->numero_fne}' enregistrée sur la vente.",
            'numero_fne' => $request->numero_fne,
        ]);
    }

    /**
     * Attache une référence FNE à un achat existant.
     */
    public function attacherFneAchat(Request $request, Achat $achat): JsonResponse
    {
        // Sécurité (faille corrigée le 23/07/2026) : même règle que ci-dessus.
        abort_unless($achat->pointDeVente->entreprise_id === Auth::user()->entreprise_id, 403);

        $request->validate([
            'numero_fne' => 'required|string|min:5|max:100',
        ]);

        $achat->update(['numero_fne' => $request->numero_fne]);

        return response()->json([
            'succes'    => true,
            'message'   => "Référence FNE '{$request->numero_fne}' enregistrée sur l'achat.",
            'numero_fne' => $request->numero_fne,
        ]);
    }
}
