<?php

namespace App\Console\Commands;

use App\Modules\Admin\Services\LiaisonComptaflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * La rotation mensuelle des clés de liaison Comptaflow.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Pourquoi elle existe
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Une clé posée une fois et jamais changée ouvre le dossier comptable d'une
 * entreprise aussi longtemps qu'elle existe. Un prestataire qui a vu passer
 * une requête, une sauvegarde égarée, un journal de serveur mal purgé : rien
 * ne referme derrière eux. La rotation ne rend pas la fuite impossible, elle
 * borne sa durée de vie à un mois.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Ce qui fait qu'elle ne casse rien
 * ─────────────────────────────────────────────────────────────────────────
 *
 * - **Un dossier à la fois, et l'échec de l'un n'arrête pas les autres.** Une
 *   entreprise dont Comptaflow refuse le renouvellement ne doit pas empêcher
 *   les vingt-neuf suivantes de tourner ;
 * - **rien n'est effacé avant que la nouvelle clé soit en main.** Un appel qui
 *   échoue laisse l'ancienne active : le déversement continue comme avant ;
 * - **un échec est daté et met le dossier au repos** douze heures. Comptaflow
 *   peut être en panne pour la journée ; marteler son API ne la réveillera
 *   pas, et une tâche qui repart en boucle sur trente dossiers en échec ferait
 *   plus de mal que la clé qu'elle renouvelle ;
 * - **la période de grâce est tenue par Comptaflow** : l'ancienne clé reste
 *   valable quelques minutes après la rotation, le temps qu'une requête déjà
 *   partie arrive. Sans elle, un déversement en vol au moment précis du
 *   renouvellement échouerait — rarement, et sans qu'on comprenne pourquoi.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Comment la lancer
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Le premier de chaque mois, par le planificateur. À la main :
 *
 *     php artisan selflow:renouveler-cles-comptaflow
 *     php artisan selflow:renouveler-cles-comptaflow --a-blanc
 */
class RenouvelerLesClesComptaflow extends Command
{
    protected $signature = 'selflow:renouveler-cles-comptaflow
                            {--a-blanc : Dire ce qui serait renouvelé, sans rien appeler}
                            {--limite=50 : Combien de dossiers au plus, pour une seule exécution}';

    protected $description = 'Renouvelle les clés de liaison Comptaflow qui ont dépassé leur durée de vie';

    public function handle(): int
    {
        $dossiers = LiaisonComptaflowService::dossiersARenouveler()
            ->take((int) $this->option('limite'));

        if ($dossiers->isEmpty()) {
            $this->info('Aucune clé à renouveler : toutes ont moins de '
                . LiaisonComptaflowService::JOURS_AVANT_ROTATION . ' jours.');

            return self::SUCCESS;
        }

        $this->info($dossiers->count() . ' clé(s) à renouveler.');

        if ($this->option('a-blanc')) {
            foreach ($dossiers as $entreprise) {
                $this->line(sprintf(
                    '  %-40s dernière rotation : %s',
                    $entreprise->nom,
                    $entreprise->comptaflow_cle_tournee_le?->format('d/m/Y') ?? 'jamais'
                ));
            }

            return self::SUCCESS;
        }

        $reussies = 0;
        $echouees = 0;

        foreach ($dossiers as $entreprise) {
            // Chaque dossier dans son propre `try` : une exception non prévue
            // sur l'un ne doit pas laisser les autres avec leur clé d'un mois.
            try {
                $resultat = LiaisonComptaflowService::renouvelerLaCle($entreprise);
            } catch (\Throwable $e) {
                $resultat = ['success' => false, 'message' => $e->getMessage()];
                Log::error('Rotation Comptaflow : exception sur ' . $entreprise->nom . ' — ' . $e->getMessage());
            }

            if ($resultat['success']) {
                $reussies++;
                $this->line("  ✔ {$entreprise->nom}");
            } else {
                $echouees++;
                $this->warn("  ✖ {$entreprise->nom} — {$resultat['message']}");
            }
        }

        $this->newLine();
        $this->info("{$reussies} renouvelée(s), {$echouees} en échec.");

        // Les échecs ne font pas échouer la commande : ils sont datés, mis au
        // repos, et repris au prochain passage. Une tâche planifiée qui rend un
        // code d'erreur pour un Comptaflow momentanément absent alerterait
        // pour rien, et l'alerte suivante ne serait plus lue.
        return self::SUCCESS;
    }
}
