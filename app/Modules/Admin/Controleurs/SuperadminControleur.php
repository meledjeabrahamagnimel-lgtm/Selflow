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
            'ncc'                     => ['nullable', 'string', 'max:50'],
            'compte_contribuable'     => ['nullable', 'string', 'max:100'],
            'regime_imposition'       => ['nullable', 'string', 'max:80'],
            'quota_points_de_vente'   => ['required', 'integer', 'min:1'],
            'plan_abonnement'         => ['required', 'string'],
            'secteur_activite'        => ['required', 'array'],
            'secteur_activite.*'      => ['required', 'string', 'in:Commercial,Industriel,Services'],
            'modules_actifs'          => ['required', 'array'],
            // Champs COMPTAFLOW conditionnels
            'comptaflow_password'     => [$request->boolean('creer_compte_comptaflow') ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'],
        ]);

        // Assurer l'activation automatique de b2b et fne
        $modules = array_unique(array_merge($request->modules_actifs, ['b2b', 'fne']));

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
                    'secret'         => config('selflow.comptaflow_api_secret', 'selflow-local-secret'),
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
            'secteur_activite.*'      => ['required', 'string', 'in:Commercial,Industriel,Services'],
            'modules_actifs'          => ['required', 'array'],
        ]);

        $modules = array_unique(array_merge($request->modules_actifs, ['b2b', 'fne']));

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

    /**
     * Modifier directement le rôle, le statut et les habilitations d'un utilisateur.
     */
    public function modifierUtilisateur(Request $request, Utilisateur $utilisateur): RedirectResponse
    {
        $request->validate([
            'role'          => ['required', 'string', 'in:superadmin,admin,admin_secondaire,responsable_pdv,caissier'],
            'statut'        => ['required', 'string', 'in:actif,suspendu,inactif'],
            'habilitations' => ['nullable', 'array'],
        ]);

        $utilisateur->update([
            'role'          => $request->role,
            'statut'        => $request->statut,
            'habilitations' => $request->habilitations ?? [],
        ]);

        return redirect()->back()->with('succes', "L'utilisateur « {$utilisateur->nom} {$utilisateur->prenom} » a été mis à jour avec succès.");
    }
}
