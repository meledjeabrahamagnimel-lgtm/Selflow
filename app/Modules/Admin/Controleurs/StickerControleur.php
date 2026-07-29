<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\StickerTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class StickerControleur
{
    /**
     * Page Gestion des Stickers avec historique et KPIs.
     */
    public function index(): View
    {
        $entreprise = Auth::user()->entreprise;

        $transactions = StickerTransaction::where('entreprise_id', $entreprise->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // KPIs calculés localement (base Selflow) — sera remplacé par API DGI quand disponible
        $totalAchetes   = StickerTransaction::where('entreprise_id', $entreprise->id)
            ->where('statut', 'succes')
            ->sum('nombre_stickers');

        $totalConsommes = 0; // TODO: brancher l'API DGI pour le solde réel

        $soldeActuel = $totalAchetes - $totalConsommes;

        $alerteSeuil = $entreprise->sticker_solde_alerte ?? 5;
        $alerte      = $soldeActuel <= $alerteSeuil && $totalAchetes > 0;

        $kpis = compact('soldeActuel', 'totalAchetes', 'totalConsommes', 'alerteSeuil', 'alerte');

        return view('admin::fne.stickers', compact('entreprise', 'transactions', 'kpis'));
    }

    /**
     * Enregistrer un achat de stickers (POST AJAX).
     */
    public function acheter(Request $request): JsonResponse
    {
        $entreprise = Auth::user()->entreprise;

        $validated = $request->validate([
            'operateur'       => ['required', 'in:ORANGE,MOOV,MTN'],
            'telephone'       => ['required', 'string', 'max:30'],
            'nombre_stickers' => ['required', 'integer', 'min:1', 'max:10000'],
        ], [
            'operateur.required'       => 'Veuillez sélectionner un opérateur Mobile Money.',
            'telephone.required'       => 'Le numéro de téléphone est obligatoire.',
            'nombre_stickers.required' => 'Le nombre de stickers est obligatoire.',
            'nombre_stickers.min'      => 'Vous devez acheter au minimum 1 sticker.',
        ]);

        $montant = $validated['nombre_stickers'] * 10; // 10 FCFA par sticker

        $transaction = StickerTransaction::create([
            'entreprise_id'  => $entreprise->id,
            'operateur'      => $validated['operateur'],
            'telephone'      => $validated['telephone'],
            'nombre_stickers'=> $validated['nombre_stickers'],
            'montant'        => $montant,
            'statut'         => 'en_attente', // sera mis à jour par webhook / cron DGI
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Demande d\'achat enregistrée. Statut : En attente de confirmation Mobile Money.',
            'transaction' => [
                'id'              => $transaction->id,
                'operateur'       => $transaction->operateur,
                'telephone'       => $transaction->telephone,
                'nombre_stickers' => $transaction->nombre_stickers,
                'montant'         => number_format($transaction->montant, 0, ',', ' ') . ' FCFA',
                'statut'          => $transaction->statutLabel(),
                'date'            => $transaction->created_at->format('d/m/Y H:i'),
            ],
        ]);
    }
}
