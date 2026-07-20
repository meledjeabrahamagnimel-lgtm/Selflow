<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\Vente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * FneControleur — Stub pour la future intégration DGI/FNE.
 *
 * Lot I : Ce contrôleur est un stub (squelette documenté) prévu pour accueillir
 * la logique de recherche et de récupération d'informations fiscales via l'API
 * de la DGI (Direction Générale des Impôts) ou FNE (Fichier National des
 * Entreprises) dès que l'API sera disponible.
 *
 * Utilisation prévue :
 * - Saisir une référence FNE dans les formulaires d'achat/vente.
 * - Appeler cette API pour récupérer les informations d'un document fiscal
 *   (fournisseur, montant, TVA, date, articles, etc.).
 * - Pré-remplir automatiquement le formulaire avec ces données.
 *
 * Pour l'activer quand l'API DGI est disponible :
 * 1. Renseigner FNE_API_URL et FNE_API_KEY dans .env
 * 2. Décommenter les appels réels dans rechercherDocumentFiscal()
 * 3. Mapper le JSON de réponse aux champs Selflow
 */
class FneControleur
{
    /**
     * Recherche un document fiscal par sa référence FNE.
     *
     * @param  Request  $request  Doit contenir 'reference_fne' (string)
     * @return JsonResponse
     *
     * STUB : Retourne actuellement des données fictives simulées.
     * Remplacer le corps par l'appel HTTP réel à l'API DGI/FNE.
     *
     * Exemple de requête :
     *   POST /admin/fne/rechercher
     *   { "reference_fne": "FNE-CI-2025-0001234" }
     *
     * Exemple de réponse attendue :
     *   { "succes": true, "document": { "numero": "...", "fournisseur": {...}, ... } }
     */
    public function rechercherDocumentFiscal(Request $request): JsonResponse
    {
        $request->validate([
            'reference_fne' => 'required|string|min:5|max:100',
        ]);

        $referenceFne = $request->input('reference_fne');

        // =========================================================
        // STUB — À REMPLACER PAR L'APPEL API DGI/FNE RÉEL
        // =========================================================
        // Exemple d'implémentation future :
        //
        // $apiUrl  = config('services.fne.url', env('FNE_API_URL'));
        // $apiKey  = config('services.fne.key', env('FNE_API_KEY'));
        //
        // $response = Http::withHeaders([
        //     'Authorization' => 'Bearer ' . $apiKey,
        //     'Accept'        => 'application/json',
        // ])->get($apiUrl . '/documents/' . $referenceFne);
        //
        // if ($response->failed()) {
        //     return response()->json([
        //         'succes'  => false,
        //         'message' => 'Document FNE introuvable ou API indisponible.',
        //     ], 404);
        // }
        //
        // $document = $response->json('document');
        // =========================================================

        // Réponse stub simulée pour tests et développement
        return response()->json([
            'succes'  => false,
            'stub'    => true,
            'message' => "L'API DGI/FNE n'est pas encore disponible. La référence '{$referenceFne}' a bien été enregistrée et sera vérifiable dès l'activation de l'API.",
            'reference_fne' => $referenceFne,
        ]);
    }

    /**
     * Met à jour la référence FNE d'une facture de vente existante.
     *
     * STUB : La validation réelle via l'API DGI sera activée ultérieurement.
     */
    public function attacherFneVente(Request $request, Vente $vente): JsonResponse
    {
        $request->validate([
            'numero_fne' => 'required|string|min:5|max:100',
        ]);

        $vente->update(['numero_fne' => $request->numero_fne]);

        return response()->json([
            'succes'    => true,
            'stub'      => true,
            'message'   => "Référence FNE '{$request->numero_fne}' enregistrée. Validation DGI en attente d'activation de l'API.",
            'numero_fne' => $request->numero_fne,
        ]);
    }

    /**
     * Met à jour la référence FNE d'un achat existant.
     *
     * STUB : La validation réelle via l'API DGI sera activée ultérieurement.
     */
    public function attacherFneAchat(Request $request, Achat $achat): JsonResponse
    {
        $request->validate([
            'numero_fne' => 'required|string|min:5|max:100',
        ]);

        $achat->update(['numero_fne' => $request->numero_fne]);

        return response()->json([
            'succes'    => true,
            'stub'      => true,
            'message'   => "Référence FNE '{$request->numero_fne}' enregistrée sur l'achat. Validation DGI en attente d'activation de l'API.",
            'numero_fne' => $request->numero_fne,
        ]);
    }
}
