<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('achat_details', function (Blueprint $table) {
            $table->integer('quantite_receptionnee')->default(0)->after('quantite')
                ->comment('Quantité réellement réceptionnée en stock');
        });

        Schema::table('vente_details', function (Blueprint $table) {
            $table->integer('quantite_livree')->default(0)->after('quantite')
                ->comment('Quantité réellement livrée au client');
        });
    }

    public function down(): void
    {
        Schema::table('achat_details', function (Blueprint $table) {
            $table->dropColumn('quantite_receptionnee');
        });

        Schema::table('vente_details', function (Blueprint $table) {
            $table->dropColumn('quantite_livree');
        });
    }
};
