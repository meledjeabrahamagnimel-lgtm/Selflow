<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\DotationAmortissement;
use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\Immobilisation;
use App\Modules\Admin\Modeles\Operation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Le plan d'amortissement, et les écritures qui le suivent.
 *
 * ## La convention retenue, et pourquoi elle est écrite ici
 *
 * L'amortissement court **à partir de la mise en service**, prorata temporis.
 * Le prorata se compte en **jours sur une année commerciale de 360 jours**,
 * chaque mois valant 30 jours : c'est la convention enseignée et pratiquée en
 * zone OHADA, et celle que retiennent les cabinets ivoiriens.
 *
 * **Le propriétaire du projet a confirmé cette convention.** Elle reste isolée
 * dans `JOURS_PAR_AN` et `proportionDeLAnnee()` : un cabinet qui compterait en
 * mois entiers change une constante, et non le service. Le reste — les numéros
 * de comptes, le schéma de la cession — vient du relevé OHADA du dépôt.
 *
 * ## Le linéaire, et lui seul
 *
 * Le dégressif suppose des coefficients fixés par un texte que le dépôt ne
 * contient pas. Les inventer donnerait un plan faux que rien ne signalerait :
 * c'est exactement l'écart qui a produit un timbre de quittance à 1,5 %, taux
 * qui ne figure dans aucun texte.
 *
 * ## Les écritures, en SYSCOHADA révisé
 *
 * | Écriture | Débit | Crédit |
 * |---|---|---|
 * | **Dotation** | 681x — dotations aux amortissements | 28x — amortissements |
 * | **Cession** — solde de l'amortissement | 28x | — |
 * | **Cession** — valeur comptable nette | 810000 | — |
 * | **Cession** — sortie du bien | — | 2x |
 * | **Cession** — le prix | 485 — créances sur cessions | 820000 |
 *
 * Deux lignes par compte, jamais deux comptes sur une ligne : c'est la
 * convention du projet et celle de Comptaflow, dont le point d'entrée retient
 * `compte_debit` s'il est présent et **ignore `compte_credit`**.
 */
class AmortissementService
{
    /**
     * L'année commerciale : douze mois de trente jours.
     *
     * C'était la seule convention d'usage de ce lot, et **le propriétaire du
     * projet l'a confirmée**. Elle reste ici, seule et nommée, pour qu'un
     * cabinet qui compte autrement n'ait qu'un endroit à changer.
     */
    public const JOURS_PAR_AN = 360;

    /**
     * Le compte de créance sur cession — SYSCOHADA `485`.
     *
     * Le prix d'une cession n'est pas toujours encaissé le jour même. La
     * créance porte donc le montant jusqu'au règlement, qui la soldera.
     */
    public const COMPTE_CREANCE_CESSION = '485000';

    /** Valeurs comptables des cessions d'immobilisations — SYSCOHADA `810000`. */
    public const COMPTE_VALEUR_CEDEE = '810000';

    /** Produits des cessions d'immobilisations — SYSCOHADA `820000`. */
    public const COMPTE_PRODUIT_CESSION = '820000';

    // ─────────────────────────────────────────────────────────────────
    // LE PLAN
    // ─────────────────────────────────────────────────────────────────

