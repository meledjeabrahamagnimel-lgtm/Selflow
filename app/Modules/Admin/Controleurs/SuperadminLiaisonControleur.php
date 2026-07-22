<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SuperadminLiaisonControleur
{
    /**
     * Page principale : tableau des liaisons Selflow ↔ COMPTAFLOW.
     */
    public function index(): View
    {
        $entreprises = Entreprise::with(['utilisateurs' => function ($q) {
                $q->where('role', 'admin')->orderBy('created_at');
            }])
            ->orderBy('nom')
            ->get();

        // Récupérer la liste des entreprises COMPTAFLOW (via API) pour le formulaire de liaison
        $comptaflowEntreprises = $this->fetchComptaflowEntreprises();

        return view('admin::superadmin.liaisons.index', compact('entreprises', 'comptaflowEntreprises'));
    }

    /**
     * Lier manuellement une entreprise Selflow à une entreprise COMPTAFLOW.
     */
    public function lier(Request $request): RedirectResponse
    {
        $request->validate([
            'entreprise_id'        => 'required|exists:entreprises,id',
            'comptaflow_company_id'=> 'required|integer|min:1',
            'comptaflow_sync_key'  => 'required|string|min:8',
        ]);

        $entreprise = Entreprise::findOrFail($request->entreprise_id);

        $entreprise->update([
            'comptaflow_company_id' => $request->comptaflow_company_id,
            'comptaflow_sync_key'   => $request->comptaflow_sync_key,
            'comptaflow_sync_status'=> 'active',
        ]);

        Log::info("[LIAISON] Entreprise #{$entreprise->id} «{$entreprise->nom}» liée à COMPTAFLOW ID #{$request->comptaflow_company_id}");

        return redirect()->route('superadmin.liaisons.index')
            ->with('success', "✅ Liaison activée : «{$entreprise->nom}» est maintenant liée à COMPTAFLOW (ID #{$request->comptaflow_company_id}).");
    }

    /**
     * Délier une entreprise de COMPTAFLOW.
     */
    public function delierEntreprise(Entreprise $entreprise): RedirectResponse
    {
        $nom = $entreprise->nom;
        $entreprise->update([
            'comptaflow_company_id' => null,
            'comptaflow_sync_key'   => null,
            'comptaflow_sync_status'=> null,
            'comptaflow_last_sync_at'=> null,
        ]);

        return redirect()->route('superadmin.liaisons.index')
            ->with('success', "🔌 Liaison supprimée pour «{$nom}».");
    }

    /**
     * Créer un compte COMPTAFLOW depuis les infos d'une entreprise Selflow.
     * (C2 : liaison inverse — création du compte COMPTAFLOW + liaison auto)
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
            return back()->with('error', "❌ Aucun admin trouvé pour «{$entreprise->nom}».");
        }

        $comptaflowUrl = config('selflow.comptaflow_api_url');
        $superSecret   = config('selflow.comptaflow_superadmin_secret');

        try {
            $response = Http::timeout(15)->post("{$comptaflowUrl}/api/superadmin/creer-depuis-selflow", [
                'superadmin_secret'  => $superSecret,
                'selflow_company_id' => $entreprise->id,
                'nom_entreprise'     => $entreprise->nom,
                'forme_juridique'    => $entreprise->forme_juridique,
                'rccm'               => $entreprise->rccm,
                'ncc'                => $entreprise->ncc,
                'adresse'            => $entreprise->adresse,
                'telephone'          => $entreprise->telephone,
                'email_admin'        => $adminUser->email,
                'nom_admin'          => $adminUser->prenom . ' ' . $adminUser->nom,
                'mot_de_passe'       => $request->mot_de_passe,
                'secteur_activite'   => $entreprise->secteur_activite,
                'regime_imposition'  => $entreprise->regime_imposition,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $comptaflowId  = $data['company_id'] ?? null;
                $syncKey       = $data['sync_key'] ?? null;

                if ($comptaflowId && $syncKey) {
                    $entreprise->update([
                        'comptaflow_company_id' => $comptaflowId,
                        'comptaflow_sync_key'   => $syncKey,
                        'comptaflow_sync_status'=> 'active',
                    ]);

                    return redirect()->route('superadmin.liaisons.index')
                        ->with('success', "✅ Compte COMPTAFLOW créé et liaison activée pour «{$entreprise->nom}» (ID COMPTAFLOW: #{$comptaflowId}).");
                }

                return back()->with('error', '❌ Réponse COMPTAFLOW incomplète : ' . $response->body());
            }

            return back()->with('error', "❌ Erreur COMPTAFLOW ({$response->status()}) : " . $response->body());

        } catch (\Throwable $e) {
            Log::error('[LIAISON] Erreur création compte COMPTAFLOW : ' . $e->getMessage());
            return back()->with('error', '❌ Impossible de joindre COMPTAFLOW : ' . $e->getMessage());
        }
    }

    /**
     * Vérifier le statut de liaison d'une entreprise (ping COMPTAFLOW).
     */
    public function verifierLiaison(Entreprise $entreprise): RedirectResponse
    {
        if (!$entreprise->comptaflow_company_id || !$entreprise->comptaflow_sync_key) {
            return back()->with('error', "❌ «{$entreprise->nom}» n'est pas liée à COMPTAFLOW.");
        }

        $comptaflowUrl = config('selflow.comptaflow_api_url');

        try {
            $response = Http::timeout(8)
                ->withHeaders(['X-Selflow-Key' => $entreprise->comptaflow_sync_key])
                ->get("{$comptaflowUrl}/api/selflow/ping/{$entreprise->comptaflow_company_id}");

            if ($response->successful()) {
                $entreprise->update(['comptaflow_last_sync_at' => now()]);
                return back()->with('success', "✅ Liaison active — COMPTAFLOW répond correctement pour «{$entreprise->nom}».");
            }

            return back()->with('error', "⚠️ COMPTAFLOW répond avec le statut {$response->status()} pour «{$entreprise->nom}».");

        } catch (\Throwable $e) {
            return back()->with('error', "❌ Impossible de joindre COMPTAFLOW : " . $e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private function fetchComptaflowEntreprises(): array
    {
        $comptaflowUrl = config('selflow.comptaflow_api_url');
        $superSecret   = config('selflow.comptaflow_superadmin_secret');

        try {
            $response = Http::timeout(6)->get("{$comptaflowUrl}/api/superadmin/entreprises", [
                'superadmin_secret' => $superSecret,
            ]);

            if ($response->successful()) {
                return $response->json('data', []);
            }
        } catch (\Throwable $e) {
            Log::warning('[LIAISON] Impossible de récupérer les entreprises COMPTAFLOW : ' . $e->getMessage());
        }

        return [];
    }
}
