<?php

/**
 * COMPTAFLOW — migration à créer.
 *
 * Emplacement suggéré : database/migrations/xxxx_xx_xx_cles_de_liaison_selflow.php
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Ce qu'elle pose
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Une clé par dossier, générée par Comptaflow, et de quoi la révoquer sans
 * l'effacer — une clé effacée ne se distingue pas d'une clé jamais délivrée,
 * et l'on ne saurait plus dire, en lisant un journal, si un appel refusé
 * portait une clé révoquée ou une clé inventée.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Trois points à ne pas manquer
 * ─────────────────────────────────────────────────────────────────────────
 *
 * 1. **La clé est indexée et unique.** Chaque déversement la cherche : sans
 *    index, c'est un balayage de table par écriture reçue.
 *
 * 2. **Elle est stockée en clair côté Comptaflow, et c'est délibéré** — il
 *    faut pouvoir la retrouver *depuis sa valeur*, ce qu'un chiffrement
 *    réversible par ligne interdit. Si vous préférez ne pas la stocker en
 *    clair, stockez `hash('sha256', $cle)` dans une colonne `sync_key_hash`
 *    et cherchez par ce hachage : la clé n'est alors lisible nulle part.
 *    C'est la meilleure option ; elle vous coûte une ligne de plus.
 *    (Selflow, lui, la chiffre : il doit la *renvoyer*, pas la reconnaître.)
 *
 * 3. **`selflow_company_id` doit être unique** : deux dossiers Comptaflow
 *    rattachés à la même entreprise Selflow, et le déversement choisirait au
 *    hasard lequel reçoit les écritures.
 *
 * // À VÉRIFIER : le nom de la table. La passerelle laisse entendre
 * // `companies` ; si le dossier comptable vit ailleurs, adaptez.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // La clé délivrée à Selflow pour ce dossier.
            $table->string('selflow_sync_key', 80)->nullable()->unique();

            // Révoquée, mais conservée : un appel refusé doit pouvoir dire
            // « clé révoquée le 12/03 » plutôt que « clé inconnue ».
            $table->timestamp('selflow_sync_key_revoked_at')->nullable();

            // Le numéro de l'entreprise chez Selflow. Unique : deux dossiers
            // pour une même entreprise rendraient le déversement indéterminé.
            $table->unsignedBigInteger('selflow_company_id')->nullable()->unique();

            // Quand la liaison a été ouverte, et le dernier déversement reçu.
            $table->timestamp('selflow_linked_at')->nullable();
            $table->timestamp('selflow_last_deposit_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn([
                'selflow_sync_key',
                'selflow_sync_key_revoked_at',
                'selflow_company_id',
                'selflow_linked_at',
                'selflow_last_deposit_at',
            ]);
        });
    }
};
