<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('b2b_negotiations', function (Blueprint $table) {
            // 'rfq' = demande de devis, 'commande' = bon de commande direct
            $table->string('type_demande', 20)->default('rfq')->after('statut');
            // Référence du bon de commande quand type_demande = 'commande'
            $table->string('reference_commande')->nullable()->after('type_demande');
        });
    }

    public function down(): void
    {
        Schema::table('b2b_negotiations', function (Blueprint $table) {
            $table->dropColumn(['type_demande', 'reference_commande']);
        });
    }
};
