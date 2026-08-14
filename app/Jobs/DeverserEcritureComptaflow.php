<?php

namespace App\Jobs;

use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\Periode;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Déversement d'une écriture comptable vers Comptaflow, en arrière-plan.
 *
 * L'appel partait en synchrone, dans le hook `created()` du modèle : chaque
 * vente, achat ou mouvement de stock qui produit une écriture attendait la
 * réponse de Comptaflow (jusqu'à 3 secondes de délai réseau) avant de rendre
 * la main à l'utilisateur. Un Comptaflow lent ou indisponible ralentissait
 * donc la caisse elle-même. La clé d'idempotence (`cle_selflow`) rend le
 * renvoi sans risque : les tentatives automatiques peuvent réessayer sans
 * dupliquer l'écriture côté Comptaflow.
 *
 * Le rattrapage par lot (`php artisan selflow:sync-ecritures`) reste la
 * filet de sécurité pour tout ce qui aurait échoué même après ces tentatives.
 */
class DeverserEcritureComptaflow implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public int $tries = 3;
    public int $backoff = 10;
    public int $timeout = 10;

    public function __construct(
        public readonly EcritureComptable $ecriture,
    ) {}

    public function handle(): void
    {
        $ecriture  = $this->ecriture;
        $entreprise = $ecriture->entreprise;

        if (!$entreprise || $entreprise->comptaflow_sync_status !== 'active' || !$entreprise->comptaflow_sync_key) {
            return;
        }

        $comptaflowUrl = config('selflow.comptaflow_api_url', 'http://127.0.0.1:8000');
        $secret = config('selflow.comptaflow_api_secret');

        // L'exercice de l'entreprise, tel qu'il est ouvert chez nous.
        // Comptaflow prend le sien sans jamais comparer.
        $exercice = Periode::where('entreprise_id', $entreprise->id)
            ->where('est_active', true)
            ->first();

        $dateStr = $ecriture->date_ecriture instanceof \Carbon\Carbon
            ? $ecriture->date_ecriture->toDateString()
            : (is_string($ecriture->date_ecriture) ? $ecriture->date_ecriture : \Carbon\Carbon::parse($ecriture->date_ecriture)->toDateString());

        $ecritureData = [
            'date_ecriture'      => $dateStr,
            'libelle'            => $ecriture->libelle,
            'reference_document' => $ecriture->reference_document,
            'code_journal'       => $ecriture->code_journal,
            'compte_debit'       => $ecriture->compte_debit,
            'compte_credit'      => $ecriture->compte_credit,
            'debit'              => (float) $ecriture->debit,
            'credit'             => (float) $ecriture->credit,

            // Le compte de tiers — le client ou le fournisseur. Sans lui,
            // Comptaflow rattache l'écriture au seul compte collectif 411000
            // et le relevé d'un client particulier devient impossible.
            'compte_tiers'       => $ecriture->compte_tiers,

            // Clé d'idempotence : rejouer une synchronisation ne doit pas
            // dupliquer l'écriture côté Comptaflow.
            'cle_selflow'        => 'SELFLOW-' . $entreprise->id . '-' . $ecriture->id,

            // Le point de vente : chez Comptaflow, un axe analytique et sa
            // section. Sans lui, aucune ventilation par magasin en aval.
            'point_de_vente'     => $ecriture->pointDeVente?->nom,

            // L'exercice auquel la pièce appartient chez nous.
            'exercice_debut'     => $exercice?->date_debut?->toDateString(),
            'exercice_fin'       => $exercice?->date_fin?->toDateString(),
        ];

        try {
            $response = Http::timeout(8)->post($comptaflowUrl . '/api/external/ecritures/deverser', [
                'secret'             => $secret,
                'selflow_company_id' => $entreprise->id,
                'ecritures'          => [$ecritureData],
            ]);

            if ($response->successful() && $response->json('success')) {
                DB::table('ecritures_comptables')
                    ->where('id', $ecriture->id)
                    ->update(['comptaflow_sync_status' => 'synced']);

                return;
            }

            DB::table('ecritures_comptables')
                ->where('id', $ecriture->id)
                ->update(['comptaflow_sync_status' => 'failed']);
        } catch (\Exception $e) {
            DB::table('ecritures_comptables')
                ->where('id', $ecriture->id)
                ->update(['comptaflow_sync_status' => 'failed']);

            Log::warning('Sync to COMPTAFLOW failed, will retry: ' . $e->getMessage());

            throw $e;
        }
    }

    /**
     * Après épuisement des tentatives : l'écriture reste marquée `failed`,
     * et le rattrapage par lot la reprendra à son prochain passage.
     */
    public function failed(\Throwable $exception): void
    {
        DB::table('ecritures_comptables')
            ->where('id', $this->ecriture->id)
            ->update(['comptaflow_sync_status' => 'failed']);

        Log::error("DeverserEcritureComptaflow: échec définitif pour l'écriture #{$this->ecriture->id} - " . $exception->getMessage());
    }
}
