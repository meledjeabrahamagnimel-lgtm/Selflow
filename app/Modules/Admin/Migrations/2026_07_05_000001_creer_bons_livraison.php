<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── Table principale des Bons de Livraison ──
        Schema::create('bons_livraison', function (Blueprint $table) {
            $table->id();
            $table->string('numero_bl')->unique();                           // BL-DD-MM-AAAA-XXXX

            // Le Bon de Commande d'origine (ventes.etape = 'Bon de commande')
            $table->foreignId('vente_id')->constrained('ventes')->onDelete('restrict');

            // La Facture générée depuis ce BL (remplie lors de la facturation)
            $table->foreignId('facture_vente_id')->nullable()->constrained('ventes')->onDelete('set null');

            $table->foreignId('point_de_vente_id')->constrained('points_de_vente')->onDelete('restrict');
            $table->foreignId('client_id')->nullable()->constrained('clients')->onDelete('set null');
            $table->unsignedBigInteger('created_by');

            $table->date('date_livraison');
            $table->enum('statut', ['en_preparation', 'partiel', 'livre', 'facture'])
                  ->default('en_preparation');
            $table->boolean('livraison_partielle')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // ── Lignes du Bon de Livraison ──
        Schema::create('bon_livraison_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bon_livraison_id')->constrained('bons_livraison')->onDelete('cascade');
            $table->foreignId('produit_id')->nullable()->constrained('produits')->onDelete('set null');

            $table->string('libelle');                   // Nom produit (snapshot)
            $table->string('unite')->nullable();
            $table->integer('qte_commandee');            // Extrait du BC
            $table->integer('qte_livree');               // Réellement remis au client

            $table->timestamps();
        });

        // ── Lier la table ventes à son BL (pour la Facture) ──
        Schema::table('ventes', function (Blueprint $table) {
            $table->foreignId('bon_livraison_id')
                  ->nullable()
                  ->after('etape')
                  ->constrained('bons_livraison')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropForeign(['bon_livraison_id']);
            $table->dropColumn('bon_livraison_id');
        });
        Schema::dropIfExists('bon_livraison_details');
        Schema::dropIfExists('bons_livraison');
    }
};
