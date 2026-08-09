<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les immobilisations et leur amortissement.
 *
 * **Rien n'existait.** Un camion, un four, un ordinateur achetés par
 * l'entreprise passaient en charge de l'exercice, ou ne passaient nulle part.
 * Trois conséquences, et la troisième coûte de l'argent :
 *
 * - **le bilan était faux** — l'actif immobilisé, la classe 2, restait vide.
 *   Une entreprise qui possède un camion de dix millions présentait un bilan
 *   qui n'en portait pas trace ;
 * - **le résultat était faux** — un investissement passé en charge écrase le
 *   résultat de l'année où il est fait, et l'allège indûment les suivantes ;
 * - **la charge d'amortissement, déductible, n'était pas prise.** Une
 *   entreprise qui n'amortit pas paie l'impôt sur un bénéfice qu'elle n'a pas.
 *
 * ## Ce que la structure porte
 *
 * Une immobilisation, et son **plan d'amortissement** — une ligne par exercice,
 * calculée d'avance. C'est le plan que le comptable présente au contrôle, et
 * c'est ce qui permet de savoir, avant de la passer, ce que la dotation de
 * l'année vaudra.
 *
 * La dotation ne s'écrit qu'une fois : la ligne du plan porte l'opération
 * comptable qui l'a passée. Une dotation passée deux fois doublerait la charge
 * et amortirait le bien au double de sa valeur.
 *
 * ## Les comptes, en SYSCOHADA révisé
 *
 * | Écriture | Débit | Crédit |
 * |---|---|---|
 * | **Dotation annuelle** | 681x — dotations aux amortissements | 28x — amortissements |
 * | **Cession** : solde de l'amortissement | 28x | — |
 * | **Cession** : valeur comptable nette | 81x — valeurs comptables des cessions | — |
 * | **Cession** : sortie du bien | — | 2x — le compte d'immobilisation |
 * | **Cession** : le prix | 485 ou trésorerie | 82x — produits des cessions |
 *
 * Les numéros viennent du relevé OHADA du dépôt, non d'une mémoire :
 * `681200`/`681300` pour les dotations, `281x` à `284x` pour les
 * amortissements, `810000`/`820000` pour les cessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('immobilisations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->unsignedBigInteger('point_de_vente_id')->nullable();

            $table->string('code', 50);
            $table->string('libelle', 200);
            $table->text('description')->nullable();

            // Les trois comptes du bien. Ils sont portés par la fiche et non
            // déduits d'une table : un four et un camion s'amortissent tous
            // deux sur 24x, mais un logiciel va sur 213x et sa dotation sur
            // 681200. Deviner rendrait le bilan faux plutôt qu'imprécis.
            $table->string('compte_immobilisation', 20);
            $table->string('compte_amortissement', 20);
            $table->string('compte_dotation', 20);

            $table->date('date_acquisition');

            // **C'est la mise en service qui déclenche l'amortissement**, non
            // l'acquisition. Un matériel acheté en novembre et installé en
            // janvier ne s'amortit pas sur novembre et décembre.
            $table->date('date_mise_en_service');

            $table->decimal('valeur_acquisition', 15, 2);

            // Ce que le bien vaudra au terme, et qui ne s'amortit pas. Nul le
            // plus souvent ; un véhicule revendu a pourtant une valeur.
            $table->decimal('valeur_residuelle', 15, 2)->default(0);

            // La durée en **mois**, non en années : un plan de trente mois
            // existe, et l'exprimer en années obligerait aux décimales.
            $table->unsignedSmallInteger('duree_mois');

            // Seul le linéaire est calculé. Le dégressif suppose des
            // coefficients fixés par un texte que le dépôt ne contient pas :
            // les inventer donnerait un plan faux que rien ne signalerait.
            $table->string('mode', 20)->default('lineaire');

            $table->string('statut', 20)->default('en_service');
            $table->date('date_sortie')->nullable();
            $table->decimal('prix_cession', 15, 2)->nullable();

            $table->unsignedBigInteger('fournisseur_id')->nullable();
            $table->unsignedBigInteger('utilisateur_id')->nullable();
            $table->timestamps();

            $table->foreign('entreprise_id')->references('id')->on('entreprises')->cascadeOnDelete();
            $table->foreign('point_de_vente_id')->references('id')->on('points_de_vente')->nullOnDelete();
            $table->foreign('fournisseur_id')->references('id')->on('fournisseurs')->nullOnDelete();
            $table->foreign('utilisateur_id')->references('id')->on('utilisateurs')->nullOnDelete();

            // Un code ne désigne qu'un bien dans une entreprise : c'est ce que
            // porte l'étiquette collée dessus, et l'inventaire physique s'y fie.
            $table->unique(['entreprise_id', 'code'], 'immo_code_unicite');
            $table->index(['entreprise_id', 'statut'], 'immo_statut');
        });

        Schema::create('dotations_amortissement', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('immobilisation_id');
            $table->unsignedBigInteger('entreprise_id');

            $table->unsignedSmallInteger('annee');
            $table->date('date_debut');
            $table->date('date_fin');

            $table->decimal('base_amortissable', 15, 2);
            $table->decimal('dotation', 15, 2);
            $table->decimal('cumul', 15, 2);
            $table->decimal('valeur_nette', 15, 2);

            // Ce qui empêche la dotation de passer deux fois. Une dotation
            // passée deux fois double la charge et amortit le bien au double de
            // sa valeur — l'erreur ne se voit qu'au bilan, l'année suivante.
            $table->timestamp('comptabilise_at')->nullable();
            $table->unsignedBigInteger('operation_id')->nullable();

            $table->timestamps();

            $table->foreign('immobilisation_id')->references('id')->on('immobilisations')->cascadeOnDelete();
            $table->foreign('entreprise_id')->references('id')->on('entreprises')->cascadeOnDelete();

            $table->unique(['immobilisation_id', 'annee'], 'dotation_unicite');
            $table->index(['entreprise_id', 'annee'], 'dotation_exercice');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dotations_amortissement');
        Schema::dropIfExists('immobilisations');
    }
};
