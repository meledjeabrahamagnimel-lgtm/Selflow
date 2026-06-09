<?php

namespace App\Modules\Admin\Controleurs\Api;

use App\Modules\Admin\Modeles\PointDeVente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PointDeVenteApiControleur
{
    public function index(): JsonResponse
    {
        $entreprise   = Auth::user()->entreprise;
        $pointsDeVente = PointDeVente::where('entreprise_id', $entreprise->id)
            ->withCount('utilisateurs')
            ->withCount('ventes')
            ->orderBy('nom')
            ->get()
            ->map(function ($pdv) {
                return [
                    'id' => $pdv->id,
                    'nom' => $pdv->nom,
                    'ville' => $pdv->ville,
                    'commune' => $pdv->commune,
                    'responsable' => $pdv->responsable,
                    'telephone' => $pdv->telephone,
                    'statut' => $pdv->statut,
                    'utilisateurs_count' => $pdv->utilisateurs_count,
                    'ventes_count' => $pdv->ventes_count
                ];
            });

        return response()->json([
            'statut' => 'succes',
            'quota_max' => $entreprise->quota_points_de_vente,
            'nombre_actuel' => $pointsDeVente->count(),
            'points_de_vente' => $pointsDeVente
        ]);
    }

    public function creer(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;

        $request->validate([
            'nom'        => ['required', 'string', 'max:100'],
            'ville'      => ['required', 'string', 'max:100'],
            'commune'    => ['nullable', 'string', 'max:100'],
            'responsable'=> ['nullable', 'string', 'max:150'],
            'telephone'  => ['nullable', 'string', 'max:30'],
        ]);

        if ($entreprise->pointsDeVente()->count() >= $entreprise->quota_points_de_vente) {
            return response()->json([
                'statut' => 'erreur',
                'message' => 'Quota de points de vente atteint pour votre abonnement.'
            ], 400);
        }

        $pdv = PointDeVente::create(array_merge(
            $request->only(['nom', 'ville', 'commune', 'responsable', 'telephone']),
            ['entreprise_id' => $entreprise->id, 'statut' => 'Ouvert']
        ));

        return response()->json([
            'statut' => 'succes',
            'message' => 'Point de vente créé avec succès.',
            'point_de_vente' => [
                'id' => $pdv->id,
                'nom' => $pdv->nom
            ]
        ], 201);
    }

    public function activerSession(Request $request, PointDeVente $pdv): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        if ($pdv->entreprise_id !== $entreprise->id) {
            return response()->json([
                'statut' => 'erreur',
                'message' => 'Accès non autorisé.'
            ], 403);
        }

        session(['point_de_vente_actif_id' => $pdv->id, 'point_de_vente_actif_nom' => $pdv->nom]);

        return response()->json([
            'statut' => 'succes',
            'message' => "Point de vente « {$pdv->nom} » activé pour cette session.",
            'point_de_vente_actif_id' => $pdv->id
        ]);
    }

    public function activerApercu(PointDeVente $pdv): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        if ($pdv->entreprise_id !== $entreprise->id) {
            return response()->json([
                'statut' => 'erreur',
                'message' => 'Accès non autorisé.'
            ], 403);
        }

        session([
            'apercu_pdv_id' => $pdv->id,
            'apercu_pdv_nom' => $pdv->nom,
            'point_de_vente_actif_id' => $pdv->id,
            'point_de_vente_actif_nom' => $pdv->nom,
        ]);

        return response()->json([
            'statut' => 'succes',
            'message' => "Aperçu du point de vente « {$pdv->nom} » activé en mode lecture seule.",
            'point_de_vente_actif_id' => $pdv->id
        ]);
    }

    public function desactiverApercu(): JsonResponse
    {
        session()->forget(['apercu_pdv_id', 'apercu_pdv_nom', 'point_de_vente_actif_id', 'point_de_vente_actif_nom']);

        return response()->json([
            'statut' => 'succes',
            'message' => "Mode aperçu désactivé. Retour à l'administration principale."
        ]);
    }
}
