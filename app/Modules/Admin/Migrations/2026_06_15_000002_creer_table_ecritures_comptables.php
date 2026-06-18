<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('ecritures_comptables', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id')->index();
            $table->unsignedBigInteger('point_de_vente_id')->nullable()->index();
            $table->date('date_ecriture')->index();
            $table->string('libelle');
            $table->string('reference_document')->index(); // e.g., VT-2026-0001, AC-2026-0001
            $table->string('code_journal')->index(); // e.g., 'VE' (Vente), 'AC' (Achat), 'CA' (Caisse), 'BQ' (Banque)
            $table->string('compte_debit')->nullable()->index();
            $table->string('compte_credit')->nullable()->index();
            $table->decimal('debit', 15, 2)->default(0.00);
            $table->decimal('credit', 15, 2)->default(0.00);
            $table->timestamps();

            $table->foreign('entreprise_id', 'fk_ecritures_entreprises')
                ->references('id')
                ->on('entreprises')
                ->onDelete('cascade');

            $table->foreign('point_de_vente_id', 'fk_ecritures_points_de_vente')
                ->references('id')
                ->on('points_de_vente')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ecritures_comptables');
    }
};
