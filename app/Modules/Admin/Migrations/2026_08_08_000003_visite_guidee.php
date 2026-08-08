<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La visite guidée de première utilisation.
 *
 * Elle se retient par utilisateur, pas par entreprise : un vendeur qui rejoint
 * une entreprise déjà configurée découvre l'application pour la première fois,
 * lui aussi. Et elle se retient en base, pas dans le navigateur — changer de
 * poste ne doit pas la faire recommencer, ni la faire disparaître pour
 * quelqu'un qui ne l'a jamais vue.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utilisateurs', function (Blueprint $table) {
            $table->timestamp('visite_guidee_terminee_le')->nullable()->after('statut');
        });
    }

    public function down(): void
    {
        Schema::table('utilisateurs', function (Blueprint $table) {
            $table->dropColumn('visite_guidee_terminee_le');
        });
    }
};
