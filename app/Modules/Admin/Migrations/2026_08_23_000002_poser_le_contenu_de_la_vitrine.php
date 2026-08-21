<?php

use Database\Seeders\VitrineSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La page d'accueil arrive par la migration, non plus par le seul semeur.
 *
 * Le contenu de la vitrine n'était posé que par `VitrineSeeder`, appelé depuis
 * `DatabaseSeeder`. Or **on ne relance pas `db:seed` sur une base en service** :
 * il repose des données de démonstration par-dessus les vraies. Résultat, les
 * environnements installés avant l'écriture de la vitrine — celui du
 * propriétaire du projet, notamment — affichaient « Cette page est en
 * préparation » là où un poste fraîchement installé montrait la page complète.
 * Deux personnes ouvraient la même adresse et ne voyaient pas la même chose,
 * sans qu'aucune erreur ne soit levée.
 *
 * `migrate`, lui, se lance sur une base en service : c'est fait pour ça. Le
 * contenu suit désormais le même chemin que le schéma.
 *
 * **Rien n'est écrasé.** Le semeur pose chaque bloc par `firstOrCreate` sur sa
 * clé : une section que le superadministrateur a réécrite, renommée ou
 * dépubliée reste telle qu'il l'a laissée. Une base qui a déjà tourné le
 * semeur ne bouge pas non plus.
 */
return new class extends Migration
{
    public function up(): void
    {
        // La table peut ne pas exister sur une base très ancienne dont les
        // migrations de la vitrine n'ont pas encore tourné : dans ce cas
        // celles-ci passeront avant, et il n'y a rien à faire ici.
        if (!\Illuminate\Support\Facades\Schema::hasTable('vitrine_sections')) {
            return;
        }

        // Une vitrine déjà remplie n'est pas retouchée — pas même par un
        // `firstOrCreate` qui ne créerait rien : inutile de faire tourner le
        // semeur pour rien à chaque déploiement.
        if (DB::table('vitrine_sections')->exists()) {
            return;
        }

        (new VitrineSeeder())->run();
    }

    /**
     * Le retour ne supprime rien.
     *
     * Effacer les sections détruirait le travail que le superadministrateur a
     * pu faire par-dessus — textes réécrits, images déposées, ordre revu — et
     * rien ne distinguerait ce qui vient du semeur de ce qui vient de lui.
     */
    public function down(): void
    {
    }
};
