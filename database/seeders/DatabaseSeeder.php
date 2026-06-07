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
        $this->call(DonneesInitialesSeeder::class);
    }
}
