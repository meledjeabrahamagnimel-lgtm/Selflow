<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            // Forme juridique (SARL, SA, SAS...)
            if (!Schema::hasColumn('entreprises', 'forme_juridique')) {
                $table->string('forme_juridique', 50)->nullable()->after('nom');
            }
            // Clé de synchronisation API avec COMPTAFLOW
            if (!Schema::hasColumn('entreprises', 'comptaflow_sync_key')) {
                $table->string('comptaflow_sync_key', 100)->nullable()->after('modules_actifs');
            }
            if (!Schema::hasColumn('entreprises', 'comptaflow_sync_status')) {
                $table->string('comptaflow_sync_status', 30)->nullable()->after('comptaflow_sync_key');
            }
            if (!Schema::hasColumn('entreprises', 'comptaflow_last_sync_at')) {
                $table->timestamp('comptaflow_last_sync_at')->nullable()->after('comptaflow_sync_status');
            }
            // ID de la compagnie correspondante dans COMPTAFLOW
            if (!Schema::hasColumn('entreprises', 'comptaflow_company_id')) {
                $table->unsignedBigInteger('comptaflow_company_id')->nullable()->after('comptaflow_last_sync_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            $table->dropColumn([
                'forme_juridique',
                'comptaflow_sync_key',
                'comptaflow_sync_status',
                'comptaflow_last_sync_at',
                'comptaflow_company_id',
            ]);
        });
    }
};
