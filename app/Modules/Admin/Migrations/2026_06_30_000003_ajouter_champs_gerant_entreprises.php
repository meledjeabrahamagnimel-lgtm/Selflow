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
        Schema::table('entreprises', function (Blueprint $table) {
            $table->string('gerant_nom')->nullable()->after('nom');
            $table->string('gerant_prenom')->nullable()->after('gerant_nom');
            $table->string('gerant_fonction')->nullable()->after('gerant_prenom');
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            $table->dropColumn(['gerant_nom', 'gerant_prenom', 'gerant_fonction']);
        });
    }
};
