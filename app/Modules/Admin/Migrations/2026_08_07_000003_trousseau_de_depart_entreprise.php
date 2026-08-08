<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trousseau de départ d'une entreprise : comptes et journaux archivables.
 *
 * Une entreprise reçoit à sa création de quoi travailler tout de suite —
 * comptes communs, journal des ventes, des achats, caisse, banque, mobile
 * money. Elle n'utilisera pas tout, et c'est prévu : ce qui ne sert pas
 * s'archive, il ne se supprime pas. Un compte ou un journal supprimé
 * laisserait des écritures orphelines dès qu'il aurait servi une fois.
 *
 * Le compte d'un journal devient facultatif : « Journal des ventes » et
 * « Opérations diverses » n'en ont aucun, seuls les journaux de trésorerie en
 * portent un. La colonne était obligatoire, ce qui forçait à inventer une
 * valeur pour les trois quarts des journaux.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_comptable', function (Blueprint $table) {
            $table->timestamp('archive_le')->nullable()->after('libelle')->index();
        });

        Schema::table('codes_journaux', function (Blueprint $table) {
            $table->timestamp('archive_le')->nullable()->after('compte')->index();
            $table->string('compte')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('plan_comptable', function (Blueprint $table) {
            $table->dropColumn('archive_le');
        });

        Schema::table('codes_journaux', function (Blueprint $table) {
            $table->dropColumn('archive_le');
        });
    }
};
