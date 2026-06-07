<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\TresorerieJournal;
use Illuminate\Http\Request;
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
}
