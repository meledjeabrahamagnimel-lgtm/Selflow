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
        // 1. Index sur les ventes
        try {
            Schema::table('ventes', function (Blueprint $table) {
                $table->index(['point_de_vente_id', 'date_vente'], 'idx_ventes_pdv_date');
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('ventes', function (Blueprint $table) {
                $table->index('date_vente', 'idx_ventes_date');
            });
        } catch (\Throwable $e) {}

        // 2. Index sur les achats
        try {
            Schema::table('achats', function (Blueprint $table) {
                $table->index(['point_de_vente_id', 'date_achat'], 'idx_achats_pdv_date');
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('achats', function (Blueprint $table) {
                $table->index('date_achat', 'idx_achats_date');
            });
        } catch (\Throwable $e) {}

        // 3. Index sur les mouvements de stock
        try {
            Schema::table('mouvements_stock', function (Blueprint $table) {
                $table->index(['point_de_vente_id', 'created_at'], 'idx_mouv_pdv_created');
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('mouvements_stock', function (Blueprint $table) {
                $table->index(['produit_id', 'created_at'], 'idx_mouv_prod_created');
            });
        } catch (\Throwable $e) {}

        // 4. Index sur le journal de trésorerie
        try {
            Schema::table('tresorerie_journal', function (Blueprint $table) {
                $table->index(['point_de_vente_id', 'date_operation'], 'idx_treso_pdv_date');
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('tresorerie_journal', function (Blueprint $table) {
                $table->index('date_operation', 'idx_treso_date');
            });
        } catch (\Throwable $e) {}
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        try {
            Schema::table('tresorerie_journal', function (Blueprint $table) {
                $table->dropIndex('idx_treso_pdv_date');
                $table->dropIndex('idx_treso_date');
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('mouvements_stock', function (Blueprint $table) {
                $table->dropIndex('idx_mouv_pdv_created');
                $table->dropIndex('idx_mouv_prod_created');
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('achats', function (Blueprint $table) {
                $table->dropIndex('idx_achats_pdv_date');
                $table->dropIndex('idx_achats_date');
            });
        } catch (\Throwable $e) {}

        try {
            Schema::table('ventes', function (Blueprint $table) {
                $table->dropIndex('idx_ventes_pdv_date');
                $table->dropIndex('idx_ventes_date');
            });
        } catch (\Throwable $e) {}
    }
};
