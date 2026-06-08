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
        Schema::table('vente_details', function (Blueprint $table) {
            $table->unsignedBigInteger('produit_id')->nullable()->change();
            $table->string('libelle_virtuel')->nullable()->after('produit_id');
        });

        Schema::create('banques', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id')->index();
            $table->foreign('entreprise_id', 'fk_banques_entreprises')
                ->references('id')
                ->on('entreprises')
                ->onDelete('cascade');
            $table->string('nom');
            $table->string('numero_compte');
            $table->timestamps();
        });

        Schema::create('codes_journaux', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id')->index();
            $table->foreign('entreprise_id', 'fk_codes_journaux_entreprises')
                ->references('id')
                ->on('entreprises')
                ->onDelete('cascade');
            $table->string('type');
            $table->string('code')->index();
            $table->string('intitule');
            $table->string('compte');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('codes_journaux');
        Schema::dropIfExists('banques');
        Schema::table('vente_details', function (Blueprint $table) {
            $table->unsignedBigInteger('produit_id')->nullable(false)->change();
            $table->dropColumn('libelle_virtuel');
        });
    }
};
