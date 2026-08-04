<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Champs exigés par la FNE (DGI) qui n'étaient pas encore stockés :
     *
     *  - remise_taux : la FNE attend le champ `discount` en POURCENTAGE, alors
     *    que `ventes.remise` stocke un MONTANT en francs. Les deux cohabitent :
     *    `remise_taux` alimente l'API, `remise` reste la valeur en francs
     *    utilisée par la comptabilité et les modèles d'impression.
     *  - est_rne / numero_rne : champs `isRne` et `rne` de l'API (facture
     *    rattachée à un reçu normalisé déjà émis).
     *  - pied_de_page / autres_mentions : `footer` et `commercialMessage`,
     *    surchargeables pièce par pièce (valeur par défaut = paramètres de
     *    l'entreprise).
     *  - produits.remise_taux : remise par défaut du produit, recopiée sur la
     *    ligne de vente/achat.
     *  - produits.code_tva : code TVA DGI explicite (TVA/TVAB/TVAC/TVAD). Il
     *    ne peut pas être déduit du seul taux, TVAC et TVAD valant tous deux 0 %.
     *  - produits.code_tva_manuel : si faux, le code est déduit automatiquement
     *    du taux et du régime d'imposition de l'entreprise.
     */
    public function up(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            if (!Schema::hasColumn('produits', 'remise_taux')) {
                $table->decimal('remise_taux', 5, 2)->default(0)->after('taux_tva');
            }
            if (!Schema::hasColumn('produits', 'code_tva')) {
                $table->string('code_tva', 8)->nullable()->after('remise_taux');
            }
            if (!Schema::hasColumn('produits', 'code_tva_manuel')) {
                $table->boolean('code_tva_manuel')->default(false)->after('code_tva');
            }
        });

        Schema::table('ventes', function (Blueprint $table) {
            if (!Schema::hasColumn('ventes', 'remise_taux')) {
                $table->decimal('remise_taux', 5, 2)->default(0)->after('remise');
            }
            if (!Schema::hasColumn('ventes', 'est_rne')) {
                $table->boolean('est_rne')->default(false)->after('type_facture');
            }
            if (!Schema::hasColumn('ventes', 'numero_rne')) {
                $table->string('numero_rne', 64)->nullable()->after('est_rne');
            }
            if (!Schema::hasColumn('ventes', 'pied_de_page')) {
                $table->string('pied_de_page', 248)->nullable();
            }
            if (!Schema::hasColumn('ventes', 'autres_mentions')) {
                $table->string('autres_mentions', 248)->nullable();
            }
        });

        Schema::table('vente_details', function (Blueprint $table) {
            if (!Schema::hasColumn('vente_details', 'remise_taux')) {
                $table->decimal('remise_taux', 5, 2)->default(0)->after('prix_unitaire');
            }
        });

        Schema::table('achats', function (Blueprint $table) {
            if (!Schema::hasColumn('achats', 'remise')) {
                $table->decimal('remise', 15, 2)->default(0)->after('montant_ttc');
            }
            if (!Schema::hasColumn('achats', 'remise_taux')) {
                $table->decimal('remise_taux', 5, 2)->default(0)->after('remise');
            }
            if (!Schema::hasColumn('achats', 'est_rne')) {
                $table->boolean('est_rne')->default(false);
            }
            if (!Schema::hasColumn('achats', 'numero_rne')) {
                $table->string('numero_rne', 64)->nullable();
            }
            if (!Schema::hasColumn('achats', 'pied_de_page')) {
                $table->string('pied_de_page', 248)->nullable();
            }
            if (!Schema::hasColumn('achats', 'autres_mentions')) {
                $table->string('autres_mentions', 248)->nullable();
            }
        });

        Schema::table('achat_details', function (Blueprint $table) {
            if (!Schema::hasColumn('achat_details', 'remise_taux')) {
                $table->decimal('remise_taux', 5, 2)->default(0)->after('prix_unitaire');
            }
        });
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn(['remise_taux', 'code_tva', 'code_tva_manuel']);
        });

        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn(['remise_taux', 'est_rne', 'numero_rne', 'pied_de_page', 'autres_mentions']);
        });

        Schema::table('vente_details', function (Blueprint $table) {
            $table->dropColumn('remise_taux');
        });

        Schema::table('achats', function (Blueprint $table) {
            $table->dropColumn(['remise', 'remise_taux', 'est_rne', 'numero_rne', 'pied_de_page', 'autres_mentions']);
        });

        Schema::table('achat_details', function (Blueprint $table) {
            $table->dropColumn('remise_taux');
        });
    }
};
