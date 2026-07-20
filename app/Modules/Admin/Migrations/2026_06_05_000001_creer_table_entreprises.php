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
        Schema::create('entreprises', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('adresse')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('rccm')->nullable();
            $table->string('compte_contribuable')->nullable();
            $table->integer('quota_points_de_vente')->default(5)->index();
            $table->string('plan_abonnement')->default('Starter')->index();
            $table->json('secteur_activite')->nullable();
            $table->json('modules_actifs')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('entreprises');
    }
};
