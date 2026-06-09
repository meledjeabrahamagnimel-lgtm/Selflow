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
        Schema::table('utilisateurs', function (Blueprint $table) {
            $table->string('jeton_api', 80)->nullable()->unique()->after('password')->index();
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::table('utilisateurs', function (Blueprint $table) {
            $table->dropColumn('jeton_api');
        });
    }
};
