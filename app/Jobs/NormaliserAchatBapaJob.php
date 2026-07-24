<?php

namespace App\Jobs;

use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Services\FneService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job : Normalisation de bordereau d'achat BAPA via l'API FNE/DGI.
 *
 * Ce Job exécute l'appel HTTP à l'API FNE en arrière-plan pour normaliser
 * un achat BAPA de manière asynchrone (évite les blocages).
 */
class NormaliserAchatBapaJob implements ShouldQueue
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
        public readonly Achat $achat
    ) {}

    /**
     * Exécuter le Job.
     */
    public function handle(): void
    {
        try {
            Log::info("NormaliserAchatBapaJob: Début normalisation async - Achat #{$this->achat->id} / Facture: {$this->achat->numero_facture}");

            $fneResult = FneService::normaliserAchatBapa($this->achat);

            if ($fneResult['success']) {
                // Recharger l'achat pour éviter les conflits
                $achat = Achat::find($this->achat->id);

                if ($achat) {
                    $achat->update([
                        'normalise'     => true,
                        'numero_fne'    => $fneResult['numero_recu'],
                        'signature_dgi' => $fneResult['signature'] ?? null,
                        'qr_code_data'  => $fneResult['qr_code_data'],
                        'fichier_fne_pdf_url' => $fneResult['pdf_url'] ?? null,
                    ]);

                    Log::info("NormaliserAchatBapaJob: Normalisation BAPA réussie - Achat #{$achat->id} → FNE: {$fneResult['numero_recu']}");
                }
            } else {
                Log::warning("NormaliserAchatBapaJob: Réponse non-success pour Achat #{$this->achat->id}", $fneResult);
            }
        } catch (\Exception $e) {
            Log::error("NormaliserAchatBapaJob: Exception pour Achat #{$this->achat->id} - " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Callback en cas d'échec définitif.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("NormaliserAchatBapaJob: ECHEC DEFINITIF pour Achat #{$this->achat->id} après {$this->tries} tentatives - " . $exception->getMessage());
        Achat::where('id', $this->achat->id)->update(['normalise' => false]);
    }
}
