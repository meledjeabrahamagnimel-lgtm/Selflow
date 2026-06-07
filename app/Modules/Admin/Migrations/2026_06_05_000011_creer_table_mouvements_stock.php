<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mouvements_stock', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produit_id')->index();
            $table->unsignedBigInteger('point_de_vente_id')->index();

            $table->foreign('produit_id', 'fk_mouvements_stock_produits')
                ->references('id')
                ->on('produits')
                ->onDelete('cascade');

            $table->foreign('point_de_vente_id', 'fk_mouvements_stock_points_de_vente')
                ->references('id')
                ->on('points_de_vente')
                ->onDelete('cascade');
            $table->string('type_mouvement')->index(); // 'Entrée', 'Sortie'
            $table->integer('quantite');
            $table->integer('stock_avant');
            $table->integer('stock_apres');
            $table->string('reference_document')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mouvements_stock');
    }
};
