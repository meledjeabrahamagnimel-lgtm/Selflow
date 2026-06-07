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
        Schema::create('vente_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vente_id')->index();
            $table->unsignedBigInteger('produit_id')->index();

            $table->foreign('vente_id', 'fk_vente_details_ventes')
                ->references('id')
                ->on('ventes')
                ->onDelete('cascade');

            $table->foreign('produit_id', 'fk_vente_details_produits')
                ->references('id')
                ->on('produits')
                ->onDelete('cascade');
            $table->integer('quantite');
            $table->decimal('prix_unitaire', 15, 2);
            $table->decimal('montant_tva', 15, 2);
            $table->decimal('montant_ttc', 15, 2);
            $table->timestamps();
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('vente_details');
    }
};
