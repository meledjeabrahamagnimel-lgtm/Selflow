<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->enum('sous_type', [
                'Reception', 'Livraison', 'Transfert', 'Rebut', 'Ajustement', 'Production'
            ])->nullable()->after('type_mouvement');

            $table->unsignedBigInteger('point_de_vente_source_id')->nullable()->after('point_de_vente_id')
                ->comment('PDV source pour les transferts internes');

            $table->unsignedBigInteger('utilisateur_id')->nullable()->after('point_de_vente_source_id')
                ->comment('Utilisateur ayant déclenché le mouvement (traçabilité Qui)');

            $table->unsignedBigInteger('fournisseur_id')->nullable()->after('utilisateur_id')
                ->comment('Pour les réceptions fournisseur');

            $table->unsignedBigInteger('client_id')->nullable()->after('fournisseur_id')
                ->comment('Pour les livraisons client');

            $table->index('utilisateur_id', 'mvt_utilisateur_id_index');
            $table->index('sous_type', 'mvt_sous_type_index');
        });
    }

    public function down(): void
    {
        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->dropIndexIfExists('mvt_utilisateur_id_index');
            $table->dropIndexIfExists('mvt_sous_type_index');
            $table->dropColumn([
                'sous_type', 'point_de_vente_source_id',
                'utilisateur_id', 'fournisseur_id', 'client_id',
            ]);
        });
    }
};
