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
        Schema::table('vente_details', function (Blueprint $table) {
            if (!Schema::hasColumn('vente_details', 'fne_invoice_item_id')) {
                $table->uuid('fne_invoice_item_id')->nullable()->after('montant_ttc');
            }
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::table('vente_details', function (Blueprint $table) {
            if (Schema::hasColumn('vente_details', 'fne_invoice_item_id')) {
                $table->dropColumn('fne_invoice_item_id');
            }
        });
    }
};
