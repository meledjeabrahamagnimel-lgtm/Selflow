<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('numero_original', 100)->nullable()->after('numero_tiers')
                ->comment('Code original avant re-numérotation COMPTAFLOW');
        });

        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->string('numero_original', 100)->nullable()->after('numero_tiers')
                ->comment('Code original avant re-numérotation COMPTAFLOW');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('numero_original');
        });
        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->dropColumn('numero_original');
        });
    }
};
