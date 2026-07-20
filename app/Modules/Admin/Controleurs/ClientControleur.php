<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ClientControleur
{
    public function index(Request $request): View
    {
        $entreprise = Auth::user()->entreprise;
        $search = $request->input('search', '');

        $query = Client::where('entreprise_id', $entreprise->id)
            ->where(function ($q) {
                $q->where('source', '!=', 'comptaflow')
                  ->orWhereNull('source');
            })
            ->withCount('ventes')
            ->orderBy('nom');
        
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('numero_tiers', 'like', "%{$search}%")
                  ->orWhere('ncc', 'like', "%{$search}%");
            });
        }

        $clients = $query->paginate(15, ['*'], 'page_local')->withQueryString();

        $clientsComptaflow = Client::where('entreprise_id', $entreprise->id)
            ->where('source', 'comptaflow')
            ->withCount('ventes')
            ->orderBy('nom')
            ->paginate(15, ['*'], 'page_comptaflow');

        $comptes = \App\Modules\Admin\Modeles\PlanComptable::obtenirComptesPrioritaires($entreprise->id);

        return view('admin::clients.index', compact('clients', 'clientsComptaflow', 'comptes', 'entreprise', 'search'));
    }

    public function creer(Request $request): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;

        $request->validate([
            'nom'               => ['required', 'string', 'max:150'],
            'telephone'         => ['nullable', 'string', 'max:30'],
            'email'             => ['nullable', 'email', 'max:150'],
            'adresse'           => ['nullable', 'string', 'max:255'],
            'ncc'               => ['nullable', 'string', 'max:50'],
            'rccm'              => ['nullable', 'string', 'max:100'],
            'regime_imposition' => ['nullable', 'string', 'max:100'],
            'compte_comptable'  => [
                'required',
                'string',
                \Illuminate\Validation\Rule::exists('plan_comptable', 'numero')->where(function ($q) use ($entreprise) {
                    $q->whereNull('entreprise_id')->orWhere('entreprise_id', $entreprise->id);
                })
            ],
        ]);

        $autoNumero = $request->boolean('auto_numero_tiers');
        $numeroTiers = null;

        if ($autoNumero) {
            $max = Client::where('entreprise_id', $entreprise->id)
                ->where('numero_tiers', 'like', '411%')
                ->orderByRaw('CAST(numero_tiers AS UNSIGNED) DESC')
                ->value('numero_tiers');
            
            if ($max && is_numeric($max)) {
                $numeroTiers = (string) ((int) $max + 1);
            } else {
                $numeroTiers = '411000';
            }
        } else {
            $request->validate([
                'numero_tiers' => [
                    'required',
                    'string',
                    'regex:/^411[0-9]*$/',
                    \Illuminate\Validation\Rule::unique('clients', 'numero_tiers')->where('entreprise_id', $entreprise->id)
                ],
            ], [
                'numero_tiers.required' => 'Le numéro de tiers est obligatoire si la numérotation automatique n\'est pas cochée.',
                'numero_tiers.regex' => 'Le numéro de tiers doit commencer par 411 et ne contenir que des chiffres.',
                'numero_tiers.unique' => 'Ce numéro de tiers est déjà utilisé.',
            ]);
            $numeroTiers = $request->input('numero_tiers');
        }

        Client::create(array_merge(
            $request->only(['nom', 'telephone', 'email', 'adresse', 'ncc', 'rccm', 'regime_imposition', 'compte_comptable']),
            [
                'entreprise_id' => $entreprise->id,
                'numero_tiers' => $numeroTiers,
            ]
        ));

        return back()->with('succes', 'Client ajouté avec succès.');
    }

    public function modifier(Request $request, Client $client): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;
        abort_unless($client->entreprise_id === $entreprise->id, 403);

        if ($client->source === 'comptaflow') {
            // Uniquement les champs spécifiques à Selflow
            $request->validate([
                'telephone'         => ['nullable', 'string', 'max:30'],
                'email'             => ['nullable', 'email', 'max:150'],
                'adresse'           => ['nullable', 'string', 'max:255'],
                'ncc'               => ['nullable', 'string', 'max:50'],
                'rccm'              => ['nullable', 'string', 'max:100'],
                'regime_imposition' => ['nullable', 'string', 'max:100'],
            ]);

            $client->update($request->only([
                'telephone', 'email', 'adresse', 'ncc', 'rccm', 'regime_imposition'
            ]));
        } else {
            // Tous les champs
            $request->validate([
                'nom'               => ['required', 'string', 'max:150'],
                'telephone'         => ['nullable', 'string', 'max:30'],
                'email'             => ['nullable', 'email', 'max:150'],
                'adresse'           => ['nullable', 'string', 'max:255'],
                'ncc'               => ['nullable', 'string', 'max:50'],
                'rccm'              => ['nullable', 'string', 'max:100'],
                'regime_imposition' => ['nullable', 'string', 'max:100'],
                'compte_comptable'  => [
                    'required',
                    'string',
                    \Illuminate\Validation\Rule::exists('plan_comptable', 'numero')->where(function ($q) use ($entreprise) {
                        $q->whereNull('entreprise_id')->orWhere('entreprise_id', $entreprise->id);
                    })
                ],
                'numero_tiers'      => [
                    'required',
                    'string',
                    'regex:/^411[0-9]*$/',
                    \Illuminate\Validation\Rule::unique('clients', 'numero_tiers')->ignore($client->id)->where('entreprise_id', $entreprise->id)
                ],
            ]);

            $client->update($request->only([
                'nom', 'telephone', 'email', 'adresse', 'ncc', 'rccm', 'regime_imposition', 'compte_comptable', 'numero_tiers'
            ]));
        }

        return back()->with('succes', 'Client modifié avec succès.');
    }

    public function supprimer(Request $request, Client $client): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;
        abort_unless($client->entreprise_id === $entreprise->id, 403);

        if ($client->ventes_count > 0 || $client->ventes()->exists()) {
            return back()->with('erreur', 'Impossible de supprimer ce client : il est lié à des ventes enregistrées.');
        }

        $client->delete();
        return back()->with('succes', 'Client supprimé avec succès.');
    }
}
