<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration de conformité comptable — Achat externe (Écart N°2)
 *
 * Ajoute :
 *  - `numero_facture_fournisseur` : numéro de facture du fournisseur externe
 *    (saisi manuellement par l'acheteur quand le fournisseur n'est pas sur Selflow).
 *    Pour un achat B2B inter-Selflow, ce champ est alimenté automatiquement.
 */
return new class extends Migration
{
    /**
     * Exécuter la migration.
     */
    public function up(): void
    {
        Schema::table('achats', function (Blueprint $table) {
            // Numéro de facture fourni par le fournisseur externe (papier/FNE)
            $table->string('numero_facture_fournisseur', 100)
                ->nullable()
                ->after('numero_facture')
                ->comment('N° facture fournisseur externe, saisi manuellement (ou FNE reçu par API DGI)');
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::table('achats', function (Blueprint $table) {
            $table->dropColumn('numero_facture_fournisseur');
        });
    }
};
