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
        // 1. Table plan_comptable
        Schema::create('plan_comptable', function (Blueprint $table) {
            $table->id();
            $table->string('numero')->unique()->index();
            $table->string('libelle');
            $table->timestamps();
        });

        // 2. Colonne numero_tiers pour clients
        Schema::table('clients', function (Blueprint $table) {
            $table->string('numero_tiers')->nullable()->unique()->after('compte_comptable');
        });

        // 3. Colonne numero_tiers pour fournisseurs
        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->string('numero_tiers')->nullable()->unique()->after('compte_comptable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plan_comptable');

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('numero_tiers');
        });

        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->dropColumn('numero_tiers');
        });
    }
};
