<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\Fournisseur;
use App\Modules\Admin\Services\NumerotationTiersService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class FournisseurControleur
{
    /**
     * Le numéro saisi à la main, une fois validé.
     *
     * Il doit suivre la racine du compte de rattachement, et ne jamais être ce
     * compte collectif lui-même. Voir `NumerotationTiersService` pour ce que
     * ces deux notions recouvrent.
     */
    private function numeroSaisi(Request $request, Entreprise $entreprise, string $racine, string $compteGeneral): string
    {
        $request->validate([
            'numero_tiers' => array_merge(
                NumerotationTiersService::reglesDeSaisie($racine),
                [
                    'not_in:' . $compteGeneral,
                    \Illuminate\Validation\Rule::unique('fournisseurs', 'numero_tiers')->where('entreprise_id', $entreprise->id),
                ]
            ),
        ], [
            'numero_tiers.required' => 'Le numéro de tiers est obligatoire si la numérotation automatique n\'est pas cochée.',
            'numero_tiers.regex'    => "Le numéro de tiers doit commencer par « {$racine} », la racine du compte de rattachement — par exemple {$racine}001 ou {$racine}KOFFI.",
            'numero_tiers.not_in'   => "Le numéro de tiers ne peut pas être {$compteGeneral} : c'est le compte collectif, pas la fiche de ce fournisseur.",
            'numero_tiers.unique'   => 'Ce numéro de tiers est déjà utilisé.',
        ]);

        return (string) $request->input('numero_tiers');
    }

    public function index(Request $request): View
    {
        $entreprise   = Auth::user()->entreprise;
        $search = $request->input('search', '');

        $query = Fournisseur::where('entreprise_id', $entreprise->id)
            ->where(function ($q) {
                $q->where('source', '!=', 'comptaflow')
                  ->orWhereNull('source');
            })
            ->withCount('achats')
            ->orderBy('nom');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('numero_tiers', 'like', "%{$search}%")
                  ->orWhere('ncc', 'like', "%{$search}%")
                  ->orWhere('secteur', 'like', "%{$search}%");
            });
        }

        $fournisseurs = $query->paginate(15, ['*'], 'page_local')->withQueryString();

        $fournisseursComptaflow = Fournisseur::where('entreprise_id', $entreprise->id)
             ->where('source', 'comptaflow')
             ->withCount('achats')
             ->orderBy('nom')
             ->paginate(15, ['*'], 'page_comptaflow');

        $comptes = \App\Modules\Admin\Modeles\PlanComptable::obtenirComptesPrioritaires($entreprise->id);

        return view('admin::fournisseurs.index', compact('fournisseurs', 'fournisseursComptaflow', 'comptes', 'entreprise', 'search'));
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
            'secteur'           => ['nullable', 'string', 'max:100'],
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
            'ncc.required_if' => 'Le NCC est obligatoire pour un fournisseur de type B2B (Entreprise à Entreprise).',
            'ncc.size' => 'Le NCC doit contenir exactement 8 caractères.',
            'ncc.regex' => 'Le NCC doit comporter 8 caractères et se terminer par une lettre majuscule.',
        ]);

        // **Le numéro de tiers n'est pas le compte général.** `401000` est le
        // compte collectif « Fournisseurs » ; `401001` ou `401KOFFI` désigne
        // *ce* fournisseur-ci. La numérotation automatique démarrait pourtant
        // à `401000`.
        $compteGeneral = $request->input('compte_comptable');
        $racine        = NumerotationTiersService::racine($compteGeneral);

        $numeroTiers = $request->boolean('auto_numero_tiers')
            ? NumerotationTiersService::pourFournisseur($entreprise, $compteGeneral, (string) $request->input('nom'))
            : $this->numeroSaisi($request, $entreprise, $racine, $compteGeneral);

        Fournisseur::create(array_merge(
            $request->only(['nom', 'type_facturation', 'telephone', 'email', 'adresse', 'secteur', 'rccm', 'regime_imposition', 'compte_comptable']),
            [
                'entreprise_id' => $entreprise->id,
                'numero_tiers'  => $numeroTiers,
                'ncc'           => ($request->input('type_facturation') === 'B2B') ? $request->input('ncc') : null,
            ]
        ));

        return back()->with('succes', 'Fournisseur ajouté avec succès.');
    }

    public function modifier(Request $request, Fournisseur $fournisseur): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;
        abort_unless($fournisseur->entreprise_id === $entreprise->id, 404);

        if ($fournisseur->source === 'comptaflow') {
            // Normaliser le NCC en entrée
            $request->merge(['ncc' => $request->has('ncc') ? strtoupper(preg_replace('/\s+/', '', $request->input('ncc'))) : null]);
            $request->validate([
                'type_facturation'  => ['nullable', 'in:B2B,B2C,B2G,B2F'],
                'telephone'         => ['nullable', 'string', 'max:30'],
                'email'             => ['nullable', 'email', 'max:150'],
                'adresse'           => ['nullable', 'string', 'max:255'],
                'secteur'           => ['nullable', 'string', 'max:100'],
                'ncc'               => ['required_if:type_facturation,B2B', 'nullable', 'string', 'size:8', 'regex:/^[A-Z0-9]{7}[A-Z]$/'],
                'rccm'              => ['nullable', 'string', 'max:100'],
                'regime_imposition' => ['nullable', 'string', 'max:100'],
            ], [
                'ncc.required_if' => 'Le NCC est obligatoire pour un fournisseur de type B2B.',
                'ncc.size' => 'Le NCC doit contenir exactement 8 caractères.',
                'ncc.regex' => 'Le NCC doit comporter 8 caractères et se terminer par une lettre majuscule.',
            ]);

            $fournisseur->update(array_merge(
                $request->only(['type_facturation', 'telephone', 'email', 'adresse', 'secteur', 'rccm', 'regime_imposition']),
                ['ncc' => ($request->input('type_facturation') === 'B2B') ? $request->input('ncc') : null]
            ));
        } else {
            // Normaliser le NCC en entrée
            $request->merge(['ncc' => $request->has('ncc') ? strtoupper(preg_replace('/\s+/', '', $request->input('ncc'))) : null]);

            $request->validate([
                'nom'               => ['required', 'string', 'max:150'],
                'type_facturation'  => ['nullable', 'in:B2B,B2C,B2G,B2F'],
                'telephone'         => ['nullable', 'string', 'max:30'],
                'email'             => ['nullable', 'email', 'max:150'],
                'adresse'           => ['nullable', 'string', 'max:255'],
                'secteur'           => ['nullable', 'string', 'max:100'],
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
                'numero_tiers'      => array_merge(
                    NumerotationTiersService::reglesDeSaisie(
                        NumerotationTiersService::racine((string) $request->input('compte_comptable'))
                    ),
                    [
                        'not_in:' . $request->input('compte_comptable'),
                        \Illuminate\Validation\Rule::unique('fournisseurs', 'numero_tiers')->ignore($fournisseur->id)->where('entreprise_id', $entreprise->id),
                    ]
                ),
            ], [
                'numero_tiers.regex'  => 'Le numéro de tiers doit commencer par la racine du compte de rattachement (par exemple 401001 ou 401KOFFI pour un fournisseur rattaché au 401000).',
                'numero_tiers.not_in' => 'Le numéro de tiers ne peut pas être le compte collectif lui-même : ce sont deux choses différentes.',
                'ncc.required_if' => 'Le NCC est obligatoire pour un fournisseur de type B2B.',
                'ncc.size' => 'Le NCC doit contenir exactement 8 caractères.',
                'ncc.regex' => 'Le NCC doit comporter 8 caractères et se terminer par une lettre majuscule.',
            ]);

            $fournisseur->update(array_merge(
                $request->only(['nom', 'type_facturation', 'telephone', 'email', 'adresse', 'secteur', 'rccm', 'regime_imposition', 'compte_comptable', 'numero_tiers']),
                ['ncc' => ($request->input('type_facturation') === 'B2B') ? $request->input('ncc') : null]
            ));
        }

        return back()->with('succes', 'Fournisseur modifié avec succès.');
    }

    public function supprimer(Request $request, Fournisseur $fournisseur): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;
        abort_unless($fournisseur->entreprise_id === $entreprise->id, 404);

        if ($fournisseur->achats()->exists()) {
            return back()->with('erreur', 'Impossible de supprimer ce fournisseur : il est lié à des achats enregistrés.');
        }

        $fournisseur->delete();
        return back()->with('succes', 'Fournisseur supprimé avec succès.');
    }
}
