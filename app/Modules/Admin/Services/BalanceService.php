<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\PlanComptable;
use App\Modules\Admin\Modeles\Referentiel\Compte;
use Illuminate\Support\Facades\DB;

/**
 * La balance de contrôle.
 *
 * Selflow écrit les écritures, Comptaflow les exploite. Mais **un client sans
 * abonnement Comptaflow n'avait aucun moyen de vérifier ce que Selflow avait
 * écrit** : les écritures existaient en base, et nulle part un écran ne les
 * totalisait. Une erreur d'imputation — une vente tombée sur le compte
 * générique, un stock sans contrepartie — ne se voyait donc jamais.
 *
 * Ce n'est pas un état comptable au sens légal : c'est un **contrôle**. Il
 * répond à trois questions, et à trois seulement :
 *
 * 1. **Les débits égalent-ils les crédits ?** Si non, une écriture est
 *    incomplète, et tout ce qui en découle est faux.
 * 2. **Quels comptes ont bougé, et de combien ?** C'est ce qui permet de
 *    repérer un compte qui ne devrait pas être là.
 * 3. **Quelque chose est-il tombé sur un compte générique ?** Une ligne
 *    importante en `701000` alors que les rayons sont paramétrés signale une
 *    imputation qui n'a pas trouvé son chemin.
 *
 * Chaque écriture porte **un compte au débit et un compte au crédit** — ce
 * n'est pas une ligne par compte, mais une ligne par mouvement. La balance
 * agrège donc les deux colonnes séparément avant de les réunir par numéro.
 */
