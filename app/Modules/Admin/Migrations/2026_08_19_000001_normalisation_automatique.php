<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le choix de normaliser tout seul, ou à la main.
 *
 * La normalisation partait systématiquement à l'enregistrement de la facture,
 * sans que personne ne puisse s'y opposer. C'est le bon comportement pour une
 * caisse qui tourne, mais pas pour une entreprise qui vérifie ses pièces avant
 * de les certifier — et une pièce certifiée ne se reprend pas.
 *
 * Deux réglages, séparés parce que les deux usages le sont : une boutique peut
 * vouloir certifier ses factures à la main, et ses tickets de caisse
 * automatiquement.
 *
 * Le défaut reprend le comportement actuel — automatique — pour ne rien
 * changer aux entreprises déjà en service sans qu'elles l'aient demandé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entreprises', function (Blueprint $colonnes) {
            if (!Schema::hasColumn('entreprises', 'normalisation_auto_factures')) {
                $colonnes->boolean('normalisation_auto_factures')
                    ->default(true)
                    ->after('possede_compte_fne');
            }

            if (!Schema::hasColumn('entreprises', 'normalisation_auto_recus')) {
                $colonnes->boolean('normalisation_auto_recus')
                    ->default(true)
                    ->after('normalisation_auto_factures');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entreprises', function (Blueprint $colonnes) {
            foreach (['normalisation_auto_factures', 'normalisation_auto_recus'] as $colonne) {
                if (Schema::hasColumn('entreprises', $colonne)) {
                    $colonnes->dropColumn($colonne);
                }
            }
        });
    }
};
