<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distinguer les journaux que l'utilisateur saisit de ceux que le système écrit.
 *
 * Le report à nouveau n'est jamais saisi à la main : c'est la clôture qui
 * l'écrit, en reprenant les soldes d'un exercice sur le suivant. Il doit
 * pourtant exister dès le départ — le créer au moment où l'on en a besoin,
 * c'est le créer dans l'urgence — mais il n'a rien à faire dans la liste
 * déroulante d'une saisie d'opération.
 *
 * Un journal `systeme` reste visible en consultation — grand livre, filtres,
 * balance — et disparaît seulement des listes de saisie. Il ne s'archive pas
 * non plus : la clôture en aurait besoin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('codes_journaux', function (Blueprint $table) {
            $table->boolean('systeme')->default(false)->after('compte')->index();
        });
    }

    public function down(): void
    {
        Schema::table('codes_journaux', function (Blueprint $table) {
            $table->dropColumn('systeme');
        });
    }
};
