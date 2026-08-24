<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le libellé d'écriture cesse d'être l'intitulé du compte.
 *
 * Jusqu'ici, l'opération d'une facture de vente portait pour libellé général
 * « Vente de marchandises » — c'est-à-dire l'intitulé SYSCOHADA du compte
 * mouvementé. Or les deux ne sont pas la même chose. Le compte dit ce qu'il
 * est ; le libellé doit dire ce que **l'opération** a été. Un grand livre du
 * 701 dont chaque ligne répète « Vente de marchandises » n'apprend rien : la
 * seule colonne de texte libre du journal est dépensée à redire son en-tête.
 *
 * Chaque entreprise pose donc ses propres gabarits, un par type d'opération :
 * un pour le libellé de l'opération, un pour celui de ses lignes. Les jetons
 * ({piece}, {tiers}, {produits}, {point_de_vente}, {date}, {nature},
 * {journal}, {reference}, {role}) sont remplacés à l'écriture.
 *
 * **Les écritures déjà passées ne sont pas réécrites.** Un journal se lit tel
 * qu'il a été tenu ; le regraver après coup effacerait ce que le comptable a
 * relu. Le gabarit vaut pour ce qui s'écrit à partir de maintenant.
 *
 * Une entreprise qui ne paramètre rien garde **exactement** le comportement
 * d'avant : les gabarits par défaut de ModeleLibelle le reproduisent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('modeles_libelles')) {
            return;
        }

        Schema::create('modeles_libelles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->string('type_operation', 30);
            $table->string('gabarit_operation', 255)->nullable();
            $table->string('gabarit_ligne', 255)->nullable();
            $table->timestamps();

            // Un seul gabarit par type et par entreprise : deux lignes
            // concurrentes laisseraient le service en choisir une au hasard.
            $table->unique(['entreprise_id', 'type_operation'], 'uniq_modele_libelle');

            $table->foreign('entreprise_id')
                ->references('id')->on('entreprises')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modeles_libelles');
    }
};
