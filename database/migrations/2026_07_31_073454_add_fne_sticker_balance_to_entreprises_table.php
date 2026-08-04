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
        Schema::table('entreprises', function (Blueprint $table) {
            if (!Schema::hasColumn('entreprises', 'fne_sticker_balance')) {
                $table->unsignedInteger('fne_sticker_balance')->default(0)->after('sticker_solde_alerte');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            if (Schema::hasColumn('entreprises', 'fne_sticker_balance')) {
                $table->dropColumn('fne_sticker_balance');
            }
        });
    }
};
