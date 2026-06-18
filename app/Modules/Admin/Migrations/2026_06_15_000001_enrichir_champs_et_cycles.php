<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Clients
        Schema::table('clients', function (Blueprint $table) {
            $table->string('rccm')->nullable()->after('ncc');
            $table->string('compte_comptable')->default('411100')->after('rccm');
        });

        // 2. Fournisseurs
        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->string('rccm')->nullable()->after('ncc');
            $table->string('compte_comptable')->default('401100')->after('rccm');
        });

        // 3. Produits
        Schema::table('produits', function (Blueprint $table) {
            $table->string('type')->default('stockable')->after('nom'); // 'stockable', 'consommable', 'service'
            $table->decimal('taux_tva', 5, 2)->default(18.00)->after('prix_vente');
            $table->string('compte_vente')->default('701100')->after('taux_tva');
            $table->string('compte_achat')->default('601100')->after('compte_vente');
        });

        // 4. Ventes
        Schema::table('ventes', function (Blueprint $table) {
            $table->string('etape')->default('Facture')->after('statut'); // 'Devis', 'Bon de commande', 'Facture'
        });

        // 5. Achats
        Schema::table('achats', function (Blueprint $table) {
            $table->string('etape')->default('Facture')->after('statut'); // 'Demande de prix', 'Bon de commande', 'Facture'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['rccm', 'compte_comptable']);
        });

        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->dropColumn(['rccm', 'compte_comptable']);
        });

        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn(['type', 'taux_tva', 'compte_vente', 'compte_achat']);
        });

        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn('etape');
        });

        Schema::table('achats', function (Blueprint $table) {
            $table->dropColumn('etape');
        });
    }
};
