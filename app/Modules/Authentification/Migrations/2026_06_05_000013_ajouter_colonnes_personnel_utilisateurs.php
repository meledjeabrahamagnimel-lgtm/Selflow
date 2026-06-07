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
            $table->string('prenom')->nullable()->after('nom');
            $table->string('fonction')->nullable()->after('role');
            $table->date('date_debut_contrat')->nullable()->after('fonction');
            $table->date('date_fin_contrat')->nullable()->after('date_debut_contrat');
            $table->text('notes')->nullable()->after('statut');
            $table->json('habilitations')->nullable()->after('notes');
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::table('utilisateurs', function (Blueprint $table) {
            $table->dropColumn([
                'prenom',
                'fonction',
                'date_debut_contrat',
                'date_fin_contrat',
                'notes',
                'habilitations',
            ]);
        });
    }
};
