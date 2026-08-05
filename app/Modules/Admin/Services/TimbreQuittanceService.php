<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\Vente;

/**
 * Droit de timbre de quittance — barème légal ivoirien.
 *
 * Article 873 du Code général des impôts, chapitre III « Timbres progressifs
 * ou timbres de quittances » :
 *
 *     Tranches d'imposition        Droits dus
 *     0 – 5 000                        0 F
 *     5 001 – 100 000                100 F
 *     100 001 – 500 000              500 F
 *     500 001 – 1 000 000          1 000 F
 *     1 000 001 – 5 000 000        2 000 F
 *     au-delà de 5 000 000         5 000 F
 *
 * « Le droit de timbre établi concerne tous les titres […] qui emportent
 * libération ou qui constatent des paiements ou des versements de sommes »,
 * et l'article 875 précise qu'il « est à la charge du débiteur » — c'est donc
 * le client qui l'acquitte, l'entreprise ne faisant que le collecter.
 *
 * Le barème est progressif par tranche, non proportionnel : le montant dû est
 * forfaitaire à l'intérieur d'une tranche. Le code appliquait auparavant un
 * taux de 1,5 %, qui ne correspond à aucun texte — il annonçait 250 F là où la
 * DGI en retenait 100.
 */
class TimbreQuittanceService
{
    /**
     * Barème de l'article 873, sous la forme [plafond de tranche, droit dû].
     *
     * Les bornes sont inclusives : 5 000 F relève encore de la première
     * tranche et n'est pas timbré, 5 001 F relève de la deuxième.
     */
    public const BAREME = [
        [5_000,     0],
        [100_000,   100],
        [500_000,   500],
        [1_000_000, 1_000],
        [5_000_000, 2_000],
    ];

    /** Droit dû au-delà de la dernière tranche du barème. */
    public const DROIT_TRANCHE_SUPERIEURE = 5_000;

    /**
     * Droit de timbre dû pour une somme encaissée.
     */
    public static function montantDu(float $sommeEncaissee): float
    {
        if ($sommeEncaissee <= 0) {
            return 0.0;
        }

        foreach (self::BAREME as [$plafond, $droit]) {
            if ($sommeEncaissee <= $plafond) {
                return (float) $droit;
            }
        }

        return (float) self::DROIT_TRANCHE_SUPERIEURE;
    }

    /**
     * Le timbre s'applique-t-il à cette vente ?
     *
     * Deux conditions cumulatives :
     *
     *  - le règlement est en espèces. Le timbre frappe la quittance, c'est-à-dire
     *    la pièce qui constate un versement de sommes ; un règlement par banque
     *    ou par mobile money laisse sa propre trace et n'en relève pas ;
     *
     *  - l'option est déclarée active dans les paramètres. Elle reflète la case
     *    cochée sur la plateforme FNE : c'est là que la DGI décide d'appliquer
     *    le timbre, et l'API ne permet pas de lire ce réglage. Sans lui, une
     *    facture non encore normalisée annoncerait un timbre que la plateforme
     *    ne retiendra pas.
     */
    public static function estApplicable(Vente $vente): bool
    {
        $entreprise = $vente->pointDeVente?->entreprise;

        if (!$entreprise || !$entreprise->timbre_quittance) {
            return false;
        }

        return self::reglementEnEspeces($vente->mode_paiement);
    }

    /**
     * Timbre retenu pour une vente.
     *
     * Le montant renvoyé par la plateforme fait toujours foi : c'est celui qui
     * figure sur la facture certifiée, donc celui que le client doit. Le
     * barème ne sert qu'avant la normalisation, pour annoncer la somme à
     * encaisser sans attendre la réponse de la DGI.
     */
    public static function pourVente(Vente $vente): float
    {
        if ($vente->fne_timbre_fiscal !== null) {
            return (float) $vente->fne_timbre_fiscal;
        }

        if (!self::estApplicable($vente)) {
            return 0.0;
        }

        return self::montantDu((float) $vente->montant_ttc);
    }

    /** Le timbre affiché vient-il de la plateforme, ou du barème ? */
    public static function provenance(Vente $vente): string
    {
        return $vente->fne_timbre_fiscal !== null ? 'dgi' : 'bareme';
    }

    private static function reglementEnEspeces(?string $modePaiement): bool
    {
        $mode = strtolower(trim((string) $modePaiement));

        return in_array($mode, ['caisse', 'espèces', 'especes', 'espèce', 'espece', 'cash'], true);
    }
}
