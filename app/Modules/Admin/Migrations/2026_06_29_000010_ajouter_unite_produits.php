<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->string('unite', 50)->nullable()->default('pcs')
                ->comment('Unité de mesure du produit');
        });
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn('unite');
        });
    }
};
