<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            if (!Schema::hasColumn('entreprises', 'statut')) {
                $table->string('statut', 30)->default('actif')->index()
                    ->comment('actif, bloque');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            if (Schema::hasColumn('entreprises', 'statut')) {
                $table->dropColumn('statut');
            }
        });
    }
};
