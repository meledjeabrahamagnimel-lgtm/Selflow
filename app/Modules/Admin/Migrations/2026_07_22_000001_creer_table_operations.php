<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table "operations" — Introduit la notion réelle d'opération comptable.
 *
 * Chaque facturation, règlement, avoir ou OD génère UNE opération, qui porte
 * un numéro de saisie séquentiel PAR JOURNAL (ex: VTE-2026-000042). Toutes
 * les lignes d'écriture (ecritures_comptables) issues de cette opération
 * partagent son id via la colonne operation_id.
 *
 * Corrige le bug où le "N° Saisie" était recalculé à la volée par MIN(id)
 * groupé sur reference_document (mélangeant parfois deux opérations
 * distinctes qui partagent le même numéro de facture, ex: facturation +
 * règlement immédiat).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id')->index();
            $table->unsignedBigInteger('point_de_vente_id')->nullable()->index();

            $table->date('date_operation')->index();

            // Type d'opération : FactureVente, FactureAchat, ReglementVente,
            // ReglementAchat, AvoirVente, AvoirAchat, Production, OD
            $table->string('type_operation')->index();

            // Code du journal concerné (VTE, ACH, CAI, BQ..., OD)
            $table->string('code_journal')->index();

            // Numéro de saisie séquentiel, unique par (entreprise, journal, exercice)
            // ex: VTE-2026-000042
            $table->string('numero_saisie')->index();

            // Référence de la pièce justificative (n° facture, n° chèque...) — optionnelle
            $table->string('reference_document')->nullable()->index();

            // Libellé général de l'opération, si possible dérivé automatiquement
            // du plan comptable SYSCOHADA des lignes qui la composent
            // (ex: "Vente de marchandises", "Achats divers")
            $table->string('libelle_general')->nullable();

            // Solde d'équilibre : somme débit - somme crédit des écritures liées.
            // Doit toujours valoir 0.00 une fois l'opération finalisée.
            $table->decimal('solde_equilibre', 15, 2)->default(0);
            $table->boolean('est_equilibree')->default(false)->index();

            $table->timestamps();

            $table->unique(['entreprise_id', 'code_journal', 'numero_saisie'], 'uniq_operation_saisie_par_journal');

            $table->foreign('entreprise_id', 'fk_operations_entreprises')
                ->references('id')->on('entreprises')->onDelete('cascade');

            $table->foreign('point_de_vente_id', 'fk_operations_points_de_vente')
                ->references('id')->on('points_de_vente')->onDelete('cascade');
        });

        // Rattacher chaque écriture à son opération
        Schema::table('ecritures_comptables', function (Blueprint $table) {
            $table->unsignedBigInteger('operation_id')->nullable()->index()->after('id');
            $table->string('compte_tiers')->nullable()->index()->after('compte_credit');

            $table->foreign('operation_id', 'fk_ecritures_operations')
                ->references('id')->on('operations')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('ecritures_comptables', function (Blueprint $table) {
            $table->dropForeign('fk_ecritures_operations');
            $table->dropColumn(['operation_id', 'compte_tiers']);
        });

        Schema::dropIfExists('operations');
    }
};
