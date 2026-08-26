<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Re-synchronisation des écritures COMPTAFLOW échouées (toutes les 5 minutes)
Schedule::command('selflow:sync-ecritures')->everyFiveMinutes()->withoutOverlapping();

// Rotation mensuelle des clés de liaison Comptaflow.
//
// Une clé posée une fois et jamais changée ouvre le dossier comptable d'une
// entreprise aussi longtemps qu'elle existe. La rotation ne rend pas une fuite
// impossible : elle borne sa durée de vie à un mois.
//
// Le premier du mois, à 3 h — heure où aucune caisse n'encaisse, donc où une
// clé qui change ne croise aucun déversement. `withoutOverlapping()` par
// prudence : la commande est déjà bornée à cinquante dossiers, mais un
// Comptaflow lent pourrait la faire durer.
Schedule::command('selflow:renouveler-cles-comptaflow')
    ->monthlyOn(1, '03:00')
    ->withoutOverlapping();
