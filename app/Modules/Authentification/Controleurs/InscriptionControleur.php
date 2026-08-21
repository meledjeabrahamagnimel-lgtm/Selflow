<?php

namespace App\Modules\Authentification\Controleurs;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\Referentiel\Categorie;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use App\Modules\Admin\Services\TrousseauEntrepriseService;

class InscriptionControleur
{
    /** Afficher le formulaire d'inscription. */
    public function afficher(): View
    {
        return view('authentification::inscription', [
            'domaines' => Categorie::domaines(),
        ]);
    }

    /** Traiter la soumission du formulaire d'inscription. */
    public function inscrire(Request $request): RedirectResponse
    {
        $request->validate([
            'nom_entreprise'      => ['required', 'string', 'max:150'],
            'regime_imposition'   => ['required', 'string', 'in:TEE,RNE,RSI,RNI'],
            'nom'                 => ['required', 'string', 'max:100'],
            'prenom'              => ['required', 'string', 'max:100'],
            'email'               => ['required', 'string', 'email', 'max:191', 'unique:utilisateurs,email'],
            'password'            => ['required', 'string', 'min:8', 'confirmed'],
            'conditions'          => ['accepted'],
            // Optionnels
            'adresse'             => ['nullable', 'string', 'max:255'],
            'telephone'           => ['nullable', 'string', 'max:30'],
            'rccm'                => ['nullable', 'string', 'max:100'],
            'compte_contribuable' => ['nullable', 'string', 'max:100'],
            'forme_juridique'     => ['nullable', 'string', 'max:60'],
            // Le domaine se choisit dans le référentiel, pas au clavier : une
            // valeur libre ne correspondrait à aucune catégorie, et l'étape 1
            // de la souscription ne saurait pas la retrouver.
            'secteurs_activite'   => ['nullable', 'array'],
            'secteurs_activite.*' => ['nullable', 'string', Rule::in(Categorie::domaines())],
            'fonction_gerant'     => ['nullable', 'string', 'max:100'],
            'telephone_gerant'    => ['nullable', 'string', 'max:30'],
        ], [
            'nom_entreprise.required'    => 'Le nom de votre entreprise est obligatoire.',
            'regime_imposition.required' => 'Le régime d\'imposition est obligatoire.',
            'regime_imposition.in'       => 'Régime invalide. Choisissez TEE, RNE, RSI ou RNI.',
            'nom.required'               => 'Votre nom est obligatoire.',
            'prenom.required'            => 'Votre prénom est obligatoire.',
            'email.required'             => 'L\'adresse email est obligatoire.',
            'email.email'                => 'L\'adresse email n\'est pas valide.',
            'email.unique'               => 'Cette adresse email est déjà utilisée.',
            'password.required'          => 'Le mot de passe est obligatoire.',
            'password.min'               => 'Le mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed'         => 'La confirmation du mot de passe ne correspond pas.',
            'conditions.accepted'        => 'Vous devez accepter les conditions d\'utilisation.',
        ]);

        DB::beginTransaction();
        try {
            // 1. Créer l'entreprise avec tous les champs
            $entreprise = Entreprise::create([
                'nom'                 => trim($request->nom_entreprise),
                'email'               => trim($request->email),
                'telephone'           => trim($request->telephone ?? ''),
                'adresse'             => trim($request->adresse ?? ''),
                'rccm'                => trim($request->rccm ?? ''),
                'compte_contribuable' => trim($request->compte_contribuable ?? ''),
                'forme_juridique'     => trim($request->forme_juridique ?? ''),
                'regime_imposition'   => $request->regime_imposition,
                'secteur_activite'    => $request->secteurs_activite ?? [],
                'gerant_nom'          => trim($request->nom),
                'gerant_prenom'       => trim($request->prenom),
                'gerant_fonction'     => trim($request->fonction_gerant ?? ''),
                'modules_actifs'      => ['principal', 'ventes', 'achats', 'stock', 'production', 'comptabilite', 'points_de_vente', 'personnel', 'b2b', 'fne'],
                'statut'              => 'actif',
                'quota_points_de_vente' => 5,
                'plan_abonnement'     => 'Starter',
            ]);

            // Sans plan comptable ni journal, la premiere vente s'impute sur des
            // comptes inventes a la volee. L'entreprise recoit donc de quoi
            // travailler des le premier jour ; ce qui ne lui sert pas, elle
            // l'archivera.
            TrousseauEntrepriseService::doter($entreprise);

            // 2. Créer l'utilisateur admin principal
            $utilisateur = Utilisateur::create([
                'entreprise_id'         => $entreprise->id,
                'nom'                   => trim($request->nom),
                'prenom'                => trim($request->prenom),
                'email'                 => trim($request->email),
                'password'              => Hash::make($request->password),
                'role'                  => 'admin',
                'fonction'              => trim($request->fonction_gerant ?? ''),
                'statut'                => 'actif',
                'doit_changer_password' => false,
            ]);

            DB::commit();

            // 3. Connecter directement
            Auth::login($utilisateur);
            $request->session()->regenerate();

            return redirect()->route('admin.tableau_de_bord')
                ->with('succes', 'Bienvenue sur Selflow ! Votre espace est prêt. Commencez par configurer votre entreprise.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()
                ->withErrors(['inscription_erreur' => 'Erreur lors de la création : ' . $e->getMessage()])
                ->withInput($request->except('password', 'password_confirmation'));
        }
    }
}