    /**
     * Établir — ou refaire — le plan d'amortissement d'un bien.
     *
     * Le plan se calcule d'avance : c'est lui que le comptable présente au
     * contrôle, et il permet de savoir, avant de la passer, ce que la dotation
     * de l'année vaudra.
     *
     * **Les lignes déjà comptabilisées ne bougent pas.** Refaire un plan dont
     * une dotation est passée mettrait le plan en désaccord avec le grand
     * livre, et le désaccord ne se verrait qu'au bilan de l'année suivante.
     *
     * @return Collection<int, DotationAmortissement>
     */
    public static function etablirLePlan(Immobilisation $bien): Collection
    {
        if ($bien->estEngage()) {
            throw new \LogicException(
                "Le plan de « {$bien->libelle} » est déjà entamé en comptabilité : "
                . 'le refaire le mettrait en désaccord avec le grand livre.'
            );
        }

        return DB::transaction(function () use ($bien) {
            $bien->dotations()->delete();

            $base    = $bien->baseAmortissable();
            $debut   = $bien->date_mise_en_service->copy();
            $fin     = $debut->copy()->addMonths($bien->duree_mois)->subDay();
            $annuite = $bien->duree_mois > 0 ? $base * 12 / $bien->duree_mois : 0.0;

            $cumul = 0.0;
            $lignes = collect();

            for ($annee = (int) $debut->year; $annee <= (int) $fin->year; $annee++) {
                $debutExercice = max($debut->timestamp, Carbon::create($annee, 1, 1)->timestamp);
                $finExercice   = min($fin->timestamp, Carbon::create($annee, 12, 31)->timestamp);

                $du = Carbon::createFromTimestamp($debutExercice);
                $au = Carbon::createFromTimestamp($finExercice);

                $dotation = round($annuite * self::proportionDeLAnnee($du, $au), 2);

                // **La dernière annuité solde le plan.** Les arrondis de chaque
                // exercice laisseraient sinon quelques francs non amortis, et
                // le bien resterait indéfiniment au bilan pour ce reliquat.
                if ($annee === (int) $fin->year) {
                    $dotation = round($base - $cumul, 2);
                }

                $dotation = max(0, min($dotation, round($base - $cumul, 2)));
                $cumul    = round($cumul + $dotation, 2);

                $lignes->push(DotationAmortissement::create([
                    'immobilisation_id' => $bien->id,
                    'entreprise_id'     => $bien->entreprise_id,
                    'annee'             => $annee,
                    'date_debut'        => $du->toDateString(),
                    'date_fin'          => $au->toDateString(),
                    'base_amortissable' => $base,
                    'dotation'          => $dotation,
                    'cumul'             => $cumul,
                    'valeur_nette'      => round($bien->valeur_acquisition - $cumul, 2),
                ]));
            }

            return $lignes;
        });
    }

    /**
     * La part d'une annuité que couvre une période, en jours sur 360.
     *
     * Le mois commercial vaut trente jours, quel que soit le calendrier : le
     * 31 janvier et le 30 janvier donnent la même chose, et février se compte
     * comme les autres. C'est ce qui rend deux plans comparables.
     */
    private static function proportionDeLAnnee(Carbon $du, Carbon $au): float
    {
        return min(1.0, self::joursCommerciaux($du, $au) / self::JOURS_PAR_AN);
    }

    /**
     * Le nombre de jours commerciaux entre deux dates, bornes comprises.
     */
    private static function joursCommerciaux(Carbon $du, Carbon $au): int
    {
        $jourDebut = min(30, (int) $du->day);
        $jourFin   = min(30, (int) $au->day);

        return ((int) $au->year - (int) $du->year) * 360
            + ((int) $au->month - (int) $du->month) * 30
            + ($jourFin - $jourDebut)
            + 1;
    }

    // ─────────────────────────────────────────────────────────────────
    // LES ÉCRITURES
    // ─────────────────────────────────────────────────────────────────

    /**
     * Passer la dotation d'un exercice.
     *
     *     Débit  681x — dotations aux amortissements
     *     Crédit 28x  — amortissements du bien
     *
     * Une dotation ne se passe qu'une fois. La repasser doublerait la charge et
     * amortirait le bien au double de sa valeur — et l'erreur ne se verrait
     * qu'au bilan, l'année suivante.
     */
    public static function comptabiliser(DotationAmortissement $ligne): ?Operation
    {
        if ($ligne->estComptabilisee()) {
            throw new \LogicException(
                "La dotation {$ligne->annee} est déjà passée : la repasser doublerait la charge."
            );
        }

        if ($ligne->dotation <= 0) {
            return null;
        }

        return DB::transaction(function () use ($ligne) {
            $bien = $ligne->immobilisation;

            $operation = Operation::creer(
                $bien->entreprise_id,
                $bien->point_de_vente_id,
                $ligne->date_fin->toDateString(),
                'amortissement',
                self::journal($bien->entreprise_id),
                $bien->code,
                "Dotation aux amortissements {$ligne->annee} — {$bien->libelle}"
            );

            self::paire(
                $operation, $bien, $ligne->date_fin->toDateString(),
                "Dotation {$ligne->annee} — {$bien->libelle}",
                $bien->compte_dotation, $bien->compte_amortissement, (float) $ligne->dotation
            );

            $operation->cloturerEquilibre();

            $ligne->update([
                'comptabilise_at' => now(),
                'operation_id'    => $operation->id,
            ]);

            return $operation;
        });
    }

