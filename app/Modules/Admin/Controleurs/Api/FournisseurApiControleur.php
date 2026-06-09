<?php

namespace App\Modules\Admin\Controleurs\Api;

use App\Modules\Admin\Modeles\Fournisseur;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FournisseurApiControleur
{
    public function index(): JsonResponse
    {
        $entreprise   = Auth::user()->entreprise;
        $fournisseurs = Fournisseur::where('entreprise_id', $entreprise->id)
            ->withCount('achats')
            ->orderBy('nom')
            ->paginate(30);

        return response()->json([
            'statut' => 'succes',
            'fournisseurs' => $fournisseurs
        ]);
    }

    public function creer(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;

        $request->validate([
            'nom'               => ['required', 'string', 'max:150'],
            'telephone'         => ['nullable', 'string', 'max:30'],
            'email'             => ['nullable', 'email', 'max:150'],
            'adresse'           => ['nullable', 'string', 'max:255'],
            'secteur'           => ['nullable', 'string', 'max:100'],
            'ncc'               => ['nullable', 'string', 'max:50'],
            'regime_imposition' => ['nullable', 'string', 'max:100'],
        ]);

        $fournisseur = Fournisseur::create(array_merge(
            $request->only(['nom', 'telephone', 'email', 'adresse', 'secteur', 'ncc', 'regime_imposition']),
            ['entreprise_id' => $entreprise->id]
        ));

        return response()->json([
            'statut' => 'succes',
            'message' => 'Fournisseur ajouté avec succès.',
            'fournisseur' => $fournisseur
        ], 201);
    }
}
