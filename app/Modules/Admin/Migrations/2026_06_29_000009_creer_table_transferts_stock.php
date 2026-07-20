<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transferts_stock', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('produit_id')->index();
            $table->foreign('produit_id')->references('id')->on('produits')->cascadeOnDelete();

            $table->unsignedBigInteger('point_de_vente_source_id')->index();
            $table->foreign('point_de_vente_source_id', 'fk_ts_pdv_source')
                ->references('id')->on('points_de_vente')->cascadeOnDelete();

            $table->unsignedBigInteger('point_de_vente_destination_id')->index();
            $table->foreign('point_de_vente_destination_id', 'fk_ts_pdv_destination')
                ->references('id')->on('points_de_vente')->cascadeOnDelete();

            $table->decimal('quantite', 12, 2);

            // Workflow : admin → approuvé directement ; autre → en_attente
            $table->enum('statut', ['en_attente', 'approuve', 'rejete'])->default('en_attente')->index();

            $table->unsignedBigInteger('demandeur_id')->index()
                ->comment('Utilisateur ayant initié la demande');
            $table->foreign('demandeur_id', 'fk_ts_demandeur')
                ->references('id')->on('utilisateurs')->cascadeOnDelete();

            $table->unsignedBigInteger('approbateur_id')->nullable()->index()
                ->comment('Admin ayant approuvé ou rejeté');
            $table->foreign('approbateur_id', 'fk_ts_approbateur')
                ->references('id')->on('utilisateurs')->nullOnDelete();

            $table->text('note')->nullable();
            $table->timestamp('approuve_le')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transferts_stock');
    }
};
