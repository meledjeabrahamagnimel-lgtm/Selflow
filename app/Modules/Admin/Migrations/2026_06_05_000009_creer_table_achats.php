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
        Schema::create('achats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('point_de_vente_id')->index();
            $table->unsignedBigInteger('fournisseur_id')->index();

            $table->foreign('point_de_vente_id', 'fk_achats_points_de_vente')
                ->references('id')
                ->on('points_de_vente')
                ->onDelete('cascade');

            $table->foreign('fournisseur_id', 'fk_achats_fournisseurs')
                ->references('id')
                ->on('fournisseurs')
                ->onDelete('cascade');
            $table->string('numero_facture')->index();
            $table->date('date_achat')->index();
            $table->string('mode_paiement')->index(); // 'Espèces', 'Mobile Money', 'Virement', 'Chèque'
            $table->decimal('montant_ht', 15, 2);
            $table->decimal('montant_tva', 15, 2);
            $table->decimal('montant_ttc', 15, 2);
            $table->string('statut')->default('Payé')->index();
            $table->timestamps();
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('achats');
    }
};