class BalanceService
{
    /**
     * La balance d'une entreprise sur une période.
     *
     * @param  string|null  $debut  date incluse, `null` pour depuis l'origine
     * @param  string|null  $fin    date incluse, `null` pour jusqu'à aujourd'hui
     * @return array{
     *     lignes: array<int, array{compte: string, libelle: string, solde_initial: float, debit: float, credit: float, solde: float}>,
     *     total_debit: float,
     *     total_credit: float,
     *     ecart: float,
     *     equilibree: bool
     * }
     */
    public static function etablir(int $entrepriseId, ?string $debut = null, ?string $fin = null, ?int $pointDeVenteId = null): array
    {
        $mouvements = self::totauxParCompte($entrepriseId, $debut, $fin, $pointDeVenteId);

        // Le solde initial : le cumul de tout ce qui precede la periode. C'est
        // ce que Comptaflow calcule sous le nom de « soldes initiaux », et sans
        // lui la balance d'un mois de mars donnerait le solde **de mars** et non
        // le solde du compte — un client qui doit 500 000 depuis janvier et n'a
        // rien bouge en mars apparaitrait a zero.
        $initiaux = $debut
            ? self::totauxParCompte($entrepriseId, null, self::veille($debut), $pointDeVenteId)
            : [];

        $comptes = array_unique(array_merge(array_keys($mouvements), array_keys($initiaux)));
        sort($comptes, SORT_STRING);

        $intitules = self::intitules($entrepriseId, $comptes);

        $lignes = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($comptes as $compte) {
            $debit = round($mouvements[$compte]['debit'] ?? 0, 2);
            $credit = round($mouvements[$compte]['credit'] ?? 0, 2);

            $soldeInitial = round(
                ($initiaux[$compte]['debit'] ?? 0) - ($initiaux[$compte]['credit'] ?? 0),
                2
            );

            $lignes[] = [
                'compte'        => (string) $compte,
                'libelle'       => $intitules[$compte] ?? Compte::nommer((string) $compte) ?? 'Compte non nommé',
                'solde_initial' => $soldeInitial,
                'debit'         => $debit,
                'credit'        => $credit,
                // Positif = solde débiteur, négatif = solde créditeur. Une
                // seule colonne signée plutôt que deux : l'écran présentera ce
                // qu'il veut, mais le calcul n'a pas à choisir pour lui.
                'solde'         => round($soldeInitial + $debit - $credit, 2),
            ];

            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        $ecart = round($totalDebit - $totalCredit, 2);

        return [
            'lignes'       => $lignes,
            'total_debit'  => round($totalDebit, 2),
            'total_credit' => round($totalCredit, 2),
            'ecart'        => $ecart,
            // Le centime de tolérance couvre les arrondis d'une TVA ventilée
            // sur plusieurs lignes ; au-delà, c'est une écriture incomplète.
            'equilibree'   => abs($ecart) < 0.01,
        ];
    }

    /**
     * Les comptes génériques réellement mouvementés.
     *
     * Une ligne en `701000` alors que les rayons portent leurs comptes signale
     * une imputation qui n'a pas trouvé son chemin — un article créé à la main,
     * sans rayon. C'est précisément ce que le lot 4.1 corrige, et ce contrôle
     * dit s'il reste des cas.
     *
     * @param  array<int, array{compte: string, debit: float, credit: float}>  $lignes
     * @return array<int, string>
     */
    public static function comptesGeneriquesUtilises(array $lignes): array
    {
        $generiques = [
            config('selflow.plan_comptable_defaut.vente_defaut'),
            config('selflow.plan_comptable_defaut.achat_defaut'),
        ];

        return array_values(array_filter(
            array_column($lignes, 'compte'),
            fn ($compte) => in_array($compte, $generiques, true)
        ));
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * La veille d'une date : la borne haute du solde initial.
     *
     * Le solde initial cumule tout ce qui précède **strictement** la période :
     * inclure le premier jour compterait ses écritures deux fois, une fois au
     * report et une fois aux mouvements.
     */
    private static function veille(string $date): string
    {
        return \Carbon\Carbon::parse($date)->subDay()->toDateString();
    }

    /**
     * Totaux débit et crédit par numéro de compte.
     *
     * Deux agrégations plutôt qu'une : une écriture porte un compte au débit
     * **et** un compte au crédit, sur la même ligne. Les réunir demanderait une
     * union, plus coûteuse et moins lisible que deux passes fusionnées en PHP —
     * la balance d'un exercice tient dans quelques centaines de comptes.
     *
     * @return array<string, array{debit: float, credit: float}>
     */
    private static function totauxParCompte(int $entrepriseId, ?string $debut, ?string $fin, ?int $pointDeVenteId): array
    {
        $totaux = [];

        foreach (['compte_debit' => 'debit', 'compte_credit' => 'credit'] as $colonneCompte => $colonneMontant) {
            $lignes = EcritureComptable::query()
                ->where('entreprise_id', $entrepriseId)
                ->whereNotNull($colonneCompte)
                ->when($debut, fn ($q) => $q->whereDate('date_ecriture', '>=', $debut))
                ->when($fin, fn ($q) => $q->whereDate('date_ecriture', '<=', $fin))
                ->when($pointDeVenteId, fn ($q) => $q->where('point_de_vente_id', $pointDeVenteId))
                ->groupBy($colonneCompte)
                ->pluck(DB::raw("SUM({$colonneMontant})"), $colonneCompte);

            foreach ($lignes as $compte => $montant) {
                $totaux[$compte] ??= ['debit' => 0.0, 'credit' => 0.0];
                $totaux[$compte][$colonneMontant] += (float) $montant;
            }
        }

        return $totaux;
    }

    /**
     * Les intitulés du plan de l'entreprise, pour les comptes mouvementés.
     *
     * Le plan de l'entreprise fait foi : c'est celui que l'utilisateur a sous
     * les yeux. Le dictionnaire OHADA ne sert que de repli, pour un compte
     * qu'une écriture aurait atteint sans qu'il figure au plan.
     *
     * @param  array<int, string>  $comptes
     * @return array<string, string>
     */
    private static function intitules(int $entrepriseId, array $comptes): array
    {
        if ($comptes === []) {
            return [];
        }

        return PlanComptable::where('entreprise_id', $entrepriseId)
            ->whereIn('numero', $comptes)
            ->pluck('libelle', 'numero')
            ->all();
    }
}
