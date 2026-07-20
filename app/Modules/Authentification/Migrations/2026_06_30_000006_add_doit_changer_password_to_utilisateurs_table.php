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
        if (Schema::hasTable('utilisateurs')) {
            Schema::table('utilisateurs', function (Blueprint $table) {
                if (!Schema::hasColumn('utilisateurs', 'doit_changer_password')) {
                    $table->boolean('doit_changer_password')->default(false)->after('jeton_api');
                }
            });
        }
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        if (Schema::hasTable('utilisateurs')) {
            Schema::table('utilisateurs', function (Blueprint $table) {
                if (Schema::hasColumn('utilisateurs', 'doit_changer_password')) {
                    $table->dropColumn('doit_changer_password');
                }
            });
        }
    }
};
