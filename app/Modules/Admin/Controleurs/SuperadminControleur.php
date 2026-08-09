<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Modules\Admin\Services\TrousseauEntrepriseService;

class SuperadminControleur
{
    /**
     * Afficher le tableau de bord SuperAdmin avec KPIs enrichis.
     */
    public function tableauDeBord(): View
    {
        // ── KPI Globaux ──
        $totalEntreprises   = Entreprise::count();
        $totalPdvs          = PointDeVente::count();
        $totalUtilisateurs  = Utilisateur::count();

        // ── Utilisateurs par rôle ──
        $totalAdmins    = Utilisateur::where('role', 'admin')->count();
        $totalCaissiers = Utilisateur::where('role', 'caissier')->count();
        $totalActifsJour = Utilisateur::whereDate('updated_at', today())->count();

        // ── PDV par entreprise (moyennes) ──
        $avgPdvParEntreprise = $totalEntreprises > 0
            ? round($totalPdvs / $totalEntreprises, 1)
            : 0;

        // ── Entreprises par plan d'abonnement ──
        $parPlan = Entreprise::select('plan_abonnement', DB::raw('count(*) as total'))
            ->groupBy('plan_abonnement')
            ->pluck('total', 'plan_abonnement')
            ->toArray();

        // ── Inscriptions des 6 derniers mois ──
        $inscriptionsParMois = Entreprise::select(
                DB::raw('YEAR(created_at) as annee'),
                DB::raw('MONTH(created_at) as mois'),
                DB::raw('count(*) as total')
            )
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('annee', 'mois')
            ->orderBy('annee')->orderBy('mois')
            ->get()
            ->map(fn($r) => [
                'label' => \Carbon\Carbon::createFromDate($r->annee, $r->mois, 1)->translatedFormat('M Y'),
                'total' => $r->total,
            ]);

        // ── Modules les plus utilisés ──
        $tousModules = [];
        Entreprise::whereNotNull('modules_actifs')->get()->each(function ($e) use (&$tousModules) {
            $mods = is_array($e->modules_actifs) ? $e->modules_actifs : json_decode($e->modules_actifs, true) ?? [];
            foreach ($mods as $m) {
                $tousModules[$m] = ($tousModules[$m] ?? 0) + 1;
            }
        });
        arsort($tousModules);
        $modulesPopulaires = array_slice($tousModules, 0, 6, true);

        // ── Entreprises récentes ──
        $entreprisesRecentes = Entreprise::withCount('pointsDeVente')
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        // ── Entreprises sans utilisateurs ──
        $entreprisesSansUsers = Entreprise::doesntHave('utilisateurs')->count();

        return view('admin::superadmin.tableau_de_bord', compact(
            'totalEntreprises',
            'totalPdvs',
            'totalUtilisateurs',
            'totalAdmins',
            'totalCaissiers',
            'totalActifsJour',
            'avgPdvParEntreprise',
            'parPlan',
            'inscriptionsParMois',
            'modulesPopulaires',
            'entreprisesRecentes',
            'entreprisesSansUsers'
        ));
    }

    /**
     * Liste complète des entreprises.
     */
    public function entreprises(): View
    {
        $entreprises = Entreprise::orderBy('nom', 'asc')->paginate(10);
        return view('admin::superadmin.entreprises.index', compact('entreprises'));
    }

    /**
     * Afficher le formulaire de création d'entreprise.
     */
    public function creerFormulaire(): View
    {
        return view('admin::superadmin.entreprises.creer');
    }

