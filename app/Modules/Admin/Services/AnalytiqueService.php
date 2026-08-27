<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\PointDeVente;
use Illuminate\Support\Facades\DB;

/**
 * Le résultat de l'entreprise, site par site.
 *
 * Chaque écriture porte déjà son `point_de_vente_id` — la vente, l'achat, le
 * règlement, le mouvement de stock, la dotation d'amortissement, la
 * consignation, et jusqu'à l'écriture manuelle. Cette information partait vers
 * Comptaflow, qui l'ignore, et **rien côté Selflow ne s'en servait** : la
 * balance et le grand livre savent filtrer sur un site, mais aucun écran ne
 * mettait les sites côte à côte. Or c'est la seule question qui compte quand
 * on en tient plusieurs : lequel gagne de l'argent, lequel en perd.
 *
 * Ce n'est pas une comptabilité analytique complète — pas de sections, pas de
 * clés de répartition, pas de charges indirectes réparties au prorata. C'est la
 * ventilation par le seul axe que l'application renseigne réellement : le lieu
 * où la pièce a été établie. Prétendre davantage supposerait des clés que
 * personne n'a données.
 */
class AnalytiqueService
{
    /**
     * Produits, charges et résultat par site, pour une période.
     *
     * @return array{
     *   sites: array<int, array{id: int|null, nom: string, produits: float, charges: float, resultat: float, ecritures: int}>,
     *   totaux: array{produits: float, charges: float, resultat: float, ecritures: int},
     *   non_ventile: array{produits: float, charges: float, resultat: float, ecritures: int}|null
     * }
     */
    public static function parSite(int $entrepriseId, ?string $debut = null, ?string $fin = null): array
    {
        $agreges = self::agreger($entrepriseId, $debut, $fin);

        $sites = PointDeVente::where('entreprise_id', $entrepriseId)
            ->orderBy('nom')
            ->get(['id', 'nom']);

        $lignes = [];
        $totaux = ['produits' => 0.0, 'charges' => 0.0, 'resultat' => 0.0, 'ecritures' => 0];

        foreach ($sites as $site) {
            $ligne = self::ligne($site->id, $site->nom, $agreges[$site->id] ?? null);
            $lignes[] = $ligne;

            $totaux['produits']  += $ligne['produits'];
            $totaux['charges']   += $ligne['charges'];
            $totaux['ecritures'] += $ligne['ecritures'];
        }

        // Les écritures sans site. Elles ne devraient pas exister — tous les
        // générateurs en posent un — mais une reprise d'historique ou un import
        // peut en laisser. Les taire ferait que la somme des sites ne vaudrait
        // pas le résultat de l'entreprise, sans que rien ne l'explique.
        $orphelines = $agreges[0] ?? null;
        $nonVentile = $orphelines
            ? self::ligne(null, 'Sans site', $orphelines)
            : null;

        if ($nonVentile) {
            $totaux['produits']  += $nonVentile['produits'];
            $totaux['charges']   += $nonVentile['charges'];
            $totaux['ecritures'] += $nonVentile['ecritures'];
        }

        $totaux['resultat'] = round($totaux['produits'] - $totaux['charges'], 2);

        return [
            'sites'       => $lignes,
            'totaux'      => $totaux,
            'non_ventile' => $nonVentile,
        ];
    }

    /**
     * @param  array{produits_credit: float, produits_debit: float, charges_debit: float, charges_credit: float, ecritures: int}|null  $brut
     * @return array{id: int|null, nom: string, produits: float, charges: float, resultat: float, ecritures: int}
     */
    private static function ligne(?int $id, string $nom, ?array $brut): array
    {
        // Un produit vit au crédit, une charge au débit — mais l'avoir et la
        // contre-passation écrivent dans l'autre sens. Retenir la seule colonne
        // naturelle ferait apparaître un avoir comme une charge et gonflerait
        // les deux totaux d'un même montant.
        $produits = round(($brut['produits_credit'] ?? 0) - ($brut['produits_debit'] ?? 0), 2);
        $charges  = round(($brut['charges_debit'] ?? 0) - ($brut['charges_credit'] ?? 0), 2);

        return [
            'id'        => $id,
            'nom'       => $nom,
            'produits'  => $produits,
            'charges'   => $charges,
            'resultat'  => round($produits - $charges, 2),
            'ecritures' => (int) ($brut['ecritures'] ?? 0),
        ];
    }

