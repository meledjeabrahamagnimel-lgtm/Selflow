<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\Lot;
use App\Modules\Admin\Modeles\MouvementLot;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\Produit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Le suivi par lot, en marge du stock et sans le remplacer.
 *
 * `StockService` reste la porte unique : c'est lui qui verrouille la fiche,
 * modifie la quantité, valorise au CUMP (Coût Unitaire Moyen Pondéré) et écrit
 * l'inventaire permanent. Ce service-ci ne fait qu'une chose de plus : dire
 * **de quel arrivage** vient la marchandise qui bouge.
 *
 * La séparation n'est pas de commodité. Faire des lots une seconde valorisation
 * donnerait deux vérités sur la valeur du stock, qui divergeraient au premier
 * arrondi, et la balance ne saurait plus laquelle croire. Ici le stock vaut ce
 * que dit le CUMP, et les lots disent la traçabilité.
 *
 * ## FEFO, et non FIFO
 *
 * *First Expired, First Out* : la sortie prend d'abord ce qui périme le plus
 * tôt. Les deux règles coïncident souvent, jamais toujours — un arrivage récent
 * à date courte doit partir avant un arrivage ancien à date longue, et le FIFO
 * laisserait périmer le premier.
 *
 * ## Ce que le service refuse
 *
 * **Sortir un lot périmé pour le vendre.** La marchandise périmée quitte le
 * stock par le rebut, qui la trace comme une perte ; la faire sortir en
 * livraison la ferait passer pour vendue. C'est la seule situation où le
 * service arrête l'appelant, et elle vaut la peine : vendre un produit périmé
 * engage la responsabilité du commerçant, et l'écran qui l'aurait permis n'a
 * aucun moyen de le rattraper après coup.
 */
class LotService
{
    /**
     * Les motifs de sortie qui peuvent emporter un lot périmé.
     *
     * Le rebut, évidemment — c'est par là que la marchandise périmée s'en va.
     * Le retour fournisseur, parce qu'un lot périmé se renvoie. La
     * contre-passation, parce qu'elle défait un mouvement déjà écrit et ne doit
     * jamais échouer. Et l'inventaire, parce qu'un comptage constate ce qui
     * est, sans jugement.
     */
    public const MOTIFS_TOLERANT_LE_PERIME = [
        MouvementStock::REBUT,
        MouvementStock::RETOUR_FOURNISSEUR,
        MouvementStock::CONTREPASSATION,
        MouvementStock::INVENTAIRE,
    ];

    /**
     * Faire entrer de la marchandise dans un lot, en le créant au besoin.
     *
     * Deux réceptions du même numéro de lot alimentent la **même** fiche : un
     * numéro de lot désigne un arrivage du fabricant, pas une livraison. Le
     * coût, lui, se pondère comme le CUMP (Coût Unitaire Moyen Pondéré) le
     * ferait — c'est la même marchandise.
     *
     * @param  array{numero?: string, date_peremption?: string|null, date_fabrication?: string|null, fournisseur_id?: int|null}  $description
     */
    public static function entrer(
        Produit $produit,
        int $pointDeVenteId,
        float $quantite,
        array $description,
        float $coutUnitaire = 0
    ): ?Lot {
        $numero = trim((string) ($description['numero'] ?? ''));

        if ($numero === '' || $quantite <= 0) {
            return null;
        }

        return DB::transaction(function () use ($produit, $pointDeVenteId, $quantite, $description, $coutUnitaire, $numero) {
            $lot = Lot::where('produit_id', $produit->id)
                ->where('point_de_vente_id', $pointDeVenteId)
                ->where('numero_lot', $numero)
                ->lockForUpdate()
                ->first();

            $quantite = round($quantite, Lot::DECIMALES);

            if (!$lot) {
                return Lot::create([
                    'entreprise_id'     => $produit->entreprise_id,
                    'produit_id'        => $produit->id,
                    'point_de_vente_id' => $pointDeVenteId,
                    'numero_lot'        => $numero,
                    'date_peremption'   => $description['date_peremption'] ?? null,
                    'date_fabrication'  => $description['date_fabrication'] ?? null,
                    'quantite'          => $quantite,
                    'cout_unitaire'     => round($coutUnitaire, 4),
                    'fournisseur_id'    => $description['fournisseur_id'] ?? null,
                ]);
            }

            $ancienne = (float) $lot->quantite;
            $apres    = round($ancienne + $quantite, Lot::DECIMALES);

            // Le coût du lot se pondère comme le CUMP (Coût Unitaire Moyen
            // Pondéré) : c'est la même marchandise, arrivée en deux fois.
            $cout = $apres > 0 && $ancienne > 0 && $coutUnitaire > 0
                ? round((($ancienne * (float) $lot->cout_unitaire) + ($quantite * $coutUnitaire)) / $apres, 4)
                : ($coutUnitaire > 0 ? round($coutUnitaire, 4) : (float) $lot->cout_unitaire);

            $lot->update([
                'quantite'      => $apres,
                'cout_unitaire' => $cout,
                // La date de péremption d'un lot ne change pas ; on la
                // renseigne seulement si elle manquait.
                'date_peremption' => $lot->date_peremption ?? ($description['date_peremption'] ?? null),
            ]);

            return $lot->fresh();
        });
    }

