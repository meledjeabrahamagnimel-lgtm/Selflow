<?php

use App\Modules\Admin\Services\NumerotationTiersService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La convention de numérotation des tiers, et la réparation de ceux qui
 * portaient le compte collectif.
 *
 * `411001` ou `411KONE` : les deux se pratiquent, et le cabinet comptable de
 * chaque entreprise a la sienne. Le réglage vit donc sur l'entreprise, comme
 * les comptes par défaut.
 *
 * **La réparation.** La numérotation automatique démarrait à `411000` — le
 * compte collectif lui-même. Le premier client de chaque entreprise porte donc
 * en base un numéro de tiers qui vaut son compte de rattachement, et son
 * relevé remonte le solde de tous les autres. On le renumérote sur la première
 * place libre de la séquence.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('entreprises') && !Schema::hasColumn('entreprises', 'numerotation_tiers')) {
            Schema::table('entreprises', function (Blueprint $table) {
                // `numeric` par défaut, comme Comptaflow : c'est la valeur de
                // `companies.tier_id_type` là-bas, et les deux applications
                // doivent produire les mêmes numéros.
                $table->string('numerotation_tiers', 16)
                    ->default(NumerotationTiersService::NUMERIQUE)
                    ->after('normalisation_auto_recus');
            });
        }

        self::reparer('clients', '41');
        self::reparer('fournisseurs', '40');
    }

    /**
     * Sortir les tiers qui portent le numéro de leur compte collectif.
     *
     * La numérotation automatique démarrait à `411000` — le collectif
     * lui-même. Le premier client de chaque entreprise porte donc en base un
     * numéro de tiers qui vaut son compte de rattachement, et son relevé
     * remonte le solde de tous les autres.
     */
    private static function reparer(string $table, string $prefixe): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'numero_tiers')) {
            return;
        }

        // `411000` pour les clients, `401000` pour les fournisseurs : le
        // collectif porte un 1 en troisième position, là où le préfixe des
        // tiers n'en compte que deux.
        $collectif = $prefixe . '1000';

        $fautifs = DB::table($table)
            ->where('numero_tiers', $collectif)
            ->get(['id', 'entreprise_id']);

        foreach ($fautifs as $fiche) {
            // La première place libre de la séquence, entreprise par
            // entreprise : deux entreprises peuvent porter le même numéro de
            // tiers sans se gêner.
            $pris = DB::table($table)
                ->where('entreprise_id', $fiche->entreprise_id)
                ->where('numero_tiers', 'like', $prefixe . '%')
                ->pluck('numero_tiers')
                ->all();

            $rang = 1;

            while (in_array($prefixe . str_pad((string) $rang, 4, '0', STR_PAD_LEFT), $pris, true)) {
                $rang++;
            }

            DB::table($table)->where('id', $fiche->id)->update([
                'numero_tiers' => $prefixe . str_pad((string) $rang, 4, '0', STR_PAD_LEFT),
            ]);
        }
    }

    /**
     * Le réglage s'en va ; les numéros réparés restent.
     *
     * Les remettre à `411000` recréerait la confusion que cette migration
     * défait, et deux fiches se retrouveraient à porter le même numéro.
     */
    public function down(): void
    {
        if (Schema::hasTable('entreprises') && Schema::hasColumn('entreprises', 'numerotation_tiers')) {
            Schema::table('entreprises', function (Blueprint $table) {
                $table->dropColumn('numerotation_tiers');
            });
        }
    }
};
