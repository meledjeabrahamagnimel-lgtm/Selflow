<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le stock porte sa valeur.
 *
 * Jusqu'ici, `produits.prix_achat` tenait lieu de coût : un prix de catalogue,
 * figé, saisi une fois. Deux conséquences que rien ne signalait :
 *
 * - **la marge affichée était fausse** dès que le fournisseur changeait ses
 *   prix. Un sac acheté 12 000 puis 15 000 restait valorisé au prix de la
 *   fiche, et la vente à 17 000 annonçait une marge qu'on n'avait pas faite ;
 * - **le bilan ne pouvait pas être établi.** Valoriser un stock demande un
 *   coût par article, pas un prix indicatif.
 *
 * La décision arrêtée est le **CUMP** (Coût Unitaire Moyen Pondéré) : à chaque
 * entrée, le coût moyen se recalcule en pondérant l'ancien stock par son coût
 * et l'entrée par le sien. Les sorties se valorisent à ce coût moyen, sans le
 * modifier.
 *
 *     CUMP = (quantité ancienne × CUMP ancien + quantité entrée × coût entrée)
 *            ÷ (quantité ancienne + quantité entrée)
 *
 * Il est porté par la **fiche de stock** — le couple article / site — et non
 * par l'article : le même sac de riz peut arriver à des coûts différents à
 * Abidjan et à Bouaké, transport compris. Un CUMP (Coût Unitaire Moyen
 * Pondéré) global mélangerait les deux et fausserait les deux.
 *
 * Quatre décimales, là où les quantités en ont trois : le coût unitaire d'un
 * article vendu au gramme se joue au centime du millier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->decimal('cump', 15, 4)->default(0)->after('quantite_disponible');
        });

        // Le coût auquel chaque mouvement a été valorisé, figé au moment où il
        // s'écrit. Sans lui, revaloriser le passé serait possible — et un
        // journal dont les valeurs bougent n'est pas un journal.
        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->decimal('cout_unitaire', 15, 4)->nullable()->after('stock_apres');
            $table->decimal('cump_apres', 15, 4)->nullable()->after('cout_unitaire');
        });
    }

    public function down(): void
    {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('cump');
        });

        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->dropColumn(['cout_unitaire', 'cump_apres']);
        });
    }
};
