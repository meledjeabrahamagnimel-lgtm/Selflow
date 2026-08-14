<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Peupler la base de données de l'application.
     */
    public function run(): void
    {
        // **Le référentiel d'abord.** Il porte les domaines d'activité que la
        // première étape de la souscription propose. Il n'était appelé nulle
        // part — seulement à la main, par `--class=ReferentielSeeder` — si bien
        // qu'une installation neuve arrivait sur « Dans quel domaine
        // travaillez-vous ? » avec une liste vide, et **aucun moyen d'aller
        // plus loin** : le formulaire exige un domaine que rien ne propose.
        //
        // Le chargement est idempotent : le relancer met à jour ce qui a
        // changé sans dupliquer ce qui existe.
        $this->call(ReferentielSeeder::class);

        $this->call(DonneesInitialesSeeder::class);
    }
}
