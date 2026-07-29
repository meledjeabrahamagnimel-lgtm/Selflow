<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {

            if (!Schema::hasColumn('entreprises', 'idu')) {
                $table->string('idu', 50)->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'reference_cadastrale')) {
                $table->string('reference_cadastrale', 100)->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'proprietaire_local')) {
                $table->string('proprietaire_local', 150)->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'commune')) {
                $table->string('commune', 100)->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'quartier')) {
                $table->string('quartier', 100)->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'sticker_solde_alerte')) {
                $table->unsignedInteger('sticker_solde_alerte')->default(5);
            }
            if (!Schema::hasColumn('entreprises', 'timbre_quittance')) {
                $table->boolean('timbre_quittance')->default(false);
            }
            if (!Schema::hasColumn('entreprises', 'bapa')) {
                $table->boolean('bapa')->default(false);
            }
            if (!Schema::hasColumn('entreprises', 'pied_de_page_facture')) {
                $table->text('pied_de_page_facture')->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'facture_autres_mentions')) {
                $table->text('facture_autres_mentions')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            $cols = [
                'idu', 'reference_cadastrale', 'proprietaire_local',
                'commune', 'quartier', 'sticker_solde_alerte',
                'timbre_quittance', 'bapa', 'pied_de_page_facture',
                'facture_autres_mentions',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('entreprises', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
