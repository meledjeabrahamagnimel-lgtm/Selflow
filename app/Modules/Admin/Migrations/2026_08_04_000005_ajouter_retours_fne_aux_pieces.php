<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conserver ce que la FNE renvoie et que Selflow jetait jusqu'ici.
     *
     * Les réponses de certification contiennent, en plus de la référence et du
     * token déjà stockés :
     *   - `warning`            : alerte sur le stock de stickers ;
     *   - `invoice.amount`     : montant TTC retenu par la DGI ;
     *   - `invoice.vatAmount`  : TVA retenue par la DGI ;
     *   - `invoice.fiscalStamp`: timbre fiscal appliqué par la DGI ;
     *   - `invoice.date`       : horodatage de la certification.
     *
     * Les conserver permet de suivre le timbre réellement facturé et de
     * comparer nos montants à ceux de la plateforme — un écart signale une
     * divergence de calcul avant que la DGI ne la relève.
     */
    public function up(): void
    {
        foreach (['ventes', 'achats'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                if (!Schema::hasColumn($table, 'fne_alerte_stickers')) {
                    $blueprint->boolean('fne_alerte_stickers')->default(false);
                }
                if (!Schema::hasColumn($table, 'fne_montant_ttc')) {
                    $blueprint->decimal('fne_montant_ttc', 15, 2)->nullable();
                }
                if (!Schema::hasColumn($table, 'fne_montant_tva')) {
                    $blueprint->decimal('fne_montant_tva', 15, 2)->nullable();
                }
                if (!Schema::hasColumn($table, 'fne_timbre_fiscal')) {
                    $blueprint->decimal('fne_timbre_fiscal', 15, 2)->nullable();
                }
                if (!Schema::hasColumn($table, 'fne_certifie_at')) {
                    $blueprint->timestamp('fne_certifie_at')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        $colonnes = [
            'fne_alerte_stickers', 'fne_montant_ttc', 'fne_montant_tva',
            'fne_timbre_fiscal', 'fne_certifie_at',
        ];

        foreach (['ventes', 'achats'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($colonnes) {
                $blueprint->dropColumn($colonnes);
            });
        }
    }
};
