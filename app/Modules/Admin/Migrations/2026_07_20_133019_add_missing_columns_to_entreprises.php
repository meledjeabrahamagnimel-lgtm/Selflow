<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration corrective : ajoute toutes les colonnes qui peuvent manquer
 * sur la base de données de production si l'historique des migrations est désynchronisé.
 * Utilise Schema::hasColumn() pour ne jamais casser si la colonne existe déjà.
 * SANS clause after() pour éviter les dépendances entre colonnes manquantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {

            if (!Schema::hasColumn('entreprises', 'secteur_activite')) {
                $table->json('secteur_activite')->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'modules_actifs')) {
                $table->json('modules_actifs')->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'ncc')) {
                $table->string('ncc')->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'forme_juridique')) {
                $table->string('forme_juridique')->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'gerant_nom')) {
                $table->string('gerant_nom')->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'gerant_prenom')) {
                $table->string('gerant_prenom')->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'gerant_fonction')) {
                $table->string('gerant_fonction')->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'regime_imposition')) {
                $table->string('regime_imposition')->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'centre_impots')) {
                $table->string('centre_impots')->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'ref_bancaire')) {
                $table->string('ref_bancaire')->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'logo_path')) {
                $table->string('logo_path')->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'logo_fne_path')) {
                $table->string('logo_fne_path')->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'comptaflow_sync_key')) {
                $table->string('comptaflow_sync_key', 100)->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'comptaflow_sync_status')) {
                $table->string('comptaflow_sync_status', 50)->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'comptaflow_last_sync_at')) {
                $table->timestamp('comptaflow_last_sync_at')->nullable();
            }
            if (!Schema::hasColumn('entreprises', 'comptaflow_company_id')) {
                $table->unsignedBigInteger('comptaflow_company_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Ne rien faire en rollback pour éviter la perte de données
    }
};
