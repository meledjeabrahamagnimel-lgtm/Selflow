<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Taxes personnalisées de la FNE (champ `customTaxes`).
     *
     * Deux niveaux, comme l'exige l'API :
     *  - au niveau de l'ARTICLE (`items[].customTaxes`) : les taxes propres au
     *    produit (GRA, AIRSI...), définies sur la fiche produit ;
     *  - au niveau de la FACTURE (`customTaxes`) : les « taxes sur total TTC »
     *    (DTD...), saisies à la vente ou sur le bordereau d'achat.
     *
     * Les tables `*_detail_taxes` et `*_taxes` liées aux pièces sont des
     * SNAPSHOTS : modifier une taxe sur la fiche produit ne doit jamais
     * réécrire une facture déjà émise et normalisée.
     */
    public function up(): void
    {
        // Taxes par défaut d'un produit (modèle recopié à la vente/achat)
        if (!Schema::hasTable('produit_taxes')) {
            Schema::create('produit_taxes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('produit_id')->index();
                $table->string('nom', 100);
                $table->decimal('taux', 5, 2);
                $table->timestamps();

                $table->foreign('produit_id', 'fk_produit_taxes_produits')
                    ->references('id')->on('produits')->onDelete('cascade');
            });
        }

        // Snapshot des taxes d'une ligne de vente → items[].customTaxes
        if (!Schema::hasTable('vente_detail_taxes')) {
            Schema::create('vente_detail_taxes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vente_detail_id')->index();
                $table->string('nom', 100);
                $table->decimal('taux', 5, 2);
                $table->timestamps();

                $table->foreign('vente_detail_id', 'fk_vente_detail_taxes_details')
                    ->references('id')->on('vente_details')->onDelete('cascade');
            });
        }

        // Taxes sur le total TTC d'une vente → customTaxes (racine du payload)
        if (!Schema::hasTable('vente_taxes')) {
            Schema::create('vente_taxes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vente_id')->index();
                $table->string('nom', 100);
                $table->decimal('taux', 5, 2);
                $table->decimal('montant', 15, 2)->default(0);
                $table->timestamps();

                $table->foreign('vente_id', 'fk_vente_taxes_ventes')
                    ->references('id')->on('ventes')->onDelete('cascade');
            });
        }

        // Idem côté achats (BAPA)
        if (!Schema::hasTable('achat_detail_taxes')) {
            Schema::create('achat_detail_taxes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('achat_detail_id')->index();
                $table->string('nom', 100);
                $table->decimal('taux', 5, 2);
                $table->timestamps();

                $table->foreign('achat_detail_id', 'fk_achat_detail_taxes_details')
                    ->references('id')->on('achat_details')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('achat_taxes')) {
            Schema::create('achat_taxes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('achat_id')->index();
                $table->string('nom', 100);
                $table->decimal('taux', 5, 2);
                $table->decimal('montant', 15, 2)->default(0);
                $table->timestamps();

                $table->foreign('achat_id', 'fk_achat_taxes_achats')
                    ->references('id')->on('achats')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('achat_taxes');
        Schema::dropIfExists('achat_detail_taxes');
        Schema::dropIfExists('vente_taxes');
        Schema::dropIfExists('vente_detail_taxes');
        Schema::dropIfExists('produit_taxes');
    }
};
