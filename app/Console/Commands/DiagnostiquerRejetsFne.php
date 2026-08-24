<?php

namespace App\Console\Commands;

use App\Modules\Admin\Modeles\FneRejet;
use App\Modules\Admin\Modeles\PortailFneDemande;
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
        } else {
            // Les rejets déjà diagnostiqués sont repris eux aussi : c'est ici
            // que le constat se rafraîchit quand un relevé plus récent arrive.
            // `diagnosticEstAJour()` écarte ensuite ceux qui décrivent déjà le
            // dernier état connu, pour ne pas réécrire la même chose chaque heure.
            $requete->whereIn('statut', [FneRejet::STATUT_OUVERT, FneRejet::STATUT_DIAGNOSTIQUE]);
        }

        $rejets = $requete->get();

        if ($rejets->isEmpty()) {
            $this->info('Aucun rejet à rapprocher.');

            // Le signalement passe aussi ici : une demande peut traîner seule,
            // le rejet qui l'avait ouverte ayant été classé entre-temps. C'est
            // même le cas où personne ne regarde.
            $this->signalerLesDemandesQuiTrainent();

            return self::SUCCESS;
        }

        $rapproches = 0;
        $inchanges  = 0;

        foreach ($rejets as $rejet) {
            // `--tous` force la réécriture, y compris d'un constat déjà à jour.
            if (!$this->option('tous')
                && $rejet->statut === FneRejet::STATUT_DIAGNOSTIQUE
                && $service->diagnosticEstAJour($rejet)) {
                $inchanges++;
                continue;
            }

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

        $this->info("{$rapproches} rejet(s) rapproché(s) sur {$rejets->count()}"
            . ($inchanges > 0 ? ", {$inchanges} déjà à jour." : '.'));

        $this->signalerLesDemandesQuiTrainent();

        return self::SUCCESS;
    }

    /**
     * Dit tout haut les demandes de relevé qui n'attendent plus : elles traînent.
     *
     * Une demande ouverte est un signal voulu — c'est ainsi qu'on voit qu'un
     * scraper ne répond plus. Encore faut-il que quelqu'un le voie. Sans ce
     * signalement, la seule façon de s'en apercevoir était d'ouvrir l'écran des
     * rejets et de remarquer un chiffre qui ne bouge pas, ce qui suppose de
     * l'avoir remarqué la veille.
     *
     * Trois causes possibles, et aucune ne se corrige toute seule : le scraper
     * ne tourne pas, il dépose ailleurs, ou le login est faux.
     */
    private function signalerLesDemandesQuiTrainent(): void
    {
        $enRetard = PortailFneDemande::enRetard()->orderBy('created_at')->get();

        if ($enRetard->isEmpty()) {
            return;
        }

        $heures = config('selflow.portail_fne.delai_alerte_heures', 24);

        $this->warn("{$enRetard->count()} demande(s) de relevé sans réponse depuis plus de {$heures} h :");

        foreach ($enRetard as $demande) {
            $this->line("    {$demande->login} — depuis {$demande->attenteLisible()} — {$demande->motif}");
        }

        Log::warning('Portail FNE : des demandes de relevé restent sans réponse', [
            'nombre' => $enRetard->count(),
            'logins' => $enRetard->pluck('login')->unique()->values()->all(),
            'seuil_heures' => $heures,
        ]);
    }
}