    /**
     * Une passe par colonne de compte, comme la balance : une écriture porte un
     * compte au débit **et** un compte au crédit sur la même ligne, et les
     * réunir demanderait une union plus coûteuse que deux passes fusionnées en
     * PHP.
     *
     * Le tri classe 6 / classe 7 se fait par un `CASE` plutôt que par un
     * `SUBSTRING` en clé de groupe : la fonction ne porte pas le même nom
     * partout, et grouper sur une expression n'est pas également supporté.
     *
     * La clé `0` porte les écritures sans site — `point_de_vente_id` étant
     * `null`, aucun identifiant réel ne peut valoir zéro.
     *
     * @return array<int, array{produits_credit: float, produits_debit: float, charges_debit: float, charges_credit: float, ecritures: int}>
     */
    private static function agreger(int $entrepriseId, ?string $debut, ?string $fin): array
    {
        $agreges = [];

        $periode = fn ($q) => $q
            ->where('entreprise_id', $entrepriseId)
            ->when($debut, fn ($r) => $r->whereDate('date_ecriture', '>=', $debut))
            ->when($fin, fn ($r) => $r->whereDate('date_ecriture', '<=', $fin));

        foreach (['compte_debit' => 'debit', 'compte_credit' => 'credit'] as $colonneCompte => $sens) {
            $lignes = EcritureComptable::query()
                ->tap($periode)
                ->whereNotNull($colonneCompte)
                // Classes 6 et 7 seulement : le résultat ne se lit pas sur le
                // bilan. Une ligne de trésorerie ou de tiers n'y a pas sa place.
                ->where(function ($q) use ($colonneCompte) {
                    $q->where($colonneCompte, 'like', '6%')
                      ->orWhere($colonneCompte, 'like', '7%');
                })
                ->groupBy('point_de_vente_id')
                ->get([
                    'point_de_vente_id',
                    DB::raw("SUM(CASE WHEN {$colonneCompte} LIKE '7%' THEN {$sens} ELSE 0 END) as produits"),
                    DB::raw("SUM(CASE WHEN {$colonneCompte} LIKE '6%' THEN {$sens} ELSE 0 END) as charges"),
                ]);

            foreach ($lignes as $l) {
                $site = self::clef($l->point_de_vente_id);
                $agreges[$site] ??= self::vide();

                $agreges[$site]['produits_' . $sens] += (float) $l->produits;
                $agreges[$site]['charges_' . $sens]  += (float) $l->charges;
            }
        }

        // Le nombre d'écritures se compte à part : une ligne qui porte une
        // charge au débit et un produit au crédit serait comptée deux fois par
        // les passes ci-dessus, et le total annoncé dépasserait le journal.
        $comptages = EcritureComptable::query()
            ->tap($periode)
            ->where(function ($q) {
                $q->where('compte_debit', 'like', '6%')->orWhere('compte_debit', 'like', '7%')
                  ->orWhere('compte_credit', 'like', '6%')->orWhere('compte_credit', 'like', '7%');
            })
            ->groupBy('point_de_vente_id')
            ->get(['point_de_vente_id', DB::raw('COUNT(*) as nombre')]);

        foreach ($comptages as $l) {
            $site = self::clef($l->point_de_vente_id);
            $agreges[$site] ??= self::vide();
            $agreges[$site]['ecritures'] = (int) $l->nombre;
        }

        return $agreges;
    }

    private static function clef($pointDeVenteId): int
    {
        return (int) ($pointDeVenteId ?? 0);
    }

    /**
     * @return array{produits_credit: float, produits_debit: float, charges_debit: float, charges_credit: float, ecritures: int}
     */
    private static function vide(): array
    {
        return [
            'produits_credit' => 0.0, 'produits_debit' => 0.0,
            'charges_debit'   => 0.0, 'charges_credit' => 0.0,
            'ecritures'       => 0,
        ];
    }
}
