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
        Schema::table('points_de_vente', function (Blueprint $table) {
            if (!Schema::hasColumn('points_de_vente', 'code_fne')) {
                $table->string('code_fne')->nullable()->after('telephone');
            }
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::table('points_de_vente', function (Blueprint $table) {
            if (Schema::hasColumn('points_de_vente', 'code_fne')) {
                $table->dropColumn('code_fne');
            }
        });
    }
};
