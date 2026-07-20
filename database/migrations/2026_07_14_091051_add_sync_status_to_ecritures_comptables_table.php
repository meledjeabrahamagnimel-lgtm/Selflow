<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecritures_comptables', function (Blueprint $table) {
            if (!Schema::hasColumn('ecritures_comptables', 'comptaflow_sync_status')) {
                $table->string('comptaflow_sync_status', 30)->default('pending')->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ecritures_comptables', function (Blueprint $table) {
            $table->dropColumn('comptaflow_sync_status');
        });
    }
};
