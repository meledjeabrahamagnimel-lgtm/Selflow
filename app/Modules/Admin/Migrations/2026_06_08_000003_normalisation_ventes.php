<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajouter les champs de normalisation DGI et remise aux ventes,
     * et le champ unité aux détails de vente.
     */
    public function up(): void
    {
        // ── Table ventes ────────────────────────────────────────────────
        Schema::table('ventes', function (Blueprint $table) {
            $table->string('type_facture')->default('proformat')->after('statut');
            // 'proformat' = facture pro-forma | 'normale' = facture normalisée DGI
            $table->boolean('normalise')->default(false)->after('type_facture');
            $table->text('qr_code_data')->nullable()->after('normalise');
            $table->decimal('remise', 15, 2)->default(0)->after('montant_ttc');
        });

        // ── Table vente_details ─────────────────────────────────────────
        Schema::table('vente_details', function (Blueprint $table) {
            $table->string('unite')->nullable()->default('Unité')->after('quantite');
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn(['type_facture', 'normalise', 'qr_code_data', 'remise']);
        });

        Schema::table('vente_details', function (Blueprint $table) {
            $table->dropColumn('unite');
        });
    }
};
