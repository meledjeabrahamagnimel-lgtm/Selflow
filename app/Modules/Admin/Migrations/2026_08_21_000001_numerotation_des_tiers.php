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
                // La séquence par défaut : elle ne produit jamais d'homonyme,
                // et une entreprise qui préfère les noms le dira dans ses
                // paramètres.
                $table->string('numerotation_tiers', 16)
                    ->default(NumerotationTiersService::SEQUENCE)
                    ->after('normalisation_auto_recus');
            });
        }

        self::reparer('clients', '411');
        self::reparer('fournisseurs', '401');
    }

    /**
     * Sortir les tiers qui portent le numéro de leur compte collectif.
     */
    private static function reparer(string $table, string $racine): void
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'numero_tiers')) {
            return;
        }

        $collectif = $racine . '000';

        $fautifs = DB::table($table)
            ->where('numero_tiers', $collectif)
            ->get(['id', 'entreprise_id']);

        foreach ($fautifs as $fiche) {
            // La première place libre de la séquence, entreprise par
            // entreprise : deux entreprises peuvent porter le même numéro de
            // tiers sans se gêner.
            $pris = DB::table($table)
                ->where('entreprise_id', $fiche->entreprise_id)
                ->where('numero_tiers', 'like', $racine . '%')
                ->pluck('numero_tiers')
                ->all();

            $rang = 1;

            while (in_array($racine . str_pad((string) $rang, 3, '0', STR_PAD_LEFT), $pris, true)) {
                $rang++;
            }

            DB::table($table)->where('id', $fiche->id)->update([
                'numero_tiers' => $racine . str_pad((string) $rang, 3, '0', STR_PAD_LEFT),
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
