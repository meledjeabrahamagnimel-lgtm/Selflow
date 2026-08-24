<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Re-synchronisation des écritures COMPTAFLOW échouées (toutes les 5 minutes)
Schedule::command('selflow:sync-ecritures')->everyFiveMinutes()->withoutOverlapping();

/*
 * Ramassage des relevés du portail FNE déposés dans le dossier d'import.
 *
 * Toutes les heures, et non toutes les minutes : un relevé se produit au mieux
 * une fois par jour, et relire un dossier soixante fois par heure ne le ferait
 * pas arriver plus tôt. `withoutOverlapping` évite qu'un dépôt volumineux soit
 * relu par le passage suivant pendant qu'il est encore en cours de lecture.
 *
 * Le passage est sans effet quand rien n'a changé : un fichier déjà lu est
 * reconnu à son empreinte. Il n'y a donc pas de « premier passage » à préparer
 * ni de fichier à déplacer après coup.
 *
 * La sortie est écrite dans un journal dédié plutôt que perdue : c'est le seul
 * endroit où l'on verra qu'un fichier est refusé pour cause de nom hors
 * nomenclature, personne n'étant devant l'écran quand la tâche tourne.
 */
Schedule::command('portail-fne:importer')
    ->hourly()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/portail-fne.log'));

/*
 * Rapprochement des rejets FNE avec les relevés du portail.
 *
 * Juste après le ramassage, et non avant : un rejet ne se diagnostique qu'avec
 * un relevé sous la main, et c'est le passage précédent qui vient de le ranger
 * en base. Un rejet sans relevé reste ouvert et sera repris au passage suivant.
 *
 * La commande ne corrige rien : elle écrit la comparaison à côté du rejet.
 */
Schedule::command('fne:diagnostiquer-rejets')
    ->hourlyAt(10)
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/portail-fne.log'));
