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
        Schema::table('plan_comptable', function (Blueprint $table) {
            $table->string('source', 20)->default('local')->after('libelle')->index();
        });

        Schema::table('codes_journaux', function (Blueprint $table) {
            $table->string('source', 20)->default('local')->after('compte')->index();
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->string('source', 20)->default('local')->after('numero_tiers')->index();
        });

        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->string('source', 20)->default('local')->after('numero_tiers')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_comptable', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('codes_journaux', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
