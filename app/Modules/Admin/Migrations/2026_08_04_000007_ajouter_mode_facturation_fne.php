<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mode de facturation observé sur la plateforme FNE.
 *
 * La DGI facture la certification de deux manières, au choix de l'entreprise
 * dans ses propres paramètres : par STICKERS (des vignettes décomptées à
 * l'unité) ou par PROVISION (un solde en francs). Ce réglage se coche chez la
 * DGI et l'API n'expose aucun champ pour le lire.
 *
 * En revanche, la réponse de certification trahit le mode retenu : elle porte
 * `balance_sticker` dans un cas, `balance_funds` dans l'autre. On enregistre
 * donc ce que la plateforme nous a répondu la dernière fois, plutôt que de
 * convertir l'un en l'autre au moyen d'un prix unitaire supposé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            // 'stickers' | 'provision' | null tant qu'aucune pièce n'a été
            // normalisée : on ne devine pas, on constate.
            $table->string('fne_mode_facturation', 20)->nullable()->after('fne_sticker_balance');

            // Solde en francs, renseigné uniquement en mode provision. Il ne
            // se convertit pas en nombre de stickers : le prix unitaire n'est
            // pas transmis par l'API.
            $table->decimal('fne_solde_provision', 15, 2)->nullable()->after('fne_mode_facturation');

            // Date de la dernière réponse ayant renseigné le solde : un solde
            // sans date ne dit pas s'il vaut encore quelque chose.
            $table->timestamp('fne_solde_maj_at')->nullable()->after('fne_solde_provision');
        });
    }

    public function down(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            $table->dropColumn(['fne_mode_facturation', 'fne_solde_provision', 'fne_solde_maj_at']);
        });
    }
};
