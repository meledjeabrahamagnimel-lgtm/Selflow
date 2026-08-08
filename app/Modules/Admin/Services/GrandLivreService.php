<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\PlanComptable;
use App\Modules\Admin\Modeles\Referentiel\Compte;

/**
 * Le grand livre : le détail, compte par compte, de ce qui s'est passé.
 *
 * La balance dit combien un compte a bougé ; le grand livre dit **pourquoi**.
 * C'est là qu'on va quand un solde surprend, et c'est ce qu'un comptable
 * réclame en premier lors d'une révision.
 *
 * La logique est reprise de **Comptaflow**, pour que les deux applications
 * disent la même chose du même exercice :
 *
 * - une **plage de comptes** — du compte A au compte B — plutôt qu'un compte
 *   isolé, parce qu'on révise une classe entière, pas une ligne ;
 * - un **solde initial** par compte : le cumul de tout ce qui précède la date
 *   de début. Sans lui, le grand livre d'un mois de mars donnerait un solde de
 *   mars, et non le solde du compte ;
 * - un **solde progressif** ligne à ligne, celui qu'on suit du doigt ;
 * - la **colonne de lettrage**, qui dit d'un coup d'œil ce qui est soldé.
 *
 * **Une différence de structure avec Comptaflow, et elle compte.** Chez lui,
 * une écriture porte **un** compte, avec son débit et son crédit ; une
 * opération compte donc plusieurs écritures. Dans Selflow, une écriture porte
 * **les deux** comptes sur la même ligne. Une écriture de Selflow produit par
 * conséquent **deux lignes de grand livre** — une sur le compte débité, une sur
 * le compte crédité. C'est la traduction qu'il faudra faire au déversement, et
 * c'est ici qu'elle se lit le plus clairement.
 */
