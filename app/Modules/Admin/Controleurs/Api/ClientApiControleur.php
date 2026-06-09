<?php

namespace App\Modules\Admin\Controleurs\Api;

use App\Modules\Admin\Modeles\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClientApiControleur
{
    public function index(): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $clients    = Client::where('entreprise_id', $entreprise->id)
            ->withCount('ventes')
            ->orderBy('nom')
            ->paginate(30);

        return response()->json([
            'statut' => 'succes',
            'clients' => $clients
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
            'ncc'               => ['nullable', 'string', 'max:50'],
            'regime_imposition' => ['nullable', 'string', 'max:100'],
        ]);

        $client = Client::create(array_merge(
            $request->only(['nom', 'telephone', 'email', 'adresse', 'ncc', 'regime_imposition']),
            ['entreprise_id' => $entreprise->id]
        ));

        return response()->json([
            'statut' => 'succes',
            'message' => 'Client ajouté avec succès.',
            'client' => $client
        ], 201);
    }
}
