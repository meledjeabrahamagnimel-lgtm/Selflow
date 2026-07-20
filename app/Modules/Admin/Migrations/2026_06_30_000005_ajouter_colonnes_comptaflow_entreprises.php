<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécuter la migration.
     */
    public function up(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            $table->string('comptaflow_sync_key')->nullable()->after('ncc');
            $table->string('comptaflow_sync_status')->default('Désactivé')->after('comptaflow_sync_key');
            $table->timestamp('comptaflow_last_sync_at')->nullable()->after('comptaflow_sync_status');
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            $table->dropColumn(['comptaflow_sync_key', 'comptaflow_sync_status', 'comptaflow_last_sync_at']);
        });
    }
};