class GrandLivreService
{
    /**
     * Le grand livre d'une plage de comptes sur une période.
     *
     * @return array<int, array{
     *     compte: string,
     *     libelle: string,
     *     solde_initial: float,
     *     lignes: array<int, array{date: string, piece: string, libelle: string, journal: string, contrepartie: ?string, debit: float, credit: float, solde: float, lettrage: ?string}>,
     *     total_debit: float,
     *     total_credit: float,
     *     solde_final: float
     * }>
     */
    public static function etablir(
        int $entrepriseId,
        ?string $compteDebut = null,
        ?string $compteFin = null,
        ?string $dateDebut = null,
        ?string $dateFin = null,
        ?int $pointDeVenteId = null
    ): array {
        // La plage se lit dans l'ordre, quel que soit l'ordre de saisie : un
        // utilisateur qui tape « de 701000 à 601000 » veut la même chose.
        if ($compteDebut && $compteFin && strcmp($compteDebut, $compteFin) > 0) {
            [$compteDebut, $compteFin] = [$compteFin, $compteDebut];
        }

        $initiaux = $dateDebut
            ? self::soldesInitiaux($entrepriseId, $compteDebut, $compteFin, $dateDebut, $pointDeVenteId)
            : [];

        $mouvements = self::mouvements($entrepriseId, $compteDebut, $compteFin, $dateDebut, $dateFin, $pointDeVenteId);

        // Un compte peut n'avoir qu'un solde initial et aucun mouvement sur la
        // période : il figure au grand livre, avec son report et rien d'autre.
        $comptes = array_unique(array_merge(array_keys($mouvements), array_keys($initiaux)));
        sort($comptes, SORT_STRING);

        $intitules = self::intitules($entrepriseId, $comptes);
        $resultat = [];

        foreach ($comptes as $compte) {
            $solde = $initiaux[$compte] ?? 0.0;
            $lignes = [];
            $totalDebit = 0.0;
            $totalCredit = 0.0;

            foreach ($mouvements[$compte] ?? [] as $ligne) {
                $solde += $ligne['debit'] - $ligne['credit'];
                $ligne['solde'] = round($solde, 2);

                $lignes[] = $ligne;
                $totalDebit += $ligne['debit'];
                $totalCredit += $ligne['credit'];
            }

            $resultat[] = [
                'compte'        => (string) $compte,
                'libelle'       => $intitules[$compte] ?? Compte::nommer((string) $compte) ?? 'Compte non nommé',
                'solde_initial' => round($initiaux[$compte] ?? 0.0, 2),
                'lignes'        => $lignes,
                'total_debit'   => round($totalDebit, 2),
                'total_credit'  => round($totalCredit, 2),
                'solde_final'   => round($solde, 2),
            ];
        }

        return $resultat;
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Le cumul de tout ce qui précède la date de début, par compte.
     *
     * Sans lui, le grand livre d'un mois de mars donnerait le solde **de mars**
     * et non le solde du compte : un client qui doit 500 000 depuis janvier et
     * n'a rien bougé en mars apparaîtrait à zéro.
     *
     * @return array<string, float>
     */
    private static function soldesInitiaux(int $entrepriseId, ?string $compteDebut, ?string $compteFin, string $dateDebut, ?int $pointDeVenteId): array
    {
        $soldes = [];

        foreach (['compte_debit' => 1, 'compte_credit' => -1] as $colonneCompte => $sens) {
            $colonneMontant = $colonneCompte === 'compte_debit' ? 'debit' : 'credit';

            $lignes = self::base($entrepriseId, $colonneCompte, $compteDebut, $compteFin, $pointDeVenteId)
                ->whereDate('date_ecriture', '<', $dateDebut)
                ->groupBy($colonneCompte)
                ->selectRaw("{$colonneCompte} as compte, SUM({$colonneMontant}) as total")
                ->pluck('total', 'compte');

            foreach ($lignes as $compte => $total) {
                $soldes[$compte] = ($soldes[$compte] ?? 0.0) + $sens * (float) $total;
            }
        }

        return $soldes;
    }

    /**
     * Les lignes de la période, groupées par compte.
     *
     * Une écriture de Selflow porte les deux comptes : elle produit donc une
     * ligne sur le compte débité et une sur le compte crédité. La contrepartie
     * — l'autre compte de la même écriture — figure sur chaque ligne : c'est ce
     * qui permet de comprendre une opération sans la rouvrir.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function mouvements(int $entrepriseId, ?string $compteDebut, ?string $compteFin, ?string $dateDebut, ?string $dateFin, ?int $pointDeVenteId): array
    {
        $parCompte = [];

        foreach (['compte_debit', 'compte_credit'] as $colonneCompte) {
            $auDebit = $colonneCompte === 'compte_debit';
            $contrepartie = $auDebit ? 'compte_credit' : 'compte_debit';

            $ecritures = self::base($entrepriseId, $colonneCompte, $compteDebut, $compteFin, $pointDeVenteId)
                ->when($dateDebut, fn ($q) => $q->whereDate('date_ecriture', '>=', $dateDebut))
                ->when($dateFin, fn ($q) => $q->whereDate('date_ecriture', '<=', $dateFin))
                ->with('lettrage')
                ->orderBy('date_ecriture')
                ->orderBy('id')
                ->get();

            foreach ($ecritures as $ecriture) {
                $parCompte[$ecriture->$colonneCompte][] = [
                    'id'           => $ecriture->id,
                    'date'         => (string) $ecriture->date_ecriture,
                    'piece'        => (string) $ecriture->reference_document,
                    'libelle'      => (string) $ecriture->libelle,
                    'journal'      => (string) $ecriture->code_journal,
                    'contrepartie' => $ecriture->$contrepartie,
                    'debit'        => $auDebit ? (float) $ecriture->debit : 0.0,
                    'credit'       => $auDebit ? 0.0 : (float) $ecriture->credit,
                    'solde'        => 0.0, // posé par `etablir()`, qui cumule
                    'lettrage'     => $ecriture->lettrage?->code,
                ];
            }
        }

        // Les deux passes ont chacune leur ordre : il faut les refondre, sinon
        // le solde progressif suivrait l'ordre des passes et non celui des
        // dates.
        foreach ($parCompte as $compte => $lignes) {
            usort($lignes, fn ($a, $b) => [$a['date'], $a['id']] <=> [$b['date'], $b['id']]);
            $parCompte[$compte] = $lignes;
        }

        return $parCompte;
    }

    /**
     * La requête commune : entreprise, plage de comptes, site.
     */
    private static function base(int $entrepriseId, string $colonneCompte, ?string $compteDebut, ?string $compteFin, ?int $pointDeVenteId)
    {
        return EcritureComptable::query()
            ->where('entreprise_id', $entrepriseId)
            ->whereNotNull($colonneCompte)
            ->when($compteDebut, fn ($q) => $q->where($colonneCompte, '>=', $compteDebut))
            ->when($compteFin, fn ($q) => $q->where($colonneCompte, '<=', $compteFin))
            ->when($pointDeVenteId, fn ($q) => $q->where('point_de_vente_id', $pointDeVenteId));
    }

    /**
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
