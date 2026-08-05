<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'entreprise dispose-t-elle déjà d'un compte sur la plateforme FNE ?
 *
 * La question conditionne ce qu'on lui demande à l'ouverture du compte
 * Selflow : reporter les informations de son espace FNE pour qu'elles
 * concordent, ou rassembler celles qu'il faut pour l'ouvrir.
 *
 * Trois états, d'où le champ nullable : null tant que la question n'a pas été
 * posée — un `false` par défaut ferait croire à une réponse qui n'a jamais
 * été donnée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            $table->boolean('possede_compte_fne')->nullable()->after('fne_solde_maj_at');
        });
    }

    public function down(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            $table->dropColumn('possede_compte_fne');
        });
    }
};
