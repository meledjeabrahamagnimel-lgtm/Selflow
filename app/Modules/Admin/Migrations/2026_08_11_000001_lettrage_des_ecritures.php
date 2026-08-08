<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le lettrage : rapprocher une facture du règlement qui la solde.
 *
 * Sans lui, le compte d'un client accumule indéfiniment ses factures et ses
 * encaissements sans que rien ne dise lesquels se répondent. Le solde est juste
 * — il l'a toujours été — mais **on ne sait pas ce qui reste dû** : une facture
 * de mars payée en avril reste visible à côté d'une facture d'août impayée, et
 * relancer le bon client demande de refaire le rapprochement à la main.
 *
 * La structure reprend celle de **Comptaflow**, pour que le déversement
 * n'ait rien à traduire : une table `lettrages` portant un code, et une colonne
 * `lettrage_id` sur les écritures. Les écritures qui partagent un code sont
 * lettrées ensemble.
 *
 * | Comptaflow | Selflow |
 * |---|---|
 * | `lettrages` : `code`, `date_lettrage`, `user_id`, `company_id` | idem, avec `entreprise_id` |
 * | `ecriture_comptables.lettrage_id` | `ecritures_comptables.lettrage_id` |
 *
 * Le code est une lettre — `A`, `B`, … `Z`, puis `AA` — attribuée dans l'ordre,
 * par entreprise. C'est la convention comptable, et c'est ce qu'un comptable
 * s'attend à lire sur un grand livre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lettrages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->string('code', 10)->index();
            $table->date('date_lettrage');
            $table->unsignedBigInteger('utilisateur_id')->nullable();
            $table->timestamps();

            $table->foreign('entreprise_id')->references('id')->on('entreprises')->cascadeOnDelete();
            $table->foreign('utilisateur_id')->references('id')->on('utilisateurs')->nullOnDelete();

            // Un code ne désigne qu'un seul lettrage dans une entreprise :
            // deux lettrages « A » rendraient le grand livre illisible.
            $table->unique(['entreprise_id', 'code']);
        });

        Schema::table('ecritures_comptables', function (Blueprint $table) {
            $table->unsignedBigInteger('lettrage_id')->nullable()->after('credit');

            $table->foreign('lettrage_id')->references('id')->on('lettrages')->nullOnDelete();
            $table->index('lettrage_id', 'ecr_lettrage_index');
        });
    }

    public function down(): void
    {
        Schema::table('ecritures_comptables', function (Blueprint $table) {
            $table->dropForeign(['lettrage_id']);
            $table->dropIndex('ecr_lettrage_index');
            $table->dropColumn('lettrage_id');
        });

        Schema::dropIfExists('lettrages');
    }
};
