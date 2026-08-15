<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Services\NumerotationTiersService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ClientControleur
{
    /**
     * Le numéro saisi à la main, une fois validé.
     *
     * Deux contrôles que rien n'exerçait : il doit suivre la racine du compte
     * de rattachement — un tiers `411002` rattaché au `401000` faisait partir
     * l'écriture sur le collectif fournisseurs — et il ne doit pas être ce
     * compte collectif lui-même.
     */
    private function numeroSaisi(Request $request, Entreprise $entreprise, string $racine, string $compteGeneral): string
    {
        $request->validate([
            'numero_tiers' => array_merge(
                NumerotationTiersService::reglesDeSaisie($racine),
                [
                    'not_in:' . $compteGeneral,
                    \Illuminate\Validation\Rule::unique('clients', 'numero_tiers')->where('entreprise_id', $entreprise->id),
                ]
            ),
        ], [
            'numero_tiers.required' => 'Le numéro de tiers est obligatoire si la numérotation automatique n\'est pas cochée.',
            'numero_tiers.regex'    => "Le numéro de tiers doit commencer par « {$racine} », la racine du compte de rattachement — par exemple {$racine}001 ou {$racine}KONE.",
            'numero_tiers.not_in'   => "Le numéro de tiers ne peut pas être {$compteGeneral} : c'est le compte collectif, pas la fiche de ce client.",
            'numero_tiers.unique'   => 'Ce numéro de tiers est déjà utilisé.',
        ]);

        return (string) $request->input('numero_tiers');
    }

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
        // Normaliser le NCC : suppression des espaces et mise en majuscule
        $request->merge(['ncc' => $request->has('ncc') ? strtoupper(preg_replace('/\s+/', '', $request->input('ncc'))) : null]);

        $request->validate([
            'nom'               => ['required', 'string', 'max:150'],
            'type_facturation'  => ['nullable', 'in:B2B,B2C,B2G,B2F'],
            'telephone'         => ['nullable', 'string', 'max:30'],
            'email'             => ['nullable', 'email', 'max:150'],
            'adresse'           => ['nullable', 'string', 'max:255'],
            'ncc'               => ['required_if:type_facturation,B2B', 'nullable', 'string', 'size:8', 'regex:/^[A-Z0-9]{7}[A-Z]$/'],
            'rccm'              => ['nullable', 'string', 'max:100'],
            'regime_imposition' => ['nullable', 'string', 'max:100'],
            'compte_comptable'  => [
                'required',
                'string',
                \Illuminate\Validation\Rule::exists('plan_comptable', 'numero')->where(function ($q) use ($entreprise) {
                    $q->whereNull('entreprise_id')->orWhere('entreprise_id', $entreprise->id);
                })
            ],
        ], [
            'ncc.required_if' => 'Le NCC est obligatoire pour un client de type B2B (Entreprise à Entreprise).',
            'ncc.size' => 'Le NCC doit contenir exactement 8 caractères.',
            'ncc.regex' => 'Le NCC doit comporter 8 caractères et se terminer par une lettre majuscule.',
        ]);

        // **Le numéro de tiers n'est pas le compte général.** `411000` est le
        // compte collectif « Clients » du plan comptable ; `411001` ou
        // `411KONE` désigne *ce* client-ci. La numérotation automatique
        // démarrait pourtant à `411000` : le premier client de chaque
        // entreprise portait le collectif comme numéro de tiers, et son relevé
        // remontait le solde de tous les autres.
        $compteGeneral = $request->input('compte_comptable');
        $racine        = NumerotationTiersService::racine($compteGeneral);

        $numeroTiers = $request->boolean('auto_numero_tiers')
            ? NumerotationTiersService::pourClient($entreprise, $compteGeneral, (string) $request->input('nom'))
            : $this->numeroSaisi($request, $entreprise, $racine, $compteGeneral);

        Client::create(array_merge(
            $request->only(['nom', 'type_facturation', 'telephone', 'email', 'adresse', 'ncc', 'rccm', 'regime_imposition', 'compte_comptable']),
            [
                'entreprise_id' => $entreprise->id,
                'numero_tiers'  => $numeroTiers,
                // Si pas B2B, vider le NCC pour cohérence
                'ncc'           => ($request->input('type_facturation') === 'B2B') ? $request->input('ncc') : null,
            ]
        ));

        return back()->with('succes', 'Client ajouté avec succès.');
    }

    public function modifier(Request $request, Client $client): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;
        abort_unless($client->entreprise_id === $entreprise->id, 404);

        if ($client->source === 'comptaflow') {
            // Uniquement les champs spécifiques à Selflow
            // Normaliser le NCC en entrée
            $request->merge(['ncc' => $request->has('ncc') ? strtoupper(preg_replace('/\s+/', '', $request->input('ncc'))) : null]);
            $request->validate([
                'type_facturation'  => ['nullable', 'in:B2B,B2C,B2G,B2F'],
                'telephone'         => ['nullable', 'string', 'max:30'],
                'email'             => ['nullable', 'email', 'max:150'],
                'adresse'           => ['nullable', 'string', 'max:255'],
'ncc'               => ['required_if:type_facturation,B2B', 'nullable', 'string', 'size:8', 'regex:/^[A-Z0-9]{7}[A-Z]$/'],
            'rccm'              => ['nullable', 'string', 'max:100'],
            'regime_imposition' => ['nullable', 'string', 'max:100'],
                ], [
                'ncc.required_if' => 'Le NCC est obligatoire pour un client de type B2B.',
                'ncc.size' => 'Le NCC doit contenir exactement 8 caractères.',
                'ncc.regex' => 'Le NCC doit comporter 8 caractères et se terminer par une lettre majuscule.',
            ]);

            $client->update(array_merge(
                $request->only(['type_facturation', 'telephone', 'email', 'adresse', 'rccm', 'regime_imposition']),
                ['ncc' => ($request->input('type_facturation') === 'B2B') ? $request->input('ncc') : null]
            ));
        } else {
            // Tous les champs
            // Normaliser le NCC en entrée
            $request->merge(['ncc' => $request->has('ncc') ? strtoupper(preg_replace('/\s+/', '', $request->input('ncc'))) : null]);
            $request->validate([
                'nom'               => ['required', 'string', 'max:150'],
                'type_facturation'  => ['nullable', 'in:B2B,B2C,B2G,B2F'],
                'telephone'         => ['nullable', 'string', 'max:30'],
                'email'             => ['nullable', 'email', 'max:150'],
                'adresse'           => ['nullable', 'string', 'max:255'],
                'ncc'               => ['required_if:type_facturation,B2B', 'nullable', 'string', 'size:8', 'regex:/^[A-Z0-9]{7}[A-Z]$/'],
                'rccm'              => ['nullable', 'string', 'max:100'],
                'regime_imposition' => ['nullable', 'string', 'max:100'],
                'compte_comptable'  => [
                    'required',
                    'string',
                    \Illuminate\Validation\Rule::exists('plan_comptable', 'numero')->where(function ($q) use ($entreprise) {
                        $q->whereNull('entreprise_id')->orWhere('entreprise_id', $entreprise->id);
                    })
                ],
                // Le numéro suit la racine du compte de rattachement, et n'est
                // jamais le compte collectif lui-même. Les lettres sont
                // admises : `411KONE` est une convention répandue, que
                // l'ancienne expression `^411[0-9]*$` refusait.
                'numero_tiers'      => array_merge(
                    NumerotationTiersService::reglesDeSaisie(
                        NumerotationTiersService::racine((string) $request->input('compte_comptable'))
                    ),
                    [
                        'not_in:' . $request->input('compte_comptable'),
                        \Illuminate\Validation\Rule::unique('clients', 'numero_tiers')->ignore($client->id)->where('entreprise_id', $entreprise->id),
                    ]
                ),
            ], [
                'numero_tiers.regex'  => 'Le numéro de tiers doit commencer par la racine du compte de rattachement (par exemple 411001 ou 411KONE pour un client rattaché au 411000).',
                'numero_tiers.not_in' => 'Le numéro de tiers ne peut pas être le compte collectif lui-même : ce sont deux choses différentes.',
                'ncc.required_if' => 'Le NCC est obligatoire pour un client de type B2B.',
                'ncc.size' => 'Le NCC doit contenir exactement 8 caractères.',
                'ncc.regex' => 'Le NCC doit comporter 8 caractères et se terminer par une lettre majuscule.',
            ]);

            $client->update(array_merge(
                $request->only(['nom', 'type_facturation', 'telephone', 'email', 'adresse', 'rccm', 'regime_imposition', 'compte_comptable', 'numero_tiers']),
                ['ncc' => ($request->input('type_facturation') === 'B2B') ? $request->input('ncc') : null]
            ));
        }

        return back()->with('succes', 'Client modifié avec succès.');
    }

    public function supprimer(Request $request, Client $client): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;
        abort_unless($client->entreprise_id === $entreprise->id, 404);

        if ($client->ventes_count > 0 || $client->ventes()->exists()) {
            return back()->with('erreur', 'Impossible de supprimer ce client : il est lié à des ventes enregistrées.');
        }

        $client->delete();
        return back()->with('succes', 'Client supprimé avec succès.');
    }
}
