<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécuter la migration.
     */
    public function up(): void
    {
        // 1. Table Fiches Techniques
        Schema::create('fiches_techniques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->onDelete('cascade');
            $table->foreignId('produit_fini_id')->constrained('produits')->onDelete('cascade');
            $table->text('description')->nullable();
            $table->timestamps();

            // Un produit fini ne peut avoir qu'une seule fiche technique active
            $table->unique(['entreprise_id', 'produit_fini_id'], 'unique_fiche_produit');
        });

        // 2. Table Détails Fiches Techniques (Ingrédients / Matières Premières)
        Schema::create('fiche_technique_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fiche_technique_id')->constrained('fiches_techniques')->onDelete('cascade');
            $table->foreignId('ingredient_id')->constrained('produits')->onDelete('cascade');
            $table->decimal('quantite', 15, 4);
            $table->string('unite', 50)->default('Unité');
            $table->timestamps();

            $table->unique(['fiche_technique_id', 'ingredient_id'], 'unique_fiche_ingredient');
        });

        // 3. Table Ordres de Production (OP)
        Schema::create('ordres_production', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->onDelete('cascade');
            $table->foreignId('point_de_vente_id')->constrained('points_de_vente')->onDelete('cascade');
            $table->foreignId('produit_fini_id')->constrained('produits')->onDelete('cascade');
            $table->string('code_ordre', 50)->unique();
            $table->decimal('quantite_cible', 15, 4);
            $table->string('statut', 30)->default('Brouillon'); // Brouillon, Planifié, Terminé, Annulé
            $table->date('date_production');
            $table->timestamps();
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('ordres_production');
        Schema::dropIfExists('fiche_technique_details');
        Schema::dropIfExists('fiches_techniques');
    }
};