    /**
     * Prélever une quantité sur les lots, du plus proche de sa date au plus
     * lointain.
     *
     * Ce qui ne trouve pas de lot n'est pas une erreur : le stock a pu être
     * posé avant l'activation du suivi, ou par un inventaire. La ventilation
     * est alors partielle, et c'est mieux que de bloquer une vente pour une
     * raison que l'utilisateur ne peut pas corriger sur-le-champ.
     *
     * @return array<int, array{lot: Lot, quantite: float}>
     * @throws \RuntimeException si la sortie devrait emporter un lot périmé
     */
    public static function prelever(
        Produit $produit,
        int $pointDeVenteId,
        float $quantite,
        string $motif
    ): array {
        $restant = round($quantite, Lot::DECIMALES);
        $pris    = [];

        $lots = Lot::where('produit_id', $produit->id)
            ->where('point_de_vente_id', $pointDeVenteId)
            ->nonVides()
            ->fefo()
            ->lockForUpdate()
            ->get();

        $tolerePerime = in_array($motif, self::MOTIFS_TOLERANT_LE_PERIME, true);

        foreach ($lots as $lot) {
            if ($restant <= 0) {
                break;
            }

            if ($lot->estPerime() && !$tolerePerime) {
                // **Vendre un produit périmé engage la responsabilité du
                // commerçant**, et aucun écran ne rattrape cela après coup. Le
                // lot le plus proche de sa date est aussi le premier que le
                // FEFO propose : le refus tombe donc au bon moment, avant
                // qu'une ligne de facture ne soit écrite.
                throw new \RuntimeException(
                    "Le lot « {$lot->numero_lot} » de « {$produit->nom} » est périmé depuis le "
                    . $lot->date_peremption->format('d/m/Y')
                    . '. Mettez-le au rebut avant de servir ce qui suit.'
                );
            }

            $prise = min($restant, (float) $lot->quantite);

            $lot->update(['quantite' => round((float) $lot->quantite - $prise, Lot::DECIMALES)]);

            $pris[] = ['lot' => $lot, 'quantite' => round($prise, Lot::DECIMALES)];
            $restant = round($restant - $prise, Lot::DECIMALES);
        }

        return $pris;
    }

    /**
     * Défaire, lot par lot, ce qu'un mouvement avait fait.
     *
     * Une contre-passation défait un mouvement : la marchandise doit revenir
     * dans les arrivages d'où elle est sortie, et non dans un lot neuf. Sans
     * cela, une vente annulée ferait disparaître la traçabilité du lot vendu et
     * un rappel du fabricant ne retrouverait plus le client.
     *
     * **Le sens de l'origine commande.** Contre-passer une sortie rend au lot
     * ce qu'elle lui avait pris ; contre-passer une entrée le lui reprend.
     * Rendre dans les deux cas fabriquerait de la marchandise dans le second —
     * une réception annulée laisserait son lot plein.
     *
     * @return array<int, array{lot: Lot, quantite: float}> la ventilation, pour
     *         que le mouvement inverse porte la même
     */
    public static function defaire(MouvementStock $origine): array
    {
        $etaitUneSortie = $origine->type_mouvement === MouvementStock::SORTIE;
        $ventilation = [];

        foreach ($origine->lots()->with('lot')->get() as $ligne) {
            if (!$ligne->lot) {
                continue;
            }

            $quantite = (float) $ligne->quantite;

            $ligne->lot->update([
                'quantite' => round(
                    $etaitUneSortie
                        ? (float) $ligne->lot->quantite + $quantite
                        : (float) $ligne->lot->quantite - $quantite,
                    Lot::DECIMALES
                ),
            ]);

            $ventilation[] = ['lot' => $ligne->lot, 'quantite' => $quantite];
        }

        return $ventilation;
    }

