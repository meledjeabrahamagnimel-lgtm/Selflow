<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les quantités cessent d'être entières.
 *
 * `quantite_disponible` était un `integer`. Le référentiel livre pourtant des
 * kilos, des litres, des mètres carrés et des sacs : 12,5 kg de cacao entraient
 * en stock pour 12, et la demi-mesure disparaissait sans que rien ne le
 * signale. Sur un an de réceptions, l'écart entre le stock théorique et le
 * comptage physique n'aurait eu aucune explication traçable.
 *
 * La correction doit porter sur toute la chaîne, pas seulement sur la fiche de
 * stock : une quantité tronquée sur la ligne de vente l'est déjà avant
 * d'atteindre le stock. Sont donc concernées les six tables où une quantité
 * physique circule.
 *
 * **Trois décimales**, et pas davantage. C'est le gramme sur le kilo, le
 * millilitre sur le litre — la précision à laquelle une balance commerciale
 * mesure, et celle qu'un inventaire physique sait recompter. Deux tables
 * gardent leurs quatre décimales : `fiche_technique_details.quantite` et
 * `ordres_production.quantite_cible`. La première n'est pas une quantité mais
 * un coefficient de nomenclature — 0,0125 kg de colorant par unité produite —
 * et arrondir un coefficient fausse toute la série qu'il multiplie.
 *
 * `stock_minimum` et `stock_maximum` suivent : un seuil d'alerte sur une
 * matière qui se compte en litres se fixe aussi en litres.
 */
return new class extends Migration
{
    /**
     * Les colonnes à convertir, par table.
     *
     * @var array<string, array<int, string>>
     */
    private const COLONNES = [
        'stocks'            => ['quantite_disponible', 'stock_minimum', 'stock_maximum'],
        'mouvements_stock'  => ['quantite', 'stock_avant', 'stock_apres'],
        'vente_details'     => ['quantite', 'quantite_livree'],
        'achat_details'     => ['quantite', 'quantite_receptionnee'],
        'produits'          => ['quantite_commandee', 'quantite_a_receptionner'],
        'transferts_stock'  => ['quantite'],
    ];

    /**
     * Colonnes sans valeur par défaut : `quantite` est toujours saisie, la
     * doter d'un défaut laisserait passer une ligne sans quantité.
     *
     * @var array<int, string>
     */
    private const SANS_DEFAUT = [
        'mouvements_stock.quantite',
        'mouvements_stock.stock_avant',
        'mouvements_stock.stock_apres',
        'vente_details.quantite',
        'achat_details.quantite',
        'transferts_stock.quantite',
    ];

    public function up(): void
    {
        $this->convertir(fn (Blueprint $table, string $colonne, bool $avecDefaut) => $avecDefaut
            ? $table->decimal($colonne, 15, 3)->default(0)->change()
            : $table->decimal($colonne, 15, 3)->change());
    }

    public function down(): void
    {
        $this->convertir(fn (Blueprint $table, string $colonne, bool $avecDefaut) => $avecDefaut
            ? $table->integer($colonne)->default(0)->change()
            : $table->integer($colonne)->change());
    }

    /**
     * Appliquer une conversion à toutes les colonnes déclarées.
     *
     * Une colonne absente est sautée : ces tables ont été bâties par des
     * migrations successives, et une base restaurée d'un état intermédiaire ne
     * doit pas bloquer sur une colonne qui n'existe pas encore.
     */
    private function convertir(callable $conversion): void
    {
        foreach (self::COLONNES as $nomTable => $colonnes) {
            if (!Schema::hasTable($nomTable)) {
                continue;
            }

            $presentes = array_values(array_filter(
                $colonnes,
                fn ($colonne) => Schema::hasColumn($nomTable, $colonne)
            ));

            if ($presentes === []) {
                continue;
            }

            Schema::table($nomTable, function (Blueprint $table) use ($nomTable, $presentes, $conversion) {
                foreach ($presentes as $colonne) {
                    $conversion($table, $colonne, !in_array("{$nomTable}.{$colonne}", self::SANS_DEFAUT, true));
                }
            });
        }
    }
};
