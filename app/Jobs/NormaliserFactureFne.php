<?php

namespace App\Jobs;

use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Services\FneService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job : Normalisation de facture via l'API FNE/DGI (Section 18.5).
 *
 * Ce Job exécute l'appel HTTP à l'API FNE en arrière-plan, évitant de bloquer
 * la réponse HTTP de la vente. En cas d'échec temporaire (timeout, API down),
 * Laravel réessaiera automatiquement (max 3 tentatives, délai exponentiel).
 *
 * Après normalisation réussie, la vente est mise à jour en base avec :
 *   - numero_fne
 *   - signature_dgi
 *   - qr_code_data
 *   - normalise = true
 *   - type_facture
 */
class NormaliserFactureFne implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * Nombre maximum de tentatives en cas d'échec.
     */
    public int $tries = 3;

    /**
     * Délai entre les tentatives (en secondes).
     */
    public int $backoff = 30;

    /**
     * Timeout maximum pour ce Job (en secondes).
     */
    public int $timeout = 30;

    public function __construct(
        public readonly Vente $vente,
        public readonly bool $estRne = false,
    ) {}

    /**
     * Exécuter le Job.
     */
    public function handle(): void
    {
        try {
            Log::info("NormaliserFactureFne: Début normalisation async - Vente #{$this->vente->id} / Facture: {$this->vente->numero_facture}");

            $fneResult = FneService::normaliserFacture($this->vente, $this->estRne);

            if ($fneResult['success']) {
                // Recharger la vente pour éviter les conflicts de version
                $vente = Vente::find($this->vente->id);
                
                if ($vente) {
                    $vente->update([
                        'normalise'     => true,
                        'numero_fne'    => $fneResult['numero_recu'],
                        'signature_dgi' => $fneResult['signature'] ?? null,
                        'qr_code_data'  => $fneResult['qr_code_data'],
                        'fichier_fne_pdf_url' => $fneResult['pdf_url'] ?? null,
                        'type_facture'  => $this->estRne ? 'RNE' : 'normale',
                    ]);

                    Log::info("NormaliserFactureFne: Normalisation réussie - Vente #{$vente->id} → FNE: {$fneResult['numero_recu']}");
                }
            } else {
                Log::warning("NormaliserFactureFne: Réponse non-success pour Vente #{$this->vente->id}", $fneResult);
            }
        } catch (\Exception $e) {
            Log::error("NormaliserFactureFne: Exception pour Vente #{$this->vente->id} - " . $e->getMessage());
            throw $e; // Relancer pour déclencher la logique de retry de Laravel
        }
    }

    /**
     * Callback en cas d'échec définitif (après épuisement des tentatives).
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("NormaliserFactureFne: ECHEC DEFINITIF pour Vente #{$this->vente->id} après {$this->tries} tentatives - " . $exception->getMessage());

        // Marquer la vente comme non-normalisée pour permettre une retry manuelle
        Vente::where('id', $this->vente->id)->update(['normalise' => false]);
    }
}