    /**
     * Passer, d'un coup, toutes les dotations dues d'une entreprise pour un
     * exercice.
     *
     * C'est le geste de clôture. Les biens sortis en cours d'exercice gardent
     * leur dotation jusqu'à la sortie : `ceder()` s'en charge, et la ligne du
     * plan est déjà comptabilisée quand on arrive ici.
     *
     * @return int le nombre de dotations passées
     */
    public static function comptabiliserLExercice(int $entrepriseId, int $annee): int
    {
        return DB::transaction(function () use ($entrepriseId, $annee) {
            $lignes = DotationAmortissement::with('immobilisation')
                ->where('entreprise_id', $entrepriseId)
                ->where('annee', $annee)
                ->whereNull('comptabilise_at')
                ->where('dotation', '>', 0)
                ->whereHas('immobilisation', fn ($q) => $q->where('statut', Immobilisation::EN_SERVICE))
                ->get();

            foreach ($lignes as $ligne) {
                self::comptabiliser($ligne);
            }

            return $lignes->count();
        });
    }

    /**
     * Sortir un bien du bilan : cession ou rebut.
     *
     * Le schéma SYSCOHADA, dans l'ordre :
     *
     *     Débit  28x    — pour solder l'amortissement déjà passé
     *     Débit  810000 — pour la valeur comptable nette, qui part en charge
     *     Crédit 2x     — pour la valeur d'acquisition, qui sort du bilan
     *
     *     Débit  485000 — la créance sur l'acquéreur
     *     Crédit 820000 — le produit de la cession
     *
     * **La plus-value ne s'écrit pas.** Elle apparaît d'elle-même, comme
     * différence entre le 82 et le 81 au compte de résultat. L'inscrire
     * doublerait le résultat de cession, et c'est une erreur qu'on retrouve
     * dans beaucoup de logiciels.
     *
     * Un rebut est une cession à prix nul : la valeur nette part en charge, et
     * rien n'entre en face. C'est exactement ce que le schéma produit sans
     * qu'on ait à le traiter à part.
     */
    public static function ceder(
        Immobilisation $bien,
        string $date,
        float $prixCession = 0,
        ?string $comptePrix = null
    ): Operation {
        if ($bien->estSorti()) {
            throw new \LogicException(
                "« {$bien->libelle} » est déjà sorti du bilan le "
                . $bien->date_sortie?->format('d/m/Y') . '.'
            );
        }

        return DB::transaction(function () use ($bien, $date, $prixCession, $comptePrix) {
            // La dotation de l'exercice de sortie est due jusqu'au jour de la
            // sortie : le bien a servi. L'omettre gonflerait la valeur nette,
            // donc minorerait la charge et majorerait la plus-value.
            self::dotationDeSortie($bien, $date);

            $bien->refresh();

            $cumul       = $bien->cumulAmorti();
            $valeurNette = round($bien->valeur_acquisition - $cumul, 2);

            $operation = Operation::creer(
                $bien->entreprise_id,
                $bien->point_de_vente_id,
                $date,
                'cession_immobilisation',
                self::journal($bien->entreprise_id),
                $bien->code,
                ($prixCession > 0 ? 'Cession' : 'Mise au rebut') . " — {$bien->libelle}"
            );

            $libelle = ($prixCession > 0 ? 'Cession' : 'Rebut') . " — {$bien->libelle}";

            // Sortie du bien : l'amortissement et la valeur nette au débit, la
            // valeur d'acquisition au crédit.
            if ($cumul > 0) {
                self::ligne($operation, $bien, $date, "{$libelle} / solde des amortissements",
                    $bien->compte_amortissement, null, $cumul, 0);
            }

            if ($valeurNette > 0) {
                self::ligne($operation, $bien, $date, "{$libelle} / valeur comptable nette",
                    self::COMPTE_VALEUR_CEDEE, null, $valeurNette, 0);
            }

            self::ligne($operation, $bien, $date, "{$libelle} / sortie du bilan",
                null, $bien->compte_immobilisation, 0, (float) $bien->valeur_acquisition);

            // Le prix, s'il y en a un. Un rebut n'en a pas.
            if ($prixCession > 0) {
                self::paire(
                    $operation, $bien, $date, "{$libelle} / prix de cession",
                    $comptePrix ?: self::COMPTE_CREANCE_CESSION,
                    self::COMPTE_PRODUIT_CESSION,
                    round($prixCession, 2)
                );
            }

            $operation->cloturerEquilibre();

            $bien->update([
                'statut'       => $prixCession > 0 ? Immobilisation::CEDE : Immobilisation::REBUTE,
                'date_sortie'  => $date,
                'prix_cession' => $prixCession > 0 ? round($prixCession, 2) : null,
            ]);

            // Les exercices à venir n'ont plus lieu d'être : le bien n'est plus
            // là pour les porter, et les laisser ferait croire à une charge
            // future qui ne viendra pas.
            $bien->dotations()->whereNull('comptabilise_at')->delete();

            return $operation;
        });
    }

