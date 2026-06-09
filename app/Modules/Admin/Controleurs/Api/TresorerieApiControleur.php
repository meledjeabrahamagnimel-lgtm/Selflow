<?php

namespace App\Modules\Admin\Controleurs\Api;

use App\Modules\Admin\Modeles\TresorerieJournal;
use App\Modules\Admin\Modeles\CodeJournal;
use App\Modules\Admin\Modeles\Banque;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TresorerieApiControleur
{
    public function encaissements(): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $operations = TresorerieJournal::with('pointDeVente')
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->where('type_operation', 'Encaissement')
            ->latest()
            ->paginate(30);

        return response()->json([
            'statut' => 'succes',
            'operations' => $operations
        ]);
    }

    public function decaissements(): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $operations = TresorerieJournal::with('pointDeVente')
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->where('type_operation', 'Décaissement')
            ->latest()
            ->paginate(30);

        return response()->json([
            'statut' => 'succes',
            'operations' => $operations
        ]);
    }

    public function journal(): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $operations = TresorerieJournal::with('pointDeVente')
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->latest()
            ->paginate(30);

        // Sommes calculées sur le journal
        $totalEntrees  = TresorerieJournal::whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->sum('montant_entree');
        $totalSorties  = TresorerieJournal::whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->sum('montant_sortie');
        $soldeFinal    = $totalEntrees - $totalSorties;

        return response()->json([
            'statut' => 'succes',
            'donnees' => [
                'total_entrees' => floatval($totalEntrees),
                'total_sorties' => floatval($totalSorties),
                'solde_final' => floatval($soldeFinal),
                'operations' => $operations
            ]
        ]);
    }

    public function codesJournaux(): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $codes = CodeJournal::where('entreprise_id', $entreprise->id)->latest()->get();

        return response()->json([
            'statut' => 'succes',
            'codes' => $codes
        ]);
    }

    public function creerCodeJournal(Request $request): JsonResponse
    {
        $request->validate([
            'type'     => 'required|string|max:255',
            'code'     => 'required|string|max:50',
            'intitule' => 'required|string|max:255',
            'compte'   => 'required|string|max:50',
        ]);

        $code = CodeJournal::create([
            'entreprise_id' => Auth::user()->entreprise_id,
            'type'          => $request->type,
            'code'          => $request->code,
            'intitule'      => $request->intitule,
            'compte'        => $request->compte,
        ]);

        return response()->json([
            'statut' => 'succes',
            'message' => 'Code journal créé avec succès !',
            'code' => $code
        ], 201);
    }

    public function supprimerCodeJournal(CodeJournal $code): JsonResponse
    {
        if ($code->entreprise_id !== Auth::user()->entreprise_id) {
            return response()->json([
                'statut' => 'erreur',
                'message' => 'Accès non autorisé.'
            ], 403);
        }

        $code->delete();

        return response()->json([
            'statut' => 'succes',
            'message' => 'Code journal supprimé avec succès !'
        ]);
    }

    public function creerBanque(Request $request): JsonResponse
    {
        $request->validate([
            'nom'           => 'required|string|max:255',
            'numero_compte' => 'required|string|max:255',
        ]);

        $banque = Banque::create([
            'entreprise_id' => Auth::user()->entreprise_id,
            'nom'           => $request->nom,
            'numero_compte' => $request->numero_compte,
        ]);

        return response()->json([
            'statut' => 'succes',
            'message' => 'Banque configurée avec succès.',
            'banque' => $banque
        ], 201);
    }
}
