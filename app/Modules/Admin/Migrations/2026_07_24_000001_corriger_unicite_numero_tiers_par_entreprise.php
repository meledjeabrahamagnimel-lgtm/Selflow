<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration corrective : le numero_tiers doit être unique PAR ENTREPRISE,
 * pas globalement. Remplace la contrainte unique simple par une contrainte
 * composite (entreprise_id, numero_tiers) sur clients et fournisseurs.
 *
 * ANOMALIE DETECTEE : La contrainte unique globale empêche deux entreprises
 * distinctes d'avoir le même code tiers (ex: 411001), ce qui est incorrect.
 */
return new class extends Migration
{
    public function up(): void
    {
        // === 1. Table clients ===
        if (Schema::hasTable('clients')) {
            // Supprimer l'ancienne contrainte unique globale si elle existe
            try {
                Schema::table('clients', function (Blueprint $table) {
                    $table->dropUnique('clients_numero_tiers_unique');
                });
            } catch (\Exception $e) {
                // Contrainte déjà absente, on continue
            }

            // Ajouter la contrainte composite (unique par entreprise)
            if (!$this->indexExists('clients', 'clients_entreprise_numero_tiers_unique')) {
                Schema::table('clients', function (Blueprint $table) {
                    $table->unique(['entreprise_id', 'numero_tiers'], 'clients_entreprise_numero_tiers_unique');
                });
            }
        }

        // === 2. Table fournisseurs ===
        if (Schema::hasTable('fournisseurs')) {
            try {
                Schema::table('fournisseurs', function (Blueprint $table) {
                    $table->dropUnique('fournisseurs_numero_tiers_unique');
                });
            } catch (\Exception $e) {
                // Contrainte déjà absente, on continue
            }

            if (!$this->indexExists('fournisseurs', 'fournisseurs_entreprise_numero_tiers_unique')) {
                Schema::table('fournisseurs', function (Blueprint $table) {
                    $table->unique(['entreprise_id', 'numero_tiers'], 'fournisseurs_entreprise_numero_tiers_unique');
                });
            }
        }
    }

    public function down(): void
    {
        // Restaurer les anciennes contraintes globales
        Schema::table('clients', function (Blueprint $table) {
            try { $table->dropUnique('clients_entreprise_numero_tiers_unique'); } catch (\Exception $e) {}
            $table->unique('numero_tiers', 'clients_numero_tiers_unique');
        });

        Schema::table('fournisseurs', function (Blueprint $table) {
            try { $table->dropUnique('fournisseurs_entreprise_numero_tiers_unique'); } catch (\Exception $e) {}
            $table->unique('numero_tiers', 'fournisseurs_numero_tiers_unique');
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return !empty($indexes);
    }
};
