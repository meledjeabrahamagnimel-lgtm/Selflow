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
        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id')->index();
            $table->foreign('entreprise_id', 'fk_produits_entreprises')
                ->references('id')
                ->on('entreprises')
                ->onDelete('cascade');
            $table->string('reference')->index();
            $table->string('nom');
            $table->string('categorie')->index();
            $table->decimal('prix_achat', 15, 2);
            $table->decimal('prix_vente', 15, 2);
            $table->integer('stock_actuel')->default(0);
            $table->integer('stock_minimum')->default(5);
            $table->integer('quantite_commandee')->default(0);
            $table->integer('quantite_a_receptionner')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
