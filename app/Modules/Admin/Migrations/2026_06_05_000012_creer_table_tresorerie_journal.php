<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tresorerie_journal', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('point_de_vente_id')->index();

            $table->foreign('point_de_vente_id', 'fk_tresorerie_journal_points_de_vente')
                ->references('id')
                ->on('points_de_vente')
                ->onDelete('cascade');
            $table->date('date_operation')->index();
            $table->string('type_operation')->index(); // 'Encaissement', 'Décaissement'
            $table->string('libelle');
            $table->string('mode_paiement')->nullable()->index();
            $table->decimal('montant_entree', 15, 2)->default(0);
            $table->decimal('montant_sortie', 15, 2)->default(0);
            $table->decimal('solde_resultat', 15, 2)->default(0);
            $table->string('reference_document')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tresorerie_journal');
    }
};
