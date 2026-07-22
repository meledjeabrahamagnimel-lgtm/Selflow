<?php

namespace App\Modules\Admin\Controleurs;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Modeles\Entreprise;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SuperadminLiaisonControleur extends Controller
{
    /**
     * Afficher le tableau croisé des liaisons Selflow ↔ COMPTAFLOW.
     */
    public function index()
    {
        $entreprises = Entreprise::with(['utilisateurs' => fn($q) => $q->where('role', 'admin')])
            ->orderBy('nom')
            ->get();

        $comptaflowCompanies = $this->fetchComptaflowEntreprises();

        return view('admin::superadmin.liaisons.index', compact('entreprises', 'comptaflowCompanies'));
    }

    /**
     * Lier manuellement une entreprise Selflow existante à un ID COMPTAFLOW.
     */
    public function lierEntreprise(Request $request): RedirectResponse
    {
        $request->validate([
            'entreprise_id'         => 'required|exists:entreprises,id',
            'comptaflow_company_id' => 'required|integer|min:1',
            'comptaflow_sync_key'   => 'required|string',
        ]);

        $entreprise = Entreprise::findOrFail($request->entreprise_id);

        $entreprise->update([
            'comptaflow_company_id' => $request->comptaflow_company_id,
            'comptaflow_sync_key'   => $request->comptaflow_sync_key,
            'comptaflow_sync_status'=> 'active',
            'comptaflow_last_sync_at'=> now(),
        ]);

        return redirect()->route('superadmin.liaisons.index')
            ->with('success', "✅ Entreprise «{$entreprise->nom}» liée avec succès à COMPTAFLOW (#{$request->comptaflow_company_id}).");
    }

    /**
     * Supprimer la liaison d'une entreprise Selflow.
     */
    public function delierEntreprise(Entreprise $entreprise): RedirectResponse
    {
        $nom = $entreprise->nom;

        $entreprise->update([
            'comptaflow_company_id'  => null,
            'comptaflow_sync_key'    => null,
            'comptaflow_sync_status' => null,
            'comptaflow_last_sync_at'=> null,
        ]);

        return redirect()->route('superadmin.liaisons.index')
            ->with('success', "🔌 Liaison supprimée pour «{$nom}».");
    }

    /**
     * Créer un compte COMPTAFLOW depuis une entreprise Selflow
     * (Transfère les données de Selflow vers COMPTAFLOW).
     */
    public function creerComptaflow(Request $request): RedirectResponse
    {
        $request->validate([
            'entreprise_id' => 'required|exists:entreprises,id',
            'mot_de_passe'  => 'required|string|min:8',
        ]);

        $entreprise = Entreprise::with(['utilisateurs' => fn($q) => $q->where('role', 'admin')])->findOrFail($request->entreprise_id);
        $adminUser  = $entreprise->utilisateurs->first();

        if (!$adminUser) {
            return back()->with('error', "❌ Aucun administrateur trouvé pour «{$entreprise->nom}».");
        }

        $comptaflowUrl = config('selflow.comptaflow_api_url', env('COMPTAFLOW_API_URL', 'http://127.0.0.1:8000'));
        $secret        = config('selflow.comptaflow_api_secret', 'selflow-comptaflow-secret-2026');
        $syncKey       = 'sf_' . Str::random(32);

        try {
            $response = Http::timeout(10)->post("{$comptaflowUrl}/api/external/register-enterprise", [
                'secret'              => $secret,
                'company_name'        => $entreprise->nom,
                'activity'            => is_array($entreprise->secteur_activite) ? implode(', ', $entreprise->secteur_activite) : ($entreprise->secteur_activite ?? 'Commercial'),
                'juridique_form'      => $entreprise->forme_juridique ?? 'SARL',
                'adresse'             => $entreprise->adresse,
                'phone_number'        => $entreprise->telephone,
                'email_adresse'       => $adminUser->email,
                'ncc'                 => $entreprise->ncc,
                'rccm'                => $entreprise->rccm,
                'compte_contribuable' => $entreprise->compte_contribuable,
                'regime'              => $entreprise->regime_imposition,
                'admin_nom'           => $adminUser->nom,
                'admin_prenom'        => $adminUser->prenom,
                'admin_password'      => $request->mot_de_passe,
                'selflow_company_id'  => $entreprise->id,
                'selflow_sync_key'    => $syncKey,
            ]);

            if ($response->successful() && $response->json('success')) {
                $cptfCompanyId = $response->json('company_id');

                $entreprise->update([
                    'comptaflow_company_id'  => $cptfCompanyId,
                    'comptaflow_sync_key'    => $syncKey,
                    'comptaflow_sync_status' => 'active',
                    'comptaflow_last_sync_at'=> now(),
                ]);

                return redirect()->route('superadmin.liaisons.index')
                    ->with('success', "✅ Compte COMPTAFLOW créé et liaison activée pour «{$entreprise->nom}» (ID COMPTAFLOW: #{$cptfCompanyId}).");
            }

            $msg = $response->json('message') ?? $response->body();
            return back()->with('error', "❌ Erreur COMPTAFLOW : " . $msg);

        } catch (\Throwable $e) {
            Log::error('[LIAISON] Erreur création compte COMPTAFLOW : ' . $e->getMessage());
            return back()->with('error', '❌ Impossible de joindre COMPTAFLOW : ' . $e->getMessage());
        }
    }

    /**
     * Vérifier le statut de liaison d'une entreprise.
     */
    public function verifierLiaison(Entreprise $entreprise): RedirectResponse
    {
        if (!$entreprise->comptaflow_company_id) {
            return back()->with('error', "❌ «{$entreprise->nom}» n'est pas liée à COMPTAFLOW.");
        }

        $entreprise->update(['comptaflow_last_sync_at' => now()]);
        return back()->with('success', "✅ Liaison active pour «{$entreprise->nom}».");
    }

    /**
     * Récupérer les entreprises COMPTAFLOW via l'API externe.
     */
    private function fetchComptaflowEntreprises(): array
    {
        $comptaflowUrl = config('selflow.comptaflow_api_url', env('COMPTAFLOW_API_URL', 'http://127.0.0.1:8000'));
        $secret        = config('selflow.comptaflow_api_secret', 'selflow-comptaflow-secret-2026');

        try {
            $response = Http::timeout(5)->post("{$comptaflowUrl}/api/external/list-companies", [
                'secret' => $secret,
            ]);

            if ($response->successful() && $response->json('success')) {
                return $response->json('companies', []);
            }
        } catch (\Throwable $e) {
            Log::warning('[LIAISON] Impossible de contacter COMPTAFLOW list-companies: ' . $e->getMessage());
        }

        return [];
    }
}
