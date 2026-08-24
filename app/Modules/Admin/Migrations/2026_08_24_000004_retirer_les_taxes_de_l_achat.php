<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `achat_taxes` et `achat_detail_taxes` sont supprimées — décision du
 * propriétaire du projet, 24/08/2026, dans le prolongement du retrait de
 * `achats.montant_autres_taxes`.
 *
 * Les deux tables avaient été posées par symétrie avec la vente, où elles
 * servent à la fois aux écritures et au champ `customTaxes` du payload. À
 * l'achat, la symétrie ne tient pas :
 *
 *   - **`achat_detail_taxes` n'a jamais rien porté.** `enregistrerTaxesDeLigne()`
 *     n'est appelée que depuis la vente ;
 *   - **`achat_taxes` était remplie, et relue par personne.** Le formulaire
 *     d'achat proposait un bloc « Taxes sur total TTC », le contrôleur
 *     l'enregistrait, et rien ne s'en servait ensuite : ni le payload du
 *     bordereau d'achat — qui ne transmet **aucune** taxe, ce qui est l'un des
 *     six écarts corrigés au moment de la conformité et qui est **gelé** —, ni
 *     `ventilationAchat()`, ni `montant_ttc`, ni le document imprimé.
 *
 * Le défaut ne s'arrêtait pas là : l'écran **ajoutait la taxe au total
 * affiché**. L'utilisateur saisissait une taxe, voyait le total monter, et la
 * pièce enregistrée l'ignorait entièrement — avec sa comptabilité.
 *
 * Le bloc de saisie est retiré du formulaire dans le même mouvement : garder
 * un champ qui n'écrit plus nulle part serait pire que le défaut d'origine.
 *
 * Les tables homonymes de la vente — `vente_taxes` et `vente_detail_taxes` —
 * **ne sont pas touchées** : elles portent le champ `customTaxes` réellement
 * transmis à la plateforme.
 */
return new class extends Migration
{
    public function up(): void
    {
        // L'ordre compte : la table de détail référence les lignes d'achat.
        Schema::dropIfExists('achat_detail_taxes');
        Schema::dropIfExists('achat_taxes');
    }

    /**
     * Les tables se reposent vides — c'est l'état d'où elles viennent pour
     * `achat_detail_taxes`, et le seul qui ait un sens pour `achat_taxes` :
     * son contenu ne servait à rien, et le rétablir ne rétablirait rien.
     */
    public function down(): void
    {
        if (!Schema::hasTable('achat_detail_taxes')) {
            Schema::create('achat_detail_taxes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('achat_detail_id');
                $table->string('nom', 100);
                $table->decimal('taux', 5, 2)->default(0);
                $table->decimal('montant', 15, 2)->default(0);
                $table->timestamps();

                $table->foreign('achat_detail_id', 'fk_achat_detail_taxes_details')
                    ->references('id')->on('achat_details')
                    ->cascadeOnDelete();
            });
        }

        if (!Schema::hasTable('achat_taxes')) {
            Schema::create('achat_taxes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('achat_id');
                $table->string('nom', 100);
                $table->decimal('taux', 5, 2)->default(0);
                $table->decimal('montant', 15, 2)->default(0);
                $table->timestamps();

                $table->foreign('achat_id', 'fk_achat_taxes_achats')
                    ->references('id')->on('achats')
                    ->cascadeOnDelete();
            });
        }
    }
};
