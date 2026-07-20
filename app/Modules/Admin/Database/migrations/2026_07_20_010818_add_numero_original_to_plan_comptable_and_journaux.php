<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_comptable', function (Blueprint $table) {
            $table->string('numero_original', 100)->nullable()->after('numero')
                ->comment('Numéro original dans COMPTAFLOW avant renumérotation');
        });

        Schema::table('codes_journaux', function (Blueprint $table) {
            $table->string('numero_original', 100)->nullable()->after('code')
                ->comment('Code original dans COMPTAFLOW avant renumérotation');
        });
    }

    public function down(): void
    {
        Schema::table('plan_comptable', function (Blueprint $table) {
            $table->dropColumn('numero_original');
        });

        Schema::table('codes_journaux', function (Blueprint $table) {
            $table->dropColumn('numero_original');
        });
    }
};
