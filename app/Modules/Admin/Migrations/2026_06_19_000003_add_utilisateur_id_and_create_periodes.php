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
        // 1. Ajouter utilisateur_id aux tables transactionnelles
        if (Schema::hasTable('ventes') && !Schema::hasColumn('ventes', 'utilisateur_id')) {
            Schema::table('ventes', function (Blueprint $table) {
                $table->unsignedBigInteger('utilisateur_id')->nullable()->after('point_de_vente_id')->index();
                $table->foreign('utilisateur_id')->references('id')->on('utilisateurs')->onDelete('set null');
            });
        }

        if (Schema::hasTable('achats') && !Schema::hasColumn('achats', 'utilisateur_id')) {
            Schema::table('achats', function (Blueprint $table) {
                $table->unsignedBigInteger('utilisateur_id')->nullable()->after('point_de_vente_id')->index();
                $table->foreign('utilisateur_id')->references('id')->on('utilisateurs')->onDelete('set null');
            });
        }

        if (Schema::hasTable('tresorerie_journal') && !Schema::hasColumn('tresorerie_journal', 'utilisateur_id')) {
            Schema::table('tresorerie_journal', function (Blueprint $table) {
                $table->unsignedBigInteger('utilisateur_id')->nullable()->after('point_de_vente_id')->index();
                $table->foreign('utilisateur_id')->references('id')->on('utilisateurs')->onDelete('set null');
            });
        }

        // 2. Créer la table des périodes
        Schema::create('periodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id')->index();
            $table->foreign('entreprise_id')->references('id')->on('entreprises')->onDelete('cascade');
            $table->string('nom')->index();
            $table->date('date_debut')->index();
            $table->date('date_fin')->index();
            $table->boolean('est_active')->default(false)->index();
            $table->timestamps();
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('periodes');

        if (Schema::hasTable('tresorerie_journal') && Schema::hasColumn('tresorerie_journal', 'utilisateur_id')) {
            Schema::table('tresorerie_journal', function (Blueprint $table) {
                $table->dropForeign(['utilisateur_id']);
                $table->dropColumn('utilisateur_id');
            });
        }

        if (Schema::hasTable('achats') && Schema::hasColumn('achats', 'utilisateur_id')) {
            Schema::table('achats', function (Blueprint $table) {
                $table->dropForeign(['utilisateur_id']);
                $table->dropColumn('utilisateur_id');
            });
        }

        if (Schema::hasTable('ventes') && Schema::hasColumn('ventes', 'utilisateur_id')) {
            Schema::table('ventes', function (Blueprint $table) {
                $table->dropForeign(['utilisateur_id']);
                $table->dropColumn('utilisateur_id');
            });
        }
    }
};
