<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le plan comptable OHADA rejoint le référentiel.
 *
 * `referentiel_comptes` ne portait que les 34 comptes que tout profil reçoit.
 * Or le référentiel impute sur des subdivisions — la famille « Vivres » vend en
 * `701100` — qu'aucune table ne nommait : le plan comptable d'une entreprise
 * affichait des numéros sans intitulé, et la balance à venir aurait été
 * illisible.
 *
 * Les 1 256 comptes de l'acte uniforme OHADA sont désormais chargés comme
 * dictionnaire de référence. `commun` distingue les 34 que chaque entreprise
 * reçoit d'office du reste, qui ne sert qu'à nommer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referentiel_comptes', function (Blueprint $table) {
            // Racine OHADA d'origine — `701` pour `701000`, `6031` pour
            // `603100`. Elle sert à nommer une subdivision que le plan ne
            // connaît pas : `603110` hérite de « Variations des stocks de
            // marchandises », que la famille vient ensuite qualifier.
            $table->string('racine', 6)->nullable()->after('numero')->index();
            $table->unsignedTinyInteger('classe')->nullable()->after('intitule')->index();
            $table->boolean('commun')->default(false)->after('classe')->index();
        });
    }

    public function down(): void
    {
        Schema::table('referentiel_comptes', function (Blueprint $table) {
            $table->dropColumn(['racine', 'classe', 'commun']);
        });
    }
};
