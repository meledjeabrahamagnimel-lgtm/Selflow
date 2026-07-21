<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\TresorerieJournal;
use App\Modules\Admin\Modeles\CodeJournal;
use App\Modules\Admin\Modeles\Banque;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class TresorerieControleur
{
    public function encaissements(): View
    {
        $entreprise = Auth::user()->entreprise;
        $pointDeVenteId = Auth::user()->estCaissier()
            ? Auth::user()->point_de_vente_id
            : session('point_de_vente_actif_id');

        $query = TresorerieJournal::with('pointDeVente')
            ->where(function($q) {
                $q->whereIn('type_operation', ['recette', 'Encaissement', 'encaissement'])
                  ->orWhere('montant_entree', '>', 0);
            });

        if ($pointDeVenteId) {
            $query->where('point_de_vente_id', $pointDeVenteId);
        } else {
            $query->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id));
        }

        $operations = $query->latest('date_operation')->latest('id')->paginate(30);

        return view('admin::tresorerie.encaissements', compact('operations'));
    }

    public function decaissements(): View
    {
        $entreprise = Auth::user()->entreprise;
        $pointDeVenteId = Auth::user()->estCaissier()
            ? Auth::user()->point_de_vente_id
            : session('point_de_vente_actif_id');

        $query = TresorerieJournal::with('pointDeVente')
            ->where(function($q) {
                $q->whereIn('type_operation', ['depense', 'Décaissement', 'dépense', 'decaissement'])
                  ->orWhere('montant_sortie', '>', 0);
            });

        if ($pointDeVenteId) {
            $query->where('point_de_vente_id', $pointDeVenteId);
        } else {
            $query->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id));
        }

        $operations = $query->latest('date_operation')->latest('id')->paginate(30);

        return view('admin::tresorerie.decaissements', compact('operations'));
    }

    public function journal(Request $request): View
    {
        $entreprise = Auth::user()->entreprise;
        $role = Auth::user()->role;
        $pointsDeVente = $entreprise->pointsDeVente()->orderBy('nom')->get();

        // Récupérer le point de vente à filtrer
        $pointDeVenteId = $request->input('point_de_vente_id');
        if ($pointDeVenteId === null) {
            $pointDeVenteId = Auth::user()->estCaissier()
                ? Auth::user()->point_de_vente_id
                : session('point_de_vente_actif_id') ?? 'tous';
        }

        $query = TresorerieJournal::with('pointDeVente');

        // Filtrage Point de Vente
        if ($pointDeVenteId !== 'tous' && !empty($pointDeVenteId)) {
            $query->where('point_de_vente_id', $pointDeVenteId);
        } else {
            $query->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id));
        }

        // Filtrage Mode de paiement
        if ($request->filled('mode_paiement')) {
            $query->where('mode_paiement', $request->mode_paiement);
        }

        // Filtrage Banque (Moyen Bancaire)
        if ($request->filled('moyen_bancaire')) {
            $query->where('moyen_bancaire', $request->moyen_bancaire);
        }

        // Récupérer la liste des modes de paiement et moyens bancaires uniques existants en base pour cette entreprise
        $pdvIds = $pointsDeVente->pluck('id');
        $modesDisponibles = TresorerieJournal::whereIn('point_de_vente_id', $pdvIds)
            ->whereNotNull('mode_paiement')
            ->distinct()
            ->pluck('mode_paiement');

        $moyensBancairesDisponibles = TresorerieJournal::whereIn('point_de_vente_id', $pdvIds)
            ->whereNotNull('moyen_bancaire')
            ->where('moyen_bancaire', '!=', '')
            ->distinct()
            ->pluck('moyen_bancaire');

        // Calculer les totaux de trésorerie sur l'ensemble filtré (avant pagination)
        $totalEntrees = (clone $query)->sum('montant_entree');
        $totalSorties = (clone $query)->sum('montant_sortie');
        $soldeFinal   = $totalEntrees - $totalSorties;

        $operations = $query->latest()->paginate(30)->withQueryString();

        return view('admin::tresorerie.journal', compact(
            'operations',
            'totalEntrees',
            'totalSorties',
            'soldeFinal',
            'pointsDeVente',
            'pointDeVenteId',
            'modesDisponibles',
            'moyensBancairesDisponibles'
        ));
    }

    public function codesJournaux(): View
    {
        $entreprise = Auth::user()->entreprise;
        
        $codes = CodeJournal::where('entreprise_id', $entreprise->id)
            ->where(function ($q) {
                $q->where('source', '!=', 'comptaflow')
                  ->orWhereNull('source');
            })
            ->latest()
            ->get();

        $codesComptaflow = CodeJournal::where('entreprise_id', $entreprise->id)
            ->where('source', 'comptaflow')
            ->latest()
            ->get();

        return view('admin::tresorerie.codes_journaux', compact('codes', 'codesComptaflow', 'entreprise'));
    }

    public function creerCodeJournal(Request $request): RedirectResponse
    {
        $request->validate([
            'type'     => 'required|string|max:255',
            'code'     => 'required|string|max:50',
            'intitule' => 'required|string|max:255',
            'compte'   => 'required|string|max:50',
        ]);

        CodeJournal::create([
            'entreprise_id' => Auth::user()->entreprise_id,
            'type'          => $request->type,
            'code'          => $request->code,
            'intitule'      => $request->intitule,
            'compte'        => $request->compte,
        ]);

        return redirect()->back()->with('succes', 'Code journal créé avec succès !');
    }

    public function supprimerCodeJournal(CodeJournal $code): RedirectResponse
    {
        abort_unless($code->entreprise_id === Auth::user()->entreprise_id, 403);
        $code->delete();
        return redirect()->back()->with('succes', 'Code journal supprimé avec succès !');
    }

    public function creerBanqueAjax(Request $request): JsonResponse
    {
        $request->validate([
            'code'     => 'required|string|max:50',
            'intitule' => 'required|string|max:255',
            'compte'   => 'required|string|max:50',
        ]);

        $journal = CodeJournal::create([
            'entreprise_id' => Auth::user()->entreprise_id,
            'type'          => 'Banque',
            'code'          => strtoupper($request->code),
            'intitule'      => $request->intitule,
            'compte'        => $request->compte,
        ]);

        return response()->json([
            'succes' => true,
            'banque' => [
                'id'            => $journal->id,
                'nom'           => $journal->intitule,
                'numero_compte' => $journal->code . ' - ' . $journal->compte,
                'code'          => $journal->code,
                'intitule'      => $journal->intitule,
                'compte'        => $journal->compte,
            ]
        ]);
    }
}
