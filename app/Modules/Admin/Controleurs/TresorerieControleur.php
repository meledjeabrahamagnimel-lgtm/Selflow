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
        $operations = TresorerieJournal::with('pointDeVente')
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->where('type_operation', 'Encaissement')
            ->latest()
            ->paginate(30);

        return view('admin::tresorerie.encaissements', compact('operations'));
    }

    public function decaissements(): View
    {
        $entreprise = Auth::user()->entreprise;
        $operations = TresorerieJournal::with('pointDeVente')
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->where('type_operation', 'Décaissement')
            ->latest()
            ->paginate(30);

        return view('admin::tresorerie.decaissements', compact('operations'));
    }

    public function journal(): View
    {
        $entreprise = Auth::user()->entreprise;
        $operations = TresorerieJournal::with('pointDeVente')
            ->whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entreprise->id))
            ->latest()
            ->paginate(30);

        $totalEntrees  = $operations->sum('montant_entree');
        $totalSorties  = $operations->sum('montant_sortie');
        $soldeFinal    = $totalEntrees - $totalSorties;

        return view('admin::tresorerie.journal', compact('operations', 'totalEntrees', 'totalSorties', 'soldeFinal'));
    }

    public function codesJournaux(): View
    {
        $entreprise = Auth::user()->entreprise;
        $codes = CodeJournal::where('entreprise_id', $entreprise->id)->latest()->get();
        return view('admin::tresorerie.codes_journaux', compact('codes'));
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
