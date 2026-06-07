<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achat_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('achat_id')->index();
            $table->unsignedBigInteger('produit_id')->index();

            $table->foreign('achat_id', 'fk_achat_details_achats')
                ->references('id')
                ->on('achats')
                ->onDelete('cascade');

            $table->foreign('produit_id', 'fk_achat_details_produits')
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

    public function down(): void
    {
        Schema::dropIfExists('achat_details');
    }
};
