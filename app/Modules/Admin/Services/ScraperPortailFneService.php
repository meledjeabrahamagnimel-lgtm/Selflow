<?php

namespace App\Modules\Admin\Services;

use Illuminate\Support\Facades\Log;

/**
 * Lancer le scraper du portail FNE sans attendre le passage horaire.
 *
 * Le fonctionnement ordinaire reste celui du planificateur : une demande de
 * relevé est mise en file, et le scraper la sert à la minute 40. Mais quand un
 * utilisateur vient de cliquer *Normaliser* et que la DGI refuse le point de
 * vente, le faire patienter jusqu'à l'heure suivante n'aurait aucun sens : on
 * lance la relève tout de suite, pour ce login, en arrière-plan.
 *
 * « En arrière-plan » veut dire **détaché** : la requête web se termine sans
 * l'attendre. Un relevé prend des dizaines de secondes — le scraper ouvre un
 * vrai navigateur sur le portail de la DGI — et bloquer la réponse HTTP là-
 * dessus figerait l'écran de l'utilisateur. Le processus vit donc sa vie ;
 * ce qu'il dépose sera rangé par `portail-fne:importer` puis rapproché par
 * `fne:diagnostiquer-rejets` au passage suivant, comme d'habitude.
 *
 * Selflow ne dépend pas de ce lancement : s'il échoue — Node absent, scraper
 * pas armé — la demande reste en file et le planificateur prendra le relais.
 * Aucune erreur ne doit donc remonter jusqu'à la normalisation qui l'a
 * déclenché.
 */
class ScraperPortailFneService
{
    /**
     * Lancer une relève de ce login, détachée, sans jamais lever d'exception.
     *
     * @return bool  vrai si le lancement a été tenté, faux s'il a été écarté
     *               (login vide, scraper éteint) ou qu'il a échoué.
     */
    public static function lancerPourLogin(?string $login): bool
    {
        $login = trim((string) $login);

        if ($login === '') {
            return false;
        }

        // Le même interrupteur que le planificateur : tant que le scraper n'est
        // pas armé (identifiants.json vide en production, dev éteint), on ne le
        // lance pas — la demande en file suffit, et un lancement voué à échouer
        // ne remplit que le journal.
        if (! config('selflow.portail_fne.scraper.actif')) {
            return false;
        }

        $node   = (string) config('selflow.portail_fne.scraper.node');
        $script = (string) config('selflow.portail_fne.scraper.script');
        $journal = storage_path('logs/portail-fne.log');

        try {
            self::detacher([$node, $script, $login], $journal);

            Log::info("ScraperPortailFne : relève lancée en arrière-plan pour le login {$login}.");

            return true;
        } catch (\Throwable $e) {
            Log::error("ScraperPortailFne : lancement impossible pour le login {$login} — " . $e->getMessage());

            return false;
        }
    }

    /**
     * Lancer une commande en processus détaché, sa sortie versée au journal.
     *
     * @param  array<int, string>  $arguments  programme puis arguments
     */
    private static function detacher(array $arguments, string $journal): void
    {
        if (\PHP_OS_FAMILY === 'Windows') {
            // `start /B ""` détache : le `""` est le titre de fenêtre exigé par
            // `start` dès qu'un argument est entre guillemets, sans quoi il
            // prendrait le chemin du programme pour un titre.
            $commande = 'start /B "" ' . self::composer($arguments)
                . ' >> ' . self::citer($journal) . ' 2>&1';

            $tube = popen($commande, 'r');
        } else {
            // `nohup … &` détache sous Unix : le processus survit à la fin de
            // la requête, sa sortie va au journal.
            $commande = 'nohup ' . self::composer($arguments)
                . ' >> ' . self::citer($journal) . ' 2>&1 &';

            $tube = popen($commande, 'r');
        }

        if ($tube === false) {
            throw new \RuntimeException('popen a échoué.');
        }

        pclose($tube);
    }

    /**
     * Assembler une ligne de commande, chaque partie citée.
     *
     * @param  array<int, string>  $arguments
     */
    private static function composer(array $arguments): string
    {
        return implode(' ', array_map([self::class, 'citer'], $arguments));
    }

    /**
     * Citer un argument, guillemets internes doublés.
     *
     * Le PATH du poste — « C:/Program Files/nodejs/node.exe », « …/DCK OFFICE
     * MANAGER/… » — est plein d'espaces : sans guillemets, la commande se
     * couperait au premier.
     */
    private static function citer(string $valeur): string
    {
        return '"' . str_replace('"', '""', $valeur) . '"';
    }
}
