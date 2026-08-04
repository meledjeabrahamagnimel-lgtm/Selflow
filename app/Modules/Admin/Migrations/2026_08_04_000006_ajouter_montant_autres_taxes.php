<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Montant des taxes personnalisées collectées sur la pièce.
     *
     * Ces taxes (GRA, AIRSI, DTD…) s'ajoutent au montant réclamé au client
     * mais ne sont ni du chiffre d'affaires ni de la TVA : elles sont
     * collectées pour le compte de l'État et lui seront reversées.
     *
     * `montant_ttc` reste le TTC fiscal (HT net + TVA), base des déclarations
     * et du payload FNE. Le montant réellement encaissé est la somme des deux,
     * exposée par l'accesseur `net_a_payer`.
     */
    public function up(): void
    {
        foreach (['ventes', 'achats'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (!Schema::hasColumn($table, 'montant_autres_taxes')) {
                    $blueprint->decimal('montant_autres_taxes', 15, 2)->default(0)->after('montant_ttc');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['ventes', 'achats'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('montant_autres_taxes');
            });
        }
    }
};
