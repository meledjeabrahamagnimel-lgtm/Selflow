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
        Schema::create('ventes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('point_de_vente_id')->index();
            $table->unsignedBigInteger('client_id')->nullable()->index();

            $table->foreign('point_de_vente_id', 'fk_ventes_points_de_vente')
                ->references('id')
                ->on('points_de_vente')
                ->onDelete('cascade');

            $table->foreign('client_id', 'fk_ventes_clients')
                ->references('id')
                ->on('clients')
                ->onDelete('set null');
            $table->string('numero_facture')->index();
            $table->date('date_vente')->index();
            $table->string('mode_paiement')->index(); // 'Espèces', 'Mobile Money', 'Carte'
            $table->decimal('montant_ht', 15, 2);
            $table->decimal('montant_tva', 15, 2);
            $table->decimal('montant_ttc', 15, 2);
            $table->string('statut')->default('Payé')->index(); // 'Payé', 'En attente'
            $table->timestamps();
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('ventes');
    }
};
