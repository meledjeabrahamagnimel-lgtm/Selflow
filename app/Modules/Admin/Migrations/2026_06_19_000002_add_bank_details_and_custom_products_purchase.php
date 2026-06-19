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
        // 1. Modifier la table 'achat_details'
        Schema::table('achat_details', function (Blueprint $table) {
            $table->dropForeign('fk_achat_details_produits');
        });

        Schema::table('achat_details', function (Blueprint $table) {
            $table->unsignedBigInteger('produit_id')->nullable()->change();
            $table->string('libelle_virtuel')->nullable()->after('produit_id');
            $table->string('unite')->nullable()->default('Unité')->after('quantite');

            $table->foreign('produit_id', 'fk_achat_details_produits')
                ->references('id')
                ->on('produits')
                ->onDelete('cascade');
        });

        // 2. Ajouter les champs bancaires à 'ventes'
        Schema::table('ventes', function (Blueprint $table) {
            $table->string('moyen_bancaire')->nullable()->after('mode_paiement');
            $table->string('reference_paiement')->nullable()->after('moyen_bancaire');
        });

        // 3. Ajouter les champs bancaires à 'achats'
        Schema::table('achats', function (Blueprint $table) {
            $table->string('moyen_bancaire')->nullable()->after('mode_paiement');
            $table->string('reference_paiement')->nullable()->after('moyen_bancaire');
        });

        // 4. Ajouter les champs bancaires à 'tresorerie_journal'
        Schema::table('tresorerie_journal', function (Blueprint $table) {
            $table->string('moyen_bancaire')->nullable()->after('mode_paiement');
            $table->string('reference_paiement')->nullable()->after('moyen_bancaire');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tresorerie_journal', function (Blueprint $table) {
            $table->dropColumn(['moyen_bancaire', 'reference_paiement']);
        });

        Schema::table('achats', function (Blueprint $table) {
            $table->dropColumn(['moyen_bancaire', 'reference_paiement']);
        });

        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn(['moyen_bancaire', 'reference_paiement']);
        });

        Schema::table('achat_details', function (Blueprint $table) {
            $table->dropForeign('fk_achat_details_produits');
        });

        Schema::table('achat_details', function (Blueprint $table) {
            $table->unsignedBigInteger('produit_id')->nullable(false)->change();
            $table->dropColumn(['libelle_virtuel', 'unite']);

            $table->foreign('produit_id', 'fk_achat_details_produits')
                ->references('id')
                ->on('produits')
                ->onDelete('cascade');
        });
    }
};
