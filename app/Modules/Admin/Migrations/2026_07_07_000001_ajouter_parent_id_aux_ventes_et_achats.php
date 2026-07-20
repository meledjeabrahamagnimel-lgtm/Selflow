<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('ventes')->onDelete('restrict');
            $table->string('raison_avoir')->nullable()->after('type_facture');
        });

        Schema::table('achats', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('achats')->onDelete('restrict');
            $table->string('raison_avoir')->nullable()->after('type_facture');
        });
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'raison_avoir']);
        });

        Schema::table('achats', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'raison_avoir']);
        });
    }
};
