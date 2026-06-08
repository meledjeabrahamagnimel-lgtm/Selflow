<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajouter les champs fiscaux/légaux aux tables entreprises, clients et fournisseurs.
     */
    public function up(): void
    {
        // ── Table entreprises : champs fiscaux + logos ──────────────────
        Schema::table('entreprises', function (Blueprint $table) {
            $table->string('ncc')->nullable()->comment('Numéro de Compte Contribuable')->after('compte_contribuable');
            $table->string('regime_imposition')->nullable()->comment('Ex: TEE, RSI, RNI')->after('ncc');
            $table->string('centre_impots')->nullable()->comment('Ex: 807 Impôts de Cocody')->after('regime_imposition');
            $table->text('ref_bancaire')->nullable()->comment('Informations bancaires texte libre')->after('centre_impots');
            $table->string('logo_path')->nullable()->comment('Chemin du logo principal de l\'entreprise')->after('ref_bancaire');
            $table->string('logo_fne_path')->nullable()->comment('Chemin du logo secondaire (ex: FNE)')->after('logo_path');
        });

        // ── Table clients : champs fiscaux ──────────────────────────────
        Schema::table('clients', function (Blueprint $table) {
            $table->string('ncc')->nullable()->comment('Numéro de Compte Contribuable')->after('adresse');
            $table->string('regime_imposition')->nullable()->comment('Ex: TEE, RSI, RNI')->after('ncc');
        });

        // ── Table fournisseurs : champs fiscaux + adresse ──────────────
        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->string('adresse')->nullable()->after('email');
            $table->string('ncc')->nullable()->comment('Numéro de Compte Contribuable')->after('adresse');
            $table->string('regime_imposition')->nullable()->comment('Ex: TEE, RSI, RNI')->after('ncc');
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            $table->dropColumn(['ncc', 'regime_imposition', 'centre_impots', 'ref_bancaire', 'logo_path', 'logo_fne_path']);
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['ncc', 'regime_imposition']);
        });

        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->dropColumn(['adresse', 'ncc', 'regime_imposition']);
        });
    }
};
