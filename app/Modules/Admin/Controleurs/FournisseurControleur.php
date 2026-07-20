<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Fournisseur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FournisseurControleur
{
    public function index(Request $request): View
    {
        $entreprise   = Auth::user()->entreprise;
        
        $fournisseurs = Fournisseur::where('entreprise_id', $entreprise->id)
             ->where(function ($q) {
                 $q->where('source', '!=', 'comptaflow')
                   ->orWhereNull('source');
             })
             ->withCount('achats')
             ->orderBy('nom')
             ->paginate(15, ['*'], 'page_local');

        $fournisseursComptaflow = Fournisseur::where('entreprise_id', $entreprise->id)
             ->where('source', 'comptaflow')
             ->withCount('achats')
             ->orderBy('nom')
             ->paginate(15, ['*'], 'page_comptaflow');

        $comptes = \App\Modules\Admin\Modeles\PlanComptable::obtenirComptesPrioritaires($entreprise->id);

        return view('admin::fournisseurs.index', compact('fournisseurs', 'fournisseursComptaflow', 'comptes', 'entreprise'));
    }

    public function creer(Request $request): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;

        $request->validate([
            'nom'               => ['required', 'string', 'max:150'],
            'telephone'         => ['nullable', 'string', 'max:30'],
            'email'             => ['nullable', 'email', 'max:150'],
            'adresse'           => ['nullable', 'string', 'max:255'],
            'secteur'           => ['nullable', 'string', 'max:100'],
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
            $max = Fournisseur::where('entreprise_id', $entreprise->id)
                ->where('numero_tiers', 'like', '401%')
                ->orderByRaw('CAST(numero_tiers AS UNSIGNED) DESC')
                ->value('numero_tiers');
            
            if ($max && is_numeric($max)) {
                $numeroTiers = (string) ((int) $max + 1);
            } else {
                $numeroTiers = '401000';
            }
        } else {
            $request->validate([
                'numero_tiers' => [
                    'required',
                    'string',
                    'regex:/^401[0-9]*$/',
                    \Illuminate\Validation\Rule::unique('fournisseurs', 'numero_tiers')->where('entreprise_id', $entreprise->id)
                ],
            ], [
                'numero_tiers.required' => 'Le numéro de tiers est obligatoire si la numérotation automatique n\'est pas cochée.',
                'numero_tiers.regex' => 'Le numéro de tiers doit commencer par 401 et ne contenir que des chiffres.',
                'numero_tiers.unique' => 'Ce numéro de tiers est déjà utilisé.',
            ]);
            $numeroTiers = $request->input('numero_tiers');
        }

        Fournisseur::create(array_merge(
            $request->only(['nom', 'telephone', 'email', 'adresse', 'secteur', 'ncc', 'rccm', 'regime_imposition', 'compte_comptable']),
            [
                'entreprise_id' => $entreprise->id,
                'numero_tiers' => $numeroTiers,
            ]
        ));

        return back()->with('succes', 'Fournisseur ajouté avec succès.');
    }

    public function modifier(Request $request, Fournisseur $fournisseur): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;
        abort_unless($fournisseur->entreprise_id === $entreprise->id, 403);

        if ($fournisseur->source === 'comptaflow') {
            // Uniquement les champs spécifiques à Selflow
            $request->validate([
                'telephone'         => ['nullable', 'string', 'max:30'],
                'email'             => ['nullable', 'email', 'max:150'],
                'adresse'           => ['nullable', 'string', 'max:255'],
                'secteur'           => ['nullable', 'string', 'max:100'],
                'ncc'               => ['nullable', 'string', 'max:50'],
                'rccm'              => ['nullable', 'string', 'max:100'],
                'regime_imposition' => ['nullable', 'string', 'max:100'],
            ]);

            $fournisseur->update($request->only([
                'telephone', 'email', 'adresse', 'secteur', 'ncc', 'rccm', 'regime_imposition'
            ]));
        } else {
            // Tous les champs
            $request->validate([
                'nom'               => ['required', 'string', 'max:150'],
                'telephone'         => ['nullable', 'string', 'max:30'],
                'email'             => ['nullable', 'email', 'max:150'],
                'adresse'           => ['nullable', 'string', 'max:255'],
                'secteur'           => ['nullable', 'string', 'max:100'],
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
                    'regex:/^401[0-9]*$/',
                    \Illuminate\Validation\Rule::unique('fournisseurs', 'numero_tiers')->ignore($fournisseur->id)->where('entreprise_id', $entreprise->id)
                ],
            ]);

            $fournisseur->update($request->only([
                'nom', 'telephone', 'email', 'adresse', 'secteur', 'ncc', 'rccm', 'regime_imposition', 'compte_comptable', 'numero_tiers'
            ]));
        }

        return back()->with('succes', 'Fournisseur modifié avec succès.');
    }
}
