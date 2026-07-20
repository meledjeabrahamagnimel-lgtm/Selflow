<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Exécuter la migration.
     */
    public function up(): void
    {
        // 1. Modifier la colonne type pour accepter les 6 nouveaux types via VARCHAR
        Schema::table('produits', function (Blueprint $table) {
            $table->string('type', 50)->default('marchandise')->change();
        });

        // 2. Requalifier les anciennes valeurs vers les nouvelles
        DB::statement("UPDATE produits SET type = 'marchandise' WHERE type = 'stockable'");
        DB::statement("UPDATE produits SET type = 'consommable_non_stockable' WHERE type = 'consommable'");
        // 'service' reste inchangé
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        // Rétablir les anciennes valeurs
        DB::statement("UPDATE produits SET type = 'stockable' WHERE type IN ('marchandise','matiere_premiere','produit_fini','consommable_stockable')");
        DB::statement("UPDATE produits SET type = 'consommable' WHERE type = 'consommable_non_stockable'");

        Schema::table('produits', function (Blueprint $table) {
            $table->string('type', 30)->default('stockable')->change();
        });
    }
};
