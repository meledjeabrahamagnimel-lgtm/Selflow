<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 0.8 — Index de performance (Section 18.1)
 *
 * Cette migration ne pose QUE les index manquants, après vérification que
 * les index simples de base ont déjà été créés dans les migrations initiales.
 *
 * Index ajoutés ici :
 *  - Composites (colonne paire filtrée ensemble)
 *  - Contrainte UNIQUE email utilisateur
 *  - Index catégorie/sous-catégorie sur produits (ajoutés par migration 0002 mais sans ->index())
 *  - Index composite (action, created_at) sur journal_audit
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────
        // TABLE : ventes — index COMPOSITE pdv + date (déjà créé partiellement)
        // ─────────────────────────────────────────────
        Schema::table('ventes', function (Blueprint $table) {
            if (!$this->indexExiste('ventes', 'ventes_pdv_date_index')) {
                $table->index(['point_de_vente_id', 'date_vente'], 'ventes_pdv_date_index');
            }
        });

        // ─────────────────────────────────────────────
        // TABLE : vente_details
        // ─────────────────────────────────────────────
        Schema::table('vente_details', function (Blueprint $table) {
            if (!$this->indexExiste('vente_details', 'vente_details_produit_id_index')) {
                $table->index('produit_id', 'vente_details_produit_id_index');
            }
            if (!$this->indexExiste('vente_details', 'vente_details_vente_id_index')) {
                $table->index('vente_id', 'vente_details_vente_id_index');
            }
        });

        // ─────────────────────────────────────────────
        // TABLE : achats — index COMPOSITE pdv + date
        // ─────────────────────────────────────────────
        Schema::table('achats', function (Blueprint $table) {
            if (!$this->indexExiste('achats', 'achats_pdv_date_index')) {
                $table->index(['point_de_vente_id', 'date_achat'], 'achats_pdv_date_index');
            }
        });

        // ─────────────────────────────────────────────
        // TABLE : achat_details
        // ─────────────────────────────────────────────
        Schema::table('achat_details', function (Blueprint $table) {
            if (!$this->indexExiste('achat_details', 'achat_details_produit_id_index')) {
                $table->index('produit_id', 'achat_details_produit_id_index');
            }
            if (!$this->indexExiste('achat_details', 'achat_details_achat_id_index')) {
                $table->index('achat_id', 'achat_details_achat_id_index');
            }
        });

        // ─────────────────────────────────────────────
        // TABLE : produits — index catégorie & sous-catégorie
        // ─────────────────────────────────────────────
        Schema::table('produits', function (Blueprint $table) {
            if (!$this->indexExiste('produits', 'produits_categorie_id_index')) {
                $table->index('categorie_id', 'produits_categorie_id_index');
            }
            if (!$this->indexExiste('produits', 'produits_sous_categorie_id_index')) {
                $table->index('sous_categorie_id', 'produits_sous_categorie_id_index');
            }
        });

        // ─────────────────────────────────────────────
        // TABLE : stocks — COMPOSITE produit + point de vente (lecture stock par site)
        // ─────────────────────────────────────────────
        Schema::table('stocks', function (Blueprint $table) {
            if (!$this->indexExiste('stocks', 'stocks_produit_pdv_index')) {
                $table->index(['produit_id', 'point_de_vente_id'], 'stocks_produit_pdv_index');
            }
        });

        // ─────────────────────────────────────────────
        // TABLE : mouvements_stock — COMPOSITE pdv + date_heure
        // ─────────────────────────────────────────────
        Schema::table('mouvements_stock', function (Blueprint $table) {
            if (!$this->indexExiste('mouvements_stock', 'mvt_pdv_date_index')) {
                $table->index(['point_de_vente_id', 'created_at'], 'mvt_pdv_date_index');
            }
        });

        // ─────────────────────────────────────────────
        // TABLE : tresorerie_journal — COMPOSITE pdv + date_operation
        // ─────────────────────────────────────────────
        Schema::table('tresorerie_journal', function (Blueprint $table) {
            if (!$this->indexExiste('tresorerie_journal', 'tres_pdv_date_index')) {
                $table->index(['point_de_vente_id', 'date_operation'], 'tres_pdv_date_index');
            }
        });

        // ─────────────────────────────────────────────
        // TABLE : ecritures_comptables — COMPOSITE entreprise + date_ecriture
        // ─────────────────────────────────────────────
        Schema::table('ecritures_comptables', function (Blueprint $table) {
            if (!$this->indexExiste('ecritures_comptables', 'ecr_entreprise_date_index')) {
                $table->index(['entreprise_id', 'date_ecriture'], 'ecr_entreprise_date_index');
            }
        });

        // ─────────────────────────────────────────────
        // TABLE : utilisateurs — UNIQUE email
        // ─────────────────────────────────────────────
        Schema::table('utilisateurs', function (Blueprint $table) {
            if (!$this->indexExiste('utilisateurs', 'utilisateurs_email_unique')) {
                $table->unique('email', 'utilisateurs_email_unique');
            }
        });

        // ─────────────────────────────────────────────
        // TABLE : journal_audit — COMPOSITE action + created_at
        // ─────────────────────────────────────────────
        Schema::table('journal_audit', function (Blueprint $table) {
            if (!$this->indexExiste('journal_audit', 'audit_action_date_index')) {
                $table->index(['action', 'created_at'], 'audit_action_date_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropIndexIfExists('ventes_pdv_date_index');
        });
        Schema::table('vente_details', function (Blueprint $table) {
            $table->dropIndexIfExists('vente_details_produit_id_index');
            $table->dropIndexIfExists('vente_details_vente_id_index');
        });
        Schema::table('achats', function (Blueprint $table) {
            $table->dropIndexIfExists('achats_pdv_date_index');
        });
        Schema::table('achat_details', function (Blueprint $table) {
            $table->dropIndexIfExists('achat_details_produit_id_index');
            $table->dropIndexIfExists('achat_details_achat_id_index');
        });
        Schema::table('produits', function (Blueprint $table) {
            $table->dropIndexIfExists('produits_categorie_id_index');
            $table->dropIndexIfExists('produits_sous_categorie_id_index');
        });
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropIndexIfExists('stocks_produit_pdv_index');
        });
        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->dropIndexIfExists('mvt_pdv_date_index');
        });
        Schema::table('tresorerie_journal', function (Blueprint $table) {
            $table->dropIndexIfExists('tres_pdv_date_index');
        });
        Schema::table('ecritures_comptables', function (Blueprint $table) {
            $table->dropIndexIfExists('ecr_entreprise_date_index');
        });
        Schema::table('utilisateurs', function (Blueprint $table) {
            $table->dropIndexIfExists('utilisateurs_email_unique');
        });
        Schema::table('journal_audit', function (Blueprint $table) {
            $table->dropIndexIfExists('audit_action_date_index');
        });
    }

    /**
     * Vérifie si un index existe déjà (protection contre les erreurs de rejeu).
     */
    private function indexExiste(string $table, string $indexName): bool
    {
        return count(DB::select(
            "SHOW INDEX FROM `{$table}` WHERE Key_name = ?",
            [$indexName]
        )) > 0;
    }
};
