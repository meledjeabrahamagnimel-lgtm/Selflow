<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (!Schema::hasColumn('clients', 'type_facturation')) {
                $table->enum('type_facturation', ['B2B', 'B2C', 'B2G', 'B2F'])
                      ->nullable()
                      ->default(null)
                      ->after('nom');
            }
        });

        Schema::table('fournisseurs', function (Blueprint $table) {
            if (!Schema::hasColumn('fournisseurs', 'type_facturation')) {
                $table->enum('type_facturation', ['B2B', 'B2C', 'B2G', 'B2F'])
                      ->nullable()
                      ->default(null)
                      ->after('nom');
            }
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            if (Schema::hasColumn('clients', 'type_facturation')) {
                $table->dropColumn('type_facturation');
            }
        });

        Schema::table('fournisseurs', function (Blueprint $table) {
            if (Schema::hasColumn('fournisseurs', 'type_facturation')) {
                $table->dropColumn('type_facturation');
            }
        });
    }
};
