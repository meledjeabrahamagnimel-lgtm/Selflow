<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécuter la migration.
     */
    public function up(): void
    {
        // 1. Table B2B Negotiations
        Schema::create('b2b_negotiations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_client_id')->constrained('entreprises')->onDelete('cascade');
            $table->foreignId('entreprise_fournisseur_id')->constrained('entreprises')->onDelete('cascade');
            $table->string('statut', 40)->default('RFQ'); // RFQ, Negociation_Client, Negociation_Fournisseur, Valide, Refuse, Termine
            $table->json('produits_demandes'); // Tableau d'objets [{reference, nom, quantite, prix_propose, unite}]
            $table->decimal('prix_final', 15, 2)->nullable();
            $table->string('type_facturation', 30)->default('commande'); // disponible, commande
            $table->json('historique_discussions')->nullable(); // Tableau de messages [{date, auteur, role, message}]
            $table->timestamps();
        });

        // 2. Ajouter b2b_negotiation_id aux achats
        Schema::table('achats', function (Blueprint $table) {
            $table->unsignedBigInteger('b2b_negotiation_id')->nullable()->index();
            $table->foreign('b2b_negotiation_id', 'fk_achats_b2b_negotiations')
                ->references('id')
                ->on('b2b_negotiations')
                ->onDelete('set null');
        });

        // 3. Ajouter b2b_negotiation_id aux ventes
        Schema::table('ventes', function (Blueprint $table) {
            $table->unsignedBigInteger('b2b_negotiation_id')->nullable()->index();
            $table->foreign('b2b_negotiation_id', 'fk_ventes_b2b_negotiations')
                ->references('id')
                ->on('b2b_negotiations')
                ->onDelete('set null');
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropForeign('fk_ventes_b2b_negotiations');
            $table->dropColumn('b2b_negotiation_id');
        });

        Schema::table('achats', function (Blueprint $table) {
            $table->dropForeign('fk_achats_b2b_negotiations');
            $table->dropColumn('b2b_negotiation_id');
        });

        Schema::dropIfExists('b2b_negotiations');
    }
};
