<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige un bug latent découvert le 24/07/2026 en préparant le peuplement
 * massif : ordres_production.code_ordre était UNIQUE GLOBALEMENT, alors que
 * NumerotationService::genererNumeroOD() ne compte les numéros existants
 * QUE pour l'entreprise courante (comme toutes les autres numérotations de
 * l'application). Deux entreprises différentes créant chacune leur premier
 * ordre de production le même jour génèrent donc la même chaîne
 * ("OD-jjmmaa-001"), et la seconde insertion échoue sur la contrainte
 * unique globale — même classe de bug que celui déjà corrigé sur
 * clients/fournisseurs.numero_tiers (migration du 24/07/2026 précédente).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Le retrait est conditionnel, comme celui de la migration précédente :
        // une base qui ne porte pas l'index que la migration croit y trouver
        // arrête tout le déploiement derrière elle. Voir `2026_08_09_000003`,
        // qui a bloqué une mise en ligne pour cette raison exacte.
        if ($this->indexExiste('ordres_production', 'ordres_production_code_ordre_unique')) {
            Schema::table('ordres_production', function (Blueprint $table) {
                $table->dropUnique(['code_ordre']);
            });
        }

        if (!$this->indexExiste('ordres_production', 'uniq_ordre_production_code_par_entreprise')) {
            Schema::table('ordres_production', function (Blueprint $table) {
                $table->unique(['entreprise_id', 'code_ordre'], 'uniq_ordre_production_code_par_entreprise');
            });
        }
    }

    private function indexExiste(string $table, string $nom): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['name'] ?? null) === $nom) {
                return true;
            }
        }

        return false;
    }

    public function down(): void
    {
        Schema::table('ordres_production', function (Blueprint $table) {
            $table->dropUnique('uniq_ordre_production_code_par_entreprise');
            $table->unique(['code_ordre']);
        });
    }
};
