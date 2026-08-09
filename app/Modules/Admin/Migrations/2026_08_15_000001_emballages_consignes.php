<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les emballages consignés : la caisse de bouteilles qu'on prête, et qu'on
 * facture si elle ne revient pas.
 *
 * **Rien n'existait**, et c'est le quotidien d'un dépôt de boissons, d'un
 * distributeur de gaz, d'un grossiste en eau minérale — c'est-à-dire d'une part
 * considérable du commerce ivoirien. Trois manques :
 *
 * - **la consignation passait en vente, ou nulle part.** Une caisse consignée
 *   2 000 francs gonflait le chiffre d'affaires de 2 000 francs que
 *   l'entreprise devra rendre : ce n'est pas un produit, c'est **une dette** ;
 * - **rien ne disait ce qui est dehors.** Un dépôt ne savait pas combien de
 *   casiers dorment chez ses clients, ni depuis quand, ni chez qui ;
 * - **le non-retour ne se constatait pas.** La consignation gardée reste
 *   indéfiniment en dette au bilan alors qu'elle est devenue un produit.
 *
 * ## Les comptes, tirés du relevé OHADA du dépôt
 *
 * | Sens | Compte | Intitulé |
 * |---|---|---|
 * | Consigné **au client** | `419400` | Clients, dettes pour emballages et matériels consignés |
 * | Consigné **par un fournisseur** | `409400` | Fournisseurs, créances pour emballages et matériels à rendre |
 * | Gain à la reprise ou au non-retour | `707400` | Bonis sur reprises et cessions d'emballages |
 * | Perte sur ce qu'on ne rend pas | `622400` | Malis sur emballages |
 *
 * La consignation reçue est **une dette**, non un produit : elle vit au passif
 * jusqu'au retour de l'emballage. La consignation versée est **une créance**.
 * C'est l'inverse exact, et confondre les deux met le bilan à l'envers.
 *
 * ## Ce que ce lot ne fait pas, et pourquoi
 *
 * **Il n'établit aucune facture.** Le non-retour d'un emballage est une vente,
 * soumise à la TVA et à la certification de la plateforme : elle passe par
 * l'écran de vente ordinaire, dont la conformité est acquise et gelée.
 * Fabriquer ici une seconde route vers la FNE remettrait cette conformité en
 * jeu pour un gain nul. Le service constate le boni en comptabilité et renvoie
 * l'utilisateur vers la facture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->unsignedBigInteger('point_de_vente_id');

            // Le sens : ce qu'on consigne à un client, ou ce qu'un fournisseur
            // nous consigne. Une dette d'un côté, une créance de l'autre.
            $table->string('sens', 10);

            $table->unsignedBigInteger('client_id')->nullable();
            $table->unsignedBigInteger('fournisseur_id')->nullable();

            // L'emballage lui-même, quand il est au catalogue. Un casier de
            // 24 bouteilles y figure souvent ; une palette rarement.
            $table->unsignedBigInteger('produit_id')->nullable();
            $table->string('designation', 200);

            // La pièce qui a porté la consignation, s'il y en a une.
            $table->string('piece_type', 100)->nullable();
            $table->unsignedBigInteger('piece_id')->nullable();
            $table->string('reference_document', 100)->nullable();

            $table->decimal('quantite', 15, 3);
            $table->decimal('prix_consigne', 15, 2);
            $table->decimal('montant', 15, 2);

            $table->decimal('quantite_rendue', 15, 3)->default(0);
            $table->decimal('montant_rembourse', 15, 2)->default(0);

            // Le gain : ce que la reprise ou le non-retour laisse à
            // l'entreprise. Compte `707400`.
            $table->decimal('boni', 15, 2)->default(0);

            $table->date('date_consignation');

            // Au-delà, l'emballage est réputé perdu. C'est ce qui permet à
            // l'écran de dire ce qui traîne, et depuis combien de temps.
            $table->date('date_limite_retour')->nullable();
            $table->date('date_cloture')->nullable();

            $table->string('statut', 20)->default('en_cours');

            $table->unsignedBigInteger('utilisateur_id')->nullable();
            $table->timestamps();

            $table->foreign('entreprise_id')->references('id')->on('entreprises')->cascadeOnDelete();
            $table->foreign('point_de_vente_id')->references('id')->on('points_de_vente')->cascadeOnDelete();
            $table->foreign('client_id')->references('id')->on('clients')->nullOnDelete();
            $table->foreign('fournisseur_id')->references('id')->on('fournisseurs')->nullOnDelete();
            $table->foreign('produit_id')->references('id')->on('produits')->nullOnDelete();
            $table->foreign('utilisateur_id')->references('id')->on('utilisateurs')->nullOnDelete();

            $table->index(['entreprise_id', 'statut'], 'consignations_statut');
            $table->index(['piece_type', 'piece_id'], 'consignations_piece');
            $table->index('date_limite_retour', 'consignations_echeance');
        });

        Schema::table('produits', function (Blueprint $table) {
            // Le prix auquel l'article se consigne. Non nul, l'article est un
            // emballage consignable, et l'écran le propose à la vente.
            $table->decimal('prix_consignation', 15, 2)->nullable()->after('preavis_peremption');

            // Le délai de retour, en jours. Au-delà, l'emballage est réputé
            // perdu. Vingt et un jours est l'usage des dépôts de boissons ;
            // une bouteille de gaz se garde bien plus longtemps.
            $table->unsignedSmallInteger('delai_retour_jours')->nullable()->after('prix_consignation');
        });
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn(['prix_consignation', 'delai_retour_jours']);
        });

        Schema::dropIfExists('consignations');
    }
};