    /**
     * Écrire la ventilation d'un mouvement entre les lots qu'il a touchés.
     *
     * @param  array<int, array{lot: Lot, quantite: float}>  $ventilation
     */
    public static function attacher(MouvementStock $mouvement, array $ventilation): void
    {
        foreach ($ventilation as $ligne) {
            MouvementLot::create([
                'mouvement_stock_id' => $mouvement->id,
                'lot_id'             => $ligne['lot']->id,
                'quantite'           => $ligne['quantite'],
            ]);
        }
    }

    // ─────────────────────────────────────────────────────────────────
    // CE QUE LES ÉCRANS DEMANDENT
    // ─────────────────────────────────────────────────────────────────

    /**
     * Les lots périmés qui portent encore de la marchandise.
     *
     * Un lot périmé et vide n'intéresse plus personne : il encombrerait l'écran
     * du rebut sans qu'il y ait rien à en faire.
     */
    public static function perimes(int $entrepriseId, ?int $pointDeVenteId = null): Collection
    {
        return self::base($entrepriseId, $pointDeVenteId)
            ->perimes()
            ->orderBy('date_peremption')
            ->get();
    }

    /**
     * Les lots qui approchent de leur date, chacun selon le préavis de son
     * article.
     *
     * Trente jours conviennent à l'alimentaire ; un médicament se retire des
     * rayons bien plus tôt, un cosmétique bien plus tard. Un préavis unique
     * pour tout le catalogue fait crier l'alerte au mauvais moment.
     */
    public static function bientotPerimes(int $entrepriseId, ?int $pointDeVenteId = null): Collection
    {
        return self::base($entrepriseId, $pointDeVenteId)
            ->whereNotNull('date_peremption')
            ->whereDate('date_peremption', '>=', now())
            ->orderBy('date_peremption')
            ->get()
            ->filter(fn (Lot $lot) => $lot->bientotPerime())
            ->values();
    }

    /**
     * Ce qu'un article a de disponible en lots sur un site.
     *
     * Peut différer du stock : la marchandise posée avant l'activation du suivi
     * n'appartient à aucun lot. L'écart se lit, et c'est ce qui indique à
     * l'utilisateur ce qui reste à régulariser.
     */
    public static function disponible(Produit $produit, int $pointDeVenteId): float
    {
        return round((float) Lot::where('produit_id', $produit->id)
            ->where('point_de_vente_id', $pointDeVenteId)
            ->sum('quantite'), Lot::DECIMALES);
    }

    /**
     * Où est passé un lot : les mouvements qui l'ont emporté, et vers qui.
     *
     * C'est la question que pose un rappel du fabricant, et à laquelle rien ne
     * savait répondre.
     */
    public static function tracer(Lot $lot): Collection
    {
        return $lot->mouvements()
            ->with(['mouvement.client', 'mouvement.produit'])
            ->get()
            ->map(fn (MouvementLot $ligne) => [
                'mouvement' => $ligne->mouvement,
                'quantite'  => (float) $ligne->quantite,
                'sens'      => $ligne->mouvement?->type_mouvement,
                'motif'     => $ligne->mouvement?->sous_type,
                'client'    => $ligne->mouvement?->client?->nom,
                'piece'     => $ligne->mouvement?->reference_document,
                'date'      => $ligne->mouvement?->created_at,
            ]);
    }

    private static function base(int $entrepriseId, ?int $pointDeVenteId)
    {
        $query = Lot::with('produit')
            ->where('entreprise_id', $entrepriseId)
            ->nonVides();

        return $pointDeVenteId ? $query->where('point_de_vente_id', $pointDeVenteId) : $query;
    }
}
