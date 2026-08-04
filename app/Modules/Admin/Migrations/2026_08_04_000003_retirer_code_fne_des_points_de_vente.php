<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Retrait du champ « Code FNE » des points de vente.
     *
     * La spécification DGI décrit `pointOfSale` comme « Nom du point de vente »
     * (Procedure_technique_integration_API, API #1 et #3). Le code technique
     * ajouté côté Selflow n'a donc pas lieu d'être : c'est le NOM du point de
     * vente — tel que déclaré dans l'espace FNE de l'entreprise — qui doit être
     * transmis.
     */
    public function up(): void
    {
        if (Schema::hasColumn('points_de_vente', 'code_fne')) {
            Schema::table('points_de_vente', function (Blueprint $table) {
                $table->dropColumn('code_fne');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('points_de_vente', 'code_fne')) {
            Schema::table('points_de_vente', function (Blueprint $table) {
                $table->string('code_fne')->nullable()->after('telephone');
            });
        }
    }
};