    /**
     * La dotation due de l'ouverture de l'exercice jusqu'au jour de la sortie.
     *
     * Le bien a servi une partie de l'année : l'omettre gonflerait la valeur
     * nette, donc minorerait la charge d'amortissement et majorerait d'autant
     * la plus-value de cession — sur laquelle l'entreprise serait imposée.
     */
    private static function dotationDeSortie(Immobilisation $bien, string $date): void
    {
        $sortie = Carbon::parse($date);

        $ligne = $bien->dotations()
            ->where('annee', (int) $sortie->year)
            ->whereNull('comptabilise_at')
            ->first();

        if (!$ligne) {
            return;
        }

        $du = $ligne->date_debut->copy();

        if ($sortie->lt($du)) {
            $ligne->delete();

            return;
        }

        $annuite = $bien->duree_mois > 0 ? $bien->baseAmortissable() * 12 / $bien->duree_mois : 0.0;
        $reste   = round($bien->baseAmortissable() - $bien->cumulAmorti(), 2);
        $prorata = round($annuite * self::proportionDeLAnnee($du, $sortie), 2);

        $ligne->update([
            'date_fin'     => $sortie->toDateString(),
            'dotation'     => max(0, min($prorata, $reste)),
            'cumul'        => round($bien->cumulAmorti() + max(0, min($prorata, $reste)), 2),
            'valeur_nette' => round($bien->valeur_acquisition - $bien->cumulAmorti() - max(0, min($prorata, $reste)), 2),
        ]);

        if ($ligne->fresh()->dotation > 0) {
            self::comptabiliser($ligne->fresh());
        } else {
            $ligne->delete();
        }
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Une paire équilibrée, **deux lignes et non une**.
     *
     * C'est la convention du projet et celle de Comptaflow, dont le point
     * d'entrée retient `compte_debit` s'il est présent et ignore
     * `compte_credit` : une ligne portant les deux comptes imputerait les deux
     * montants sur le seul compte débité.
     */
    private static function paire(
        Operation $operation, Immobilisation $bien, string $date,
        string $libelle, string $compteDebit, string $compteCredit, float $montant
    ): void {
        self::ligne($operation, $bien, $date, $libelle, $compteDebit, null, $montant, 0);
        self::ligne($operation, $bien, $date, $libelle, null, $compteCredit, 0, $montant);
    }

    private static function ligne(
        Operation $operation, Immobilisation $bien, string $date, string $libelle,
        ?string $compteDebit, ?string $compteCredit, float $debit, float $credit
    ): void {
        EcritureComptable::create([
            'operation_id'       => $operation->id,
            'entreprise_id'      => $bien->entreprise_id,
            'point_de_vente_id'  => $bien->point_de_vente_id,
            'date_ecriture'      => $date,
            'libelle'            => mb_substr($libelle, 0, 190),
            'reference_document' => $bien->code,
            'code_journal'       => self::journal($bien->entreprise_id),
            'compte_debit'       => $compteDebit,
            'compte_credit'      => $compteCredit,
            'debit'              => $debit,
            'credit'             => $credit,
        ]);
    }

    /**
     * Le journal des opérations diverses : une dotation n'est ni une vente, ni
     * un achat, ni un encaissement.
     */
    private static function journal(int $entrepriseId): string
    {
        return \App\Modules\Admin\Modeles\CodeJournal::where('entreprise_id', $entrepriseId)
            ->where('type', 'OD')
            ->value('code') ?? 'OD';
    }
}
