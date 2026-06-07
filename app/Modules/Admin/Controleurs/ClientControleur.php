<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ClientControleur
{
    public function index(): View
    {
        $entreprise = Auth::user()->entreprise;
        $clients    = Client::where('entreprise_id', $entreprise->id)
            ->withCount('ventes')
            ->orderBy('nom')
            ->paginate(30);

        return view('admin::clients.index', compact('clients'));
    }

    public function creer(Request $request): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;

        $request->validate([
            'nom'       => ['required', 'string', 'max:150'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email'     => ['nullable', 'email', 'max:150'],
            'adresse'   => ['nullable', 'string', 'max:255'],
        ]);

        Client::create(array_merge(
            $request->only(['nom', 'telephone', 'email', 'adresse']),
            ['entreprise_id' => $entreprise->id]
        ));

        return back()->with('succes', 'Client ajouté avec succès.');
    }
}
