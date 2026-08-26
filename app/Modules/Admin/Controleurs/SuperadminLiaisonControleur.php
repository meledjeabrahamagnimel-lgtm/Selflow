<?php

namespace App\Modules\Admin\Controleurs;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Services\LiaisonComptaflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Les liaisons Selflow ↔ Comptaflow, vues du superadministrateur.
 *
 * Trois chemins ont disparu d'ici, et c'est le fond du lot :
 *
 *  - **`lierEntreprise()`** liait une entreprise à un dossier Comptaflow en
 *    collant une clé et un identifiant. Rien ne vérifiait que le dossier
 *    appartenait à cette entreprise. La route qui y menait référençait par
 *    ailleurs une méthode `lier` qui n'existait pas : le formulaire tombait
 *    en erreur 500 (Internal Server Error — erreur du serveur) depuis qu'il
 *    avait été renommé, sans que personne s'en aperçoive ;
 *  - **`creerComptaflow()`** demandait au superadministrateur de choisir le
 *    **mot de passe** du compte d'un client, et le transmettait en clair dans
 *    le corps de la requête ;
 *  - **`delierEntreprise()`** effaçait la clé chez Selflow sans rien dire à
 *    Comptaflow, où elle continuait d'ouvrir le dossier. Une clé oubliée chez
 *    l'autre est une clé qui marche.
 *
 * À la place : une file de demandes, que le superadministrateur valide ou
 * refuse. La clé est délivrée par Comptaflow, jamais saisie, jamais affichée.
 */
class SuperadminLiaisonControleur extends Controller
{
    /**
     * Le tableau des liaisons, demandes en attente en tête.
     */
    public function index()
    {
        $entreprises = Entreprise::with(['utilisateurs' => fn ($q) => $q->where('role', 'admin')])
            ->orderBy('nom')
            ->get();

        // Ce qui attend une décision passe devant : c'est la seule chose sur
        // cet écran qui demande une action, et elle se perdait au milieu de
        // toutes les entreprises.
        $demandes = $entreprises->filter(fn (Entreprise $e) => $e->demandeComptaflowEnAttente())->values();

        $comptaflowCompanies = $this->fetchComptaflowEntreprises();

        return view('admin::superadmin.liaisons.index', compact('entreprises', 'demandes', 'comptaflowCompanies'));
    }

    /**
     * Valider une demande : Comptaflow ouvre le dossier et délivre la clé.
     */
    public function validerDemande(Entreprise $entreprise): RedirectResponse
    {
        $resultat = LiaisonComptaflowService::valider($entreprise);

        return $resultat['success']
            ? back()->with('success', "✅ « {$entreprise->nom} » : " . $resultat['message'])
            : back()->with('error', "❌ « {$entreprise->nom} » : " . $resultat['message']);
    }

    /**
     * Refuser une demande, avec un motif que l'entreprise lit dans ses
     * paramètres. Un refus muet la laisserait redemander sans fin.
     */
    public function refuserDemande(Request $request, Entreprise $entreprise): RedirectResponse
    {
        $request->validate([
            'motif' => ['required', 'string', 'max:255'],
        ], [
            'motif.required' => 'Dites pourquoi : l\'entreprise ne verra que ce motif.',
        ]);

        LiaisonComptaflowService::refuser($entreprise, $request->input('motif'));

        return back()->with('success', "Demande de « {$entreprise->nom} » refusée.");
    }

    /**
     * Délier : la clé est révoquée des deux côtés.
     */
    public function delierEntreprise(Entreprise $entreprise): RedirectResponse
    {
        $resultat = LiaisonComptaflowService::revoquer($entreprise);

        return redirect()->route('superadmin.liaisons.index')
            ->with('success', "🔌 « {$entreprise->nom} » — " . $resultat['message']);
    }

    /**
     * Vérifier qu'une liaison répond encore.
     *
     * L'ancienne version se contentait d'écrire `comptaflow_last_sync_at = now()`
     * et d'annoncer « Liaison active » : elle datait une vérification qui
     * n'avait pas eu lieu. Elle interroge maintenant Comptaflow.
     */
    public function verifierLiaison(Entreprise $entreprise): RedirectResponse
    {
        if (!$entreprise->liaisonComptaflowActive()) {
            return back()->with('error', "❌ « {$entreprise->nom} » n'est pas liée à Comptaflow.");
        }

        try {
            $reponse = Http::timeout(10)
                ->withHeaders(LiaisonComptaflowService::enTete($entreprise))
                ->post($this->url('/api/external/companies/verify'), [
                    'secret'                => config('selflow.comptaflow_api_secret'),
                    'selflow_company_id'    => $entreprise->id,
                    'comptaflow_company_id' => $entreprise->comptaflow_company_id,
                ]);
        } catch (\Throwable $e) {
            return back()->with('error', "❌ Comptaflow injoignable : " . $e->getMessage());
        }

        if (in_array($reponse->status(), [404, 405], true)) {
            return back()->with('error', "❌ Comptaflow n'expose pas encore /api/external/companies/verify.");
        }

        if (!$reponse->successful() || !$reponse->json('success')) {
            return back()->with('error', "❌ « {$entreprise->nom} » : Comptaflow ne reconnaît pas cette clé.");
        }

        $entreprise->update(['comptaflow_last_sync_at' => now()]);

        return back()->with('success', "✅ Liaison vérifiée pour « {$entreprise->nom} ».");
    }

    /**
     * Les dossiers que Comptaflow connaît, pour rapprochement à l'œil.
     *
     * @return array<int, mixed>
     */
    private function fetchComptaflowEntreprises(): array
    {
        try {
            $response = Http::timeout(5)->post($this->url('/api/external/list-companies'), [
                'secret' => config('selflow.comptaflow_api_secret'),
            ]);

            if ($response->successful() && $response->json('success')) {
                return $response->json('companies', []);
            }
        } catch (\Throwable $e) {
            Log::warning('[LIAISON] Impossible de contacter Comptaflow list-companies : ' . $e->getMessage());
        }

        return [];
    }

    private function url(string $chemin): string
    {
        return rtrim(config('selflow.comptaflow_api_url', 'http://127.0.0.1:8000'), '/') . $chemin;
    }
}
