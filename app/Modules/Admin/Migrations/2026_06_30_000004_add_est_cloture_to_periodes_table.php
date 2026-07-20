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
        if (Schema::hasTable('periodes')) {
            Schema::table('periodes', function (Blueprint $table) {
                if (!Schema::hasColumn('periodes', 'est_cloture')) {
                    $table->boolean('est_cloture')->default(false)->index()->after('est_active');
                }
            });
        }
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        if (Schema::hasTable('periodes')) {
            Schema::table('periodes', function (Blueprint $table) {
                if (Schema::hasColumn('periodes', 'est_cloture')) {
                    $table->dropColumn('est_cloture');
                }
            });
        }
    }
};
