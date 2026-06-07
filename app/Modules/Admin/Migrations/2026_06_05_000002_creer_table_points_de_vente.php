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
        Schema::create('points_de_vente', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id')->index();
            $table->foreign('entreprise_id', 'fk_points_de_vente_entreprises')
                ->references('id')
                ->on('entreprises')
                ->onDelete('cascade');
            $table->string('nom');
            $table->string('ville');
            $table->string('commune');
            $table->string('responsable')->nullable();
            $table->string('telephone')->nullable();
            $table->string('statut')->default('Ouvert')->index();
            $table->timestamps();
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('points_de_vente');
    }
};
