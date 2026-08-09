<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ce qui rend un devis opposable.
 *
 * Le devis existe depuis l'origine, comme étape de la vente : Devis → Bon de
 * commande → Facture. Il ne touche ni le stock ni la comptabilité, et n'est
 * jamais transmis à la plateforme — tout cela était juste. **Mais il n'engage
 * personne**, et trois manques l'expliquent :
 *
 * - **aucune date de validité.** Un devis établi en janvier reste présentable
 *   en décembre, aux prix de janvier. Le client qui l'accepte a raison de le
 *   faire : rien n'y dit le contraire. Un devis est une offre, et une offre
 *   sans terme engage indéfiniment celui qui l'a faite ;
 * - **aucune trace de l'acceptation.** Ni la date, ni le nom de qui a accepté.
 *   En cas de contestation, il n'existe donc rien à opposer ;
 * - **aucune trace de la conversion.** `archived` disait qu'un devis avait été
 *   transformé, mais rien ne disait en quoi, et rien n'empêchait de le
 *   transformer une seconde fois : **le même devis produisait deux bons de
 *   commande**, donc deux livraisons et deux factures.
 *
 * Ces trois colonnes servent les devis et les bons de commande. Elles restent
 * nulles sur une facture, qui n'a pas de terme et engage dès son émission.
 *
 * **Rien ici ne touche à la FNE.** Un devis n'est pas une pièce fiscale : il
 * n'est ni normalisé, ni transmis, ni certifié, et aucune des colonnes `fne_*`
 * n'est lue ou écrite par ce lot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            // Le terme de l'offre. Nulle sur une facture.
            $table->date('date_validite')->nullable()->after('date_vente');

            // L'acceptation du client : quand, et par qui de son côté. C'est ce
            // qui se produit en cas de contestation.
            $table->date('date_acceptation')->nullable()->after('date_validite');
            $table->string('accepte_par', 190)->nullable()->after('date_acceptation');

            // La pièce née de celle-ci. `archived` disait qu'une conversion
            // avait eu lieu sans dire en quoi, et n'empêchait pas la seconde.
            $table->unsignedBigInteger('converti_en_id')->nullable()->after('piece_liee_id');

            $table->foreign('converti_en_id')->references('id')->on('ventes')->nullOnDelete();
            $table->index('date_validite', 'ventes_validite_index');
        });
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropForeign(['converti_en_id']);
            $table->dropIndex('ventes_validite_index');
            $table->dropColumn(['date_validite', 'date_acceptation', 'accepte_par', 'converti_en_id']);
        });
    }
};
