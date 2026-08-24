<?php

namespace App\Console\Commands;

use App\Modules\Admin\Modeles\FneRejet;
use App\Modules\Admin\Services\DiagnosticFneService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Rapproche les pièces refusées par la DGI du dernier relevé du portail.
 *
 * Passe sur les rejets encore ouverts, va chercher le relevé disponible pour
 * l'entreprise, et consigne la comparaison sur le rejet. C'est le moment où
 * « Le nom du point de vente doit être déclaré à l'identique » devient
 * « vous avez envoyé X, le portail déclare Y ».
 *
 * **Elle ne corrige rien.** Ni la pièce, ni l'entreprise, ni le paramétrage :
 * elle écrit un diagnostic à côté du rejet, et le fait passer de `ouvert` à
 * `diagnostique`. Appliquer une correction reste une décision, prise par
 * quelqu'un, devant l'écran — trois des champs relevés commandent le
 * comportement fiscal, et une facture ne change pas parce qu'un fichier est
 * arrivé dans un dossier.
 *
 * Usage :
 *   php artisan fne:diagnostiquer-rejets
 *   php artisan fne:diagnostiquer-rejets --rejet=12
 *   php artisan fne:diagnostiquer-rejets --tous   (rejouer les déjà diagnostiqués)
 */
class DiagnostiquerRejetsFne extends Command
{
    protected $signature = 'fne:diagnostiquer-rejets
                            {--rejet= : Un seul rejet, par son identifiant}
                            {--tous : Reprendre aussi les rejets déjà diagnostiqués}';

    protected $description = 'Rapproche les rejets FNE du dernier relevé du portail.';

    public function handle(DiagnosticFneService $service): int
    {
        $requete = FneRejet::query()->orderBy('id');

        if ($id = $this->option('rejet')) {
            $requete->whereKey($id);
        } elseif (!$this->option('tous')) {
            $requete->where('statut', FneRejet::STATUT_OUVERT);
        } else {
            $requete->whereIn('statut', [FneRejet::STATUT_OUVERT, FneRejet::STATUT_DIAGNOSTIQUE]);
        }

        $rejets = $requete->get();

        if ($rejets->isEmpty()) {
            $this->info('Aucun rejet à rapprocher.');

            return self::SUCCESS;
        }

        $rapproches = 0;

        foreach ($rejets as $rejet) {
            $diagnostic = $service->diagnostiquer($rejet);

            // Sans relevé, le rejet reste ouvert : le marquer « diagnostiqué »
            // sur la foi d'une comparaison qui n'a pas eu lieu le ferait sortir
            // de la file sans que rien n'ait été vérifié.
            if ($diagnostic['releve'] === null) {
                $this->line("Rejet #{$rejet->id} ({$rejet->numero_piece}) : aucun relevé disponible.");
                continue;
            }

            $rejet->update([
                'diagnostic' => $diagnostic,
                'statut'     => FneRejet::STATUT_DIAGNOSTIQUE,
            ]);

            $rapproches++;

            $this->line("Rejet #{$rejet->id} ({$rejet->numero_piece}) — {$diagnostic['conclusion']}");

            foreach ($diagnostic['champs'] as $champ) {
                $this->line("    {$champ['champ']} : {$champ['explication']}");
            }

            // Les écarts de fiche sortent aussi au journal : le timbre de
            // quittance et le bordereau d'achat y figurent, et ils changent ce
            // qui part à la DGI. Personne n'est devant l'écran quand la tâche
            // planifiée passe.
            if ($diagnostic['ecarts_fiche'] !== []) {
                Log::warning('Rejet FNE : écarts entre le portail et le paramétrage', [
                    'rejet'  => $rejet->id,
                    'ecarts' => array_keys($diagnostic['ecarts_fiche']),
                ]);
            }
        }

        $this->info("{$rapproches} rejet(s) rapproché(s) sur {$rejets->count()}.");

        return self::SUCCESS;
    }
}