    /**
     * Enregistrer une nouvelle entreprise et créer son point de vente "Siège".
     */
    public function creer(Request $request): RedirectResponse
    {
        // Normaliser le NCC : suppression des espaces et mise en majuscule
        $request->merge(['ncc' => $request->has('ncc') ? strtoupper(preg_replace('/\s+/', '', $request->input('ncc'))) : null]);

        $request->validate([
            'nom'                     => ['required', 'string', 'max:150', 'unique:entreprises,nom'],
            'forme_juridique'         => ['nullable', 'string', 'max:50'],
            'gerant_nom'              => ['nullable', 'string', 'max:100'],
            'gerant_prenom'           => ['nullable', 'string', 'max:150'],
            'gerant_fonction'         => ['nullable', 'string', 'max:150'],
            'email'                   => ['nullable', 'email', 'max:150'],
            'telephone'               => ['nullable', 'string', 'max:30'],
            'adresse'                 => ['nullable', 'string', 'max:255'],
            'rccm'                    => ['nullable', 'string', 'max:100'],
            'ncc'                     => ['nullable', 'string', 'size:8', 'regex:/^[A-Z0-9]{7}[A-Z]$/'],
            'compte_contribuable'     => ['nullable', 'string', 'max:100'],
            'regime_imposition'       => ['nullable', 'string', 'max:80'],
            'quota_points_de_vente'   => ['required', 'integer', 'min:1'],
            'plan_abonnement'         => ['required', 'string'],
            'secteur_activite'        => ['required', 'array'],
            'secteur_activite.*'      => ['required', 'string', 'in:Commercial,Industriel,Services,Agricole,Artisanat,BTP / Construction,Restauration / Hôtellerie,Santé,Transport / Logistique,Technologies / Numérique,Éducation / Formation,Autre'],
            'modules_actifs'          => ['required', 'array'],
            // Champs COMPTAFLOW conditionnels
            'comptaflow_password'     => [$request->boolean('creer_compte_comptaflow') ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'],
        ]);

        // Tous les modules sont activés par défaut — l'admin peut en désactiver dans les paramètres
        $tousModules = ['principal', 'ventes', 'achats', 'stock', 'production', 'comptabilite', 'points_de_vente', 'personnel', 'b2b', 'fne'];
        $modulesChoisis = is_array($request->modules_actifs) ? $request->modules_actifs : [];
        $modules = array_unique(array_merge($tousModules, $modulesChoisis));

        // Créer l'entreprise
        $entreprise = Entreprise::create([
            'nom'                    => $request->nom,
            'forme_juridique'        => $request->forme_juridique,
            'gerant_nom'             => $request->gerant_nom,
            'gerant_prenom'          => $request->gerant_prenom,
            'gerant_fonction'        => $request->gerant_fonction,
            'email'                  => $request->email,
            'telephone'              => $request->telephone,
            'adresse'                => $request->adresse,
            'rccm'                   => $request->rccm,
            'ncc'                    => $request->ncc,
            'compte_contribuable'    => $request->compte_contribuable,
            'regime_imposition'      => $request->regime_imposition,
            'quota_points_de_vente'  => $request->quota_points_de_vente,
            'plan_abonnement'        => $request->plan_abonnement,
            'secteur_activite'       => $request->secteur_activite,
            'modules_actifs'         => $modules,
        ]);

        // Sans plan comptable ni journal, la premiere vente s'impute sur des
        // comptes inventes a la volee. L'entreprise recoit donc de quoi
        // travailler des le premier jour ; ce qui ne lui sert pas, elle
        // l'archivera.
        TrousseauEntrepriseService::doter($entreprise);

        // Création automatique du Siège comme point de vente par défaut
        PointDeVente::create([
            'entreprise_id' => $entreprise->id,
            'nom'           => 'Siège',
            'ville'         => $request->adresse ? explode(',', $request->adresse)[0] : 'Abidjan',
            'commune'       => 'Plateau',
            'responsable'   => 'Responsable Général',
            'telephone'     => $request->telephone,
            'statut'        => 'Ouvert',
        ]);

        // ── Liaison COMPTAFLOW (si case cochée) ──
        $messageSupplement = '';
        if ($request->boolean('creer_compte_comptaflow') && $request->filled('comptaflow_password')) {
            try {
                $syncKey = Str::random(40);
                $comptaflowUrl = config('selflow.comptaflow_api_url', 'http://127.0.0.1:8002');

                $response = Http::timeout(15)->post($comptaflowUrl . '/api/external/register-enterprise', [
                    'secret'         => config('selflow.comptaflow_api_secret'),
                    'company_name'   => $entreprise->nom,
                    'activity'       => implode(', ', $entreprise->secteur_activite ?? ['Commercial']),
                    'juridique_form' => $entreprise->forme_juridique ?? 'SARL',
                    'adresse'        => $entreprise->adresse,
                    'city'           => $entreprise->adresse ? explode(',', $entreprise->adresse)[0] : 'Abidjan',
                    'country'        => 'Côte d\'Ivoire',
                    'phone_number'   => $entreprise->telephone,
                    'email_adresse'  => $entreprise->email,
                    'ncc'            => $entreprise->ncc,
                    'rccm'           => $entreprise->rccm,
                    'compte_contribuable' => $entreprise->compte_contribuable,
                    'regime'         => $entreprise->regime_imposition,
                    'admin_nom'      => $entreprise->gerant_nom,
                    'admin_prenom'   => $entreprise->gerant_prenom,
                    'admin_password' => $request->comptaflow_password,
                    'selflow_company_id' => $entreprise->id,
                    'selflow_sync_key'   => $syncKey,
                ]);

                if ($response->successful() && $response->json('success')) {
                    $entreprise->update([
                        'comptaflow_company_id' => $response->json('company_id'),
                        'comptaflow_sync_key'   => $syncKey,
                        'comptaflow_sync_status' => 'active',
                        'comptaflow_last_sync_at' => now(),
                    ]);
                    $messageSupplement = ' Le compte COMPTAFLOW a été créé et lié avec succès.';
                } else {
                    $messageSupplement = ' ⚠️ Avertissement : L\'entreprise Selflow a été créée, mais la création du compte COMPTAFLOW a échoué (' . ($response->json('message') ?? 'Erreur inconnue') . ').';
                    Log::warning('COMPTAFLOW register-enterprise failed', ['response' => $response->json()]);
                }
            } catch (\Exception $e) {
                $messageSupplement = ' ⚠️ Avertissement : Impossible de contacter COMPTAFLOW (' . $e->getMessage() . ').';
                Log::error('COMPTAFLOW register-enterprise exception', ['error' => $e->getMessage()]);
            }
        }

        return redirect()->route('superadmin.entreprises')->with('succes', 'Entreprise et son point de vente "Siège" créés avec succès.' . $messageSupplement);
    }

    /**
     * Afficher le formulaire de modification d'entreprise.
     */
    public function modifierFormulaire(Entreprise $entreprise): View
    {
        return view('admin::superadmin.entreprises.modifier', compact('entreprise'));
    }

    /**
     * Mettre à jour le secteur d'activité et les modules actifs de l'entreprise.
     */
    public function modifier(Request $request, Entreprise $entreprise): RedirectResponse
    {
        $request->validate([
            'nom'                     => ['required', 'string', 'max:150', 'unique:entreprises,nom,' . $entreprise->id],
            'gerant_nom'              => ['nullable', 'string', 'max:100'],
            'gerant_prenom'           => ['nullable', 'string', 'max:150'],
            'gerant_fonction'         => ['nullable', 'string', 'max:150'],
            'quota_points_de_vente'   => ['required', 'integer', 'min:1'],
            'plan_abonnement'         => ['required', 'string'],
            'secteur_activite'        => ['required', 'array'],
            'secteur_activite.*'      => ['required', 'string', 'in:Commercial,Industriel,Services,Agricole,Artisanat,BTP / Construction,Restauration / Hôtellerie,Santé,Transport / Logistique,Technologies / Numérique,Éducation / Formation,Autre'],
            'modules_actifs'          => ['required', 'array'],
        ]);

        // Conserver tous les modules activés par défaut même lors d'une mise à jour
        $tousModules = ['principal', 'ventes', 'achats', 'stock', 'production', 'comptabilite', 'points_de_vente', 'personnel', 'b2b', 'fne'];
        $modulesChoisis = is_array($request->modules_actifs) ? $request->modules_actifs : [];
        $modules = array_unique(array_merge($tousModules, $modulesChoisis));

        $entreprise->update([
            'nom'                    => $request->nom,
            'gerant_nom'             => $request->gerant_nom,
            'gerant_prenom'          => $request->gerant_prenom,
            'gerant_fonction'        => $request->gerant_fonction,
            'quota_points_de_vente'  => $request->quota_points_de_vente,
            'plan_abonnement'        => $request->plan_abonnement,
            'secteur_activite'       => $request->secteur_activite,
            'modules_actifs'         => $modules,
        ]);

        return redirect()->route('superadmin.entreprises')->with('succes', 'Entreprise mise à jour avec succès.');
    }

    /**
     * Activer ou Bloquer une entreprise.
     */
    public function toggleStatus(Entreprise $entreprise): RedirectResponse
    {
        $nouveauStatut = $entreprise->statut === 'bloque' ? 'actif' : 'bloque';
        $entreprise->update(['statut' => $nouveauStatut]);
        
        $message = $nouveauStatut === 'bloque' 
            ? "L'entreprise « {$entreprise->nom} » et tous ses utilisateurs ont été bloqués avec succès."
            : "L'entreprise « {$entreprise->nom} » a été réactivée.";

        return redirect()->route('superadmin.entreprises')->with('succes', $message);
    }

    /**
     * Supprimer une entreprise.
     */
    public function supprimer(Entreprise $entreprise): RedirectResponse
    {
        $nom = $entreprise->nom;
        
        // Supprimer les entités enfants associées
        $entreprise->utilisateurs()->delete();
        $entreprise->pointsDeVente()->delete();
        $entreprise->delete();

        return redirect()->route('superadmin.entreprises')->with('succes', "L'entreprise « {$nom} » a été supprimée avec succès.");
    }

    /**
     * Liste complète des utilisateurs de toutes les entreprises.
     */
    public function utilisateurs(Request $request): View
    {
        $query = Utilisateur::with('entreprise');

        // Filtrer par nom/email d'utilisateur
        if ($request->filled('recherche_utilisateur')) {
            $search = $request->recherche_utilisateur;
            $query->where(function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Filtrer par nom d'entreprise
        if ($request->filled('recherche_entreprise')) {
            $search = $request->recherche_entreprise;
            $query->whereHas('entreprise', function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%");
            });
        }

        $utilisateurs = $query->orderBy('created_at', 'desc')->paginate(15);
        $entreprises = Entreprise::orderBy('nom')->get();

        return view('admin::superadmin.utilisateurs.index', compact('utilisateurs', 'entreprises'));
    }

    public function modifierUtilisateur(Request $request, Utilisateur $utilisateur): RedirectResponse
    {
        $request->validate([
            'role'          => ['required', 'string', 'in:superadmin,admin,admin_secondaire,responsable_pdv,caissier'],
            'statut'        => ['required', 'string', 'in:actif,suspendu,inactif'],
            'habilitations' => ['nullable', 'array'],
            'password'      => ['nullable', 'string', 'min:6'],
        ]);

        $data = [
            'role'          => $request->role,
            'statut'        => $request->statut,
            'habilitations' => $request->habilitations ?? [],
        ];

        if ($request->filled('password')) {
            $data['password'] = \Illuminate\Support\Facades\Hash::make($request->password);
        }

        $utilisateur->update($data);

        return redirect()->back()->with('succes', "L'utilisateur « {$utilisateur->nom} {$utilisateur->prenom} » a été mis à jour avec succès.");
    }

    // ─────────────────────────────────────────────────────────────
    // ADMINISTRATION INTERNE (SUPERADMINS CRUD)
    // ─────────────────────────────────────────────────────────────

    public function admins(Request $request): View
    {
        $query = Utilisateur::where('role', 'superadmin');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenom', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $admins = $query->orderBy('created_at', 'desc')->paginate(15);
        return view('admin::superadmin.admins.index', compact('admins'));
    }

    public function creerAdmin(): View
    {
        return view('admin::superadmin.admins.creer');
    }

    public function enregistrerAdmin(Request $request): RedirectResponse
    {
        $request->validate([
            'nom'           => ['required', 'string', 'max:100'],
            'prenom'        => ['required', 'string', 'max:150'],
            'email'         => ['required', 'email', 'max:150', 'unique:utilisateurs,email'],
            'password'      => ['required', 'string', 'min:8', 'confirmed'],
            'habilitations' => ['nullable', 'array'],
            'statut'        => ['required', 'string', 'in:actif,inactif'],
        ]);

        Utilisateur::create([
            'entreprise_id'         => null,
            'nom'                   => trim($request->nom),
            'prenom'                => trim($request->prenom),
            'email'                 => trim($request->email),
            'password'              => \Illuminate\Support\Facades\Hash::make($request->password),
            'role'                  => 'superadmin',
            'statut'                => $request->statut,
            'habilitations'         => $request->habilitations ?? [],
            'doit_changer_password' => false,
        ]);

        return redirect()->route('superadmin.admins.index')->with('succes', 'Administrateur interne créé avec succès.');
    }

    public function modifierAdmin(Utilisateur $utilisateur): View
    {
        return view('admin::superadmin.admins.modifier', compact('utilisateur'));
    }

    public function mettreAJourAdmin(Request $request, Utilisateur $utilisateur): RedirectResponse
    {
        $request->validate([
            'nom'           => ['required', 'string', 'max:100'],
            'prenom'        => ['required', 'string', 'max:150'],
            'email'         => ['required', 'email', 'max:150', 'unique:utilisateurs,email,' . $utilisateur->id],
            'password'      => ['nullable', 'string', 'min:8', 'confirmed'],
            'habilitations' => ['nullable', 'array'],
            'statut'        => ['required', 'string', 'in:actif,inactif'],
        ]);

        $data = [
            'nom'           => trim($request->nom),
            'prenom'        => trim($request->prenom),
            'email'         => trim($request->email),
            'statut'        => $request->statut,
            'habilitations' => $request->habilitations ?? [],
        ];

        if ($utilisateur->email === 'superadmin@gmail.com') {
            $data['habilitations'] = [];
        }

        if ($request->filled('password')) {
            $data['password'] = \Illuminate\Support\Facades\Hash::make($request->password);
        }

        $utilisateur->update($data);

        return redirect()->route('superadmin.admins.index')->with('succes', 'Administrateur interne mis à jour avec succès.');
    }

    public function supprimerAdmin(Utilisateur $utilisateur): RedirectResponse
    {
        if ($utilisateur->id === Auth::id()) {
            return redirect()->back()->with('erreur', 'Vous ne pouvez pas vous supprimer vous-même.');
        }

        if ($utilisateur->email === 'superadmin@gmail.com') {
            return redirect()->back()->with('erreur', 'Le super-administrateur principal ne peut pas être supprimé.');
        }

        $utilisateur->delete();

        return redirect()->route('superadmin.admins.index')->with('succes', 'Administrateur interne supprimé avec succès.');
    }

    // ─────────────────────────────────────────────────────────────
    // CONFIGURATION PLATEFORME — SECTEURS ↔ MODULES
    // ─────────────────────────────────────────────────────────────

    /** Définition des 10 modules de la plateforme */
    public static function tousLesModules(): array
    {
        return [
            'principal'      => ['label' => 'Tableau de bord',     'icone' => 'fa-chart-pie',          'par_defaut' => true,  'desactivable' => true],
            'ventes'         => ['label' => 'Ventes & Facturation', 'icone' => 'fa-cash-register',      'par_defaut' => true,  'desactivable' => true],
            'achats'         => ['label' => 'Achats',               'icone' => 'fa-cart-plus',          'par_defaut' => true,  'desactivable' => true],
            'stock'          => ['label' => 'Stock',                'icone' => 'fa-boxes-stacked',      'par_defaut' => true,  'desactivable' => true],
            'production'     => ['label' => 'Production',           'icone' => 'fa-industry',           'par_defaut' => true,  'desactivable' => true],
            'comptabilite'   => ['label' => 'Comptabilité & FNE',   'icone' => 'fa-book',               'par_defaut' => true,  'desactivable' => true],
            'points_de_vente'=> ['label' => 'Points de vente',      'icone' => 'fa-store',              'par_defaut' => true,  'desactivable' => true],
            'personnel'      => ['label' => 'Personnels & Accès',   'icone' => 'fa-users-gear',         'par_defaut' => true,  'desactivable' => true],
            'b2b'            => ['label' => 'Communication B2B',    'icone' => 'fa-handshake',          'par_defaut' => true,  'desactivable' => true],
            'fne'            => ['label' => 'Fiscalité DGI',        'icone' => 'fa-file-invoice-dollar','par_defaut' => true,  'desactivable' => true],
        ];
    }

    /** Définition des 12 secteurs d'activité */
    public static function tousLesSecteurs(): array
    {
        return [
            'Commercial'         => ['icone' => 'fa-tag',           'couleur' => '#3b82f6'],
            'Industriel'         => ['icone' => 'fa-industry',      'couleur' => '#6366f1'],
            'Services'           => ['icone' => 'fa-handshake',     'couleur' => '#10b981'],
            'Agricole'           => ['icone' => 'fa-seedling',      'couleur' => '#84cc16'],
            'Artisanat'          => ['icone' => 'fa-hammer',        'couleur' => '#f59e0b'],
            'BTP / Construction' => ['icone' => 'fa-helmet-safety', 'couleur' => '#f97316'],
            'Restauration / Hôtellerie' => ['icone' => 'fa-utensils', 'couleur' => '#ef4444'],
            'Santé'              => ['icone' => 'fa-stethoscope',   'couleur' => '#ec4899'],
            'Transport / Logistique'    => ['icone' => 'fa-truck',  'couleur' => '#8b5cf6'],
            'Technologies / Numérique'  => ['icone' => 'fa-microchip', 'couleur' => '#06b6d4'],
            'Éducation / Formation'     => ['icone' => 'fa-graduation-cap', 'couleur' => '#0ea5e9'],
            'Autre'              => ['icone' => 'fa-circle-dot',    'couleur' => '#94a3b8'],
        ];
    }

    /** Chemin du fichier de config JSON */
    private function cheminConfig(): string
    {
        return storage_path('app/secteurs_modules.json');
    }

    /** Charger la configuration courante */
    private function chargerConfig(): array
    {
        $chemin = $this->cheminConfig();
        if (!file_exists($chemin)) {
            // Config par défaut : tous les modules actifs pour tous les secteurs
            $tousModules = array_keys(self::tousLesModules());
            $defaut = [];
            foreach (array_keys(self::tousLesSecteurs()) as $secteur) {
                $defaut[$secteur] = $tousModules;
            }
            $defaut['__defaut__'] = $tousModules; // modules activés pour une entreprise sans secteur
            return $defaut;
        }
        return json_decode(file_get_contents($chemin), true) ?? [];
    }

    public function secteursModules(): View
    {
        $modules = self::tousLesModules();
        $secteurs = self::tousLesSecteurs();
        $config = $this->chargerConfig();

        return view('admin::superadmin.secteurs_modules.index', compact('modules', 'secteurs', 'config'));
    }

    public function sauvegarderSecteursModules(Request $request): RedirectResponse
    {
        $tousModules = array_keys(self::tousLesModules());
        $tousSecteurs = array_merge(['__defaut__'], array_keys(self::tousLesSecteurs()));

        $config = [];
        foreach ($tousSecteurs as $secteur) {
            $cle = str_replace(['/', ' '], '_', $secteur);
            $modulesCoches = $request->input('modules_' . $cle, []);
            // Filtrer pour ne garder que des modules valides
            $config[$secteur] = array_values(array_intersect($modulesCoches, $tousModules));
        }

        file_put_contents($this->cheminConfig(), json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return redirect()->route('superadmin.secteurs_modules.index')->with('succes', 'Configuration Secteurs ↔ Modules sauvegardée avec succès.');
    }
}
