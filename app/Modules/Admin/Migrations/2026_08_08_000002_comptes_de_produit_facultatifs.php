<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les comptes d'un produit deviennent facultatifs, et perdent leurs valeurs
 * par défaut.
 *
 * Deux défauts se cumulaient.
 *
 * Les colonnes étaient obligatoires, alors que tout produit n'a pas les deux
 * comptes : une matière première ne se vend pas, un service ne s'achète pas.
 * Le référentiel laisse d'ailleurs la case vide dans ces cas. Les remplir
 * forçait à inventer un compte, qui se serait retrouvé au grand livre.
 *
 * Et leurs valeurs par défaut — `701100` et `601100` — étaient précisément
 * celles que l'acte uniforme réserve à la ventilation géographique des ventes
 * (« Dans la Région »). Tout produit créé sans compte explicite atterrissait
 * donc là, et la liasse fiscale l'aurait compté comme une vente régionale.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->string('compte_vente')->nullable()->default(null)->change();
            $table->string('compte_achat')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->string('compte_vente')->nullable(false)->default('701100')->change();
            $table->string('compte_achat')->nullable(false)->default('601100')->change();
        });
    }
};
