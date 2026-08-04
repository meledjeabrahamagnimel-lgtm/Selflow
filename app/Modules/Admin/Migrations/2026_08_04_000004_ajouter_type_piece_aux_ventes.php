<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinguer enfin la FACTURE du REÇU.
     *
     * Jusqu'ici, la nature du document était déduite de l'absence de client :
     * toute vente au comptant sans client identifié était présentée comme un
     * « Reçu » dans le registre Factures & Reçus, alors qu'une vente en espèces
     * à un client de passage reste une facture. Inversement, un vrai reçu ne
     * pouvait pas être émis délibérément.
     *
     * `type_piece` porte désormais cette nature, choisie à la saisie :
     *   - 'facture' : facture de vente (valeur par défaut, comportement actuel)
     *   - 'recu'    : reçu
     *
     * `piece_liee_id` relie un reçu à la facture qui en découle — et la facture
     * au reçu dont elle est issue —, la conversion pouvant se faire dans les
     * deux sens. `parent_id` reste réservé au lien facture → avoir.
     */
    public function up(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            if (!Schema::hasColumn('ventes', 'type_piece')) {
                $table->string('type_piece', 16)->default('facture')->after('type_facture')->index();
            }
            if (!Schema::hasColumn('ventes', 'piece_liee_id')) {
                $table->unsignedBigInteger('piece_liee_id')->nullable()->after('type_piece')->index();
            }
        });

        // Reprise de l'existant : les ventes que l'ancien code avait marquées
        // 'RNE' sur `type_facture` étaient bien des reçus. Toutes les autres
        // sont des factures, y compris les ventes au comptant sans client.
        DB::table('ventes')->where('type_facture', 'RNE')->update(['type_piece' => 'recu']);
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn(['type_piece', 'piece_liee_id']);
        });
    }
};
