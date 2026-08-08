<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Stock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * La seule porte par laquelle le stock bouge.
 *
 * Le couple « modifier la fiche, puis écrire le mouvement » était recopié dans
 * plus de douze endroits, sur sept contrôleurs et deux contrôleurs d'API. Trois
 * conséquences, toutes constatées dans le dépôt :
 *
 * - **Rien ne garantissait la paire.** Une exception entre les deux gestes
 *   laissait un stock modifié sans trace, ou une trace sans stock. Plusieurs de
 *   ces blocs n'étaient pas dans une transaction.
 * - **`stock_avant` était lu sans verrou.** Deux ventes simultanées sur le même
 *   article lisaient la même valeur, écrivaient chacune leur `stock_apres`, et
 *   le journal annonçait un stock que la fiche démentait.
 * - **Les libellés divergeaient.** `ProductionControleur` écrivait « Entree »
 *   sans accent là où l'écran compare « Entrée » : une entrée de production
 *   s'affichait en rouge avec un signe moins.
 *
 * Ici, les trois gestes — verrouiller, modifier, journaliser — sont
 * indissociables. Une seule méthode les fait, et les motifs sont des
 * constantes.
 *
 * **Le journal ne s'efface pas.** Corriger un mouvement, c'est en écrire un de
 * sens inverse : `contrePasser()`. La ligne fautive reste lisible, et un écart
 * d'inventaire garde son explication six mois plus tard.
 */
class StockService
{
    /**
     * Faire entrer de la marchandise.
     *
     * @param  string  $motif  une des constantes de `MouvementStock`
     * @param  array{piece?: Model, reference?: string, fournisseur_id?: int, client_id?: int, contrepartie_id?: int, utilisateur_id?: int, contrepasse_id?: int}  $contexte
     */
    public static function entree(
        Produit $produit,
        int $pointDeVenteId,
        float $quantite,
        string $motif,
        array $contexte = []
    ): ?MouvementStock {
        return self::mouvementer($produit, $pointDeVenteId, $quantite, MouvementStock::ENTREE, $motif, $contexte);
    }

    /**
     * Faire sortir de la marchandise.
     *
     * Le stock peut passer sous zéro : c'est une anomalie, pas une impossibilité
     * — une livraison saisie avant sa réception, un comptage en retard. La
     * refuser ici bloquerait l'utilisateur sans rien corriger. C'est à l'appelant
     * de vérifier la disponibilité s'il doit le faire, avec `disponible()`, et
     * sous le même verrou.
     *
     * @param  array{piece?: Model, reference?: string, fournisseur_id?: int, client_id?: int, contrepartie_id?: int, utilisateur_id?: int, contrepasse_id?: int}  $contexte
     */
    public static function sortie(
        Produit $produit,
        int $pointDeVenteId,
        float $quantite,
        string $motif,
        array $contexte = []
    ): ?MouvementStock {
        return self::mouvementer($produit, $pointDeVenteId, $quantite, MouvementStock::SORTIE, $motif, $contexte);
    }

    /**
     * Déplacer de la marchandise d'un site à l'autre.
     *
     * Deux écritures, une seule transaction : un transfert à moitié écrit
     * ferait apparaître ou disparaître de la marchandise.
     *
     * @return array{0: ?MouvementStock, 1: ?MouvementStock} la sortie et l'entrée
     */
    public static function transferer(
        Produit $produit,
        int $sourceId,
        int $destinationId,
        float $quantite,
        array $contexte = []
    ): array {
        if ($sourceId === $destinationId) {
            throw new \InvalidArgumentException(
                'Un transfert va d\'un site à un autre : la source et la destination sont identiques.'
            );
        }

        return DB::transaction(function () use ($produit, $sourceId, $destinationId, $quantite, $contexte) {
            // Les deux sites sont verrouillés dans un ordre stable — le plus
            // petit identifiant d'abord — pour que deux transferts croisés
            // entre les mêmes sites ne s'attendent pas mutuellement.
            [$premier, $second] = $sourceId < $destinationId
                ? [$sourceId, $destinationId]
                : [$destinationId, $sourceId];

            self::verrouiller($produit, $premier);
            self::verrouiller($produit, $second);

            $sortie = self::sortie($produit, $sourceId, $quantite, MouvementStock::TRANSFERT,
                $contexte + ['contrepartie_id' => $destinationId]);

            $entree = self::entree($produit, $destinationId, $quantite, MouvementStock::TRANSFERT,
                $contexte + ['contrepartie_id' => $sourceId]);

            return [$sortie, $entree];
        });
    }

    /**
     * Aligner le stock sur ce qu'un comptage physique a trouvé.
     *
     * L'écart devient un mouvement, dans le sens qu'il faut. Un comptage
     * conforme n'écrit rien : un journal n'a pas à porter des lignes à zéro.
     */
    public static function inventorier(
        Produit $produit,
        int $pointDeVenteId,
        float $quantiteComptee,
        array $contexte = []
    ): ?MouvementStock {
        return DB::transaction(function () use ($produit, $pointDeVenteId, $quantiteComptee, $contexte) {
            $fiche = self::verrouiller($produit, $pointDeVenteId);
            $ecart = round($quantiteComptee - (float) $fiche->quantite_disponible, Stock::DECIMALES);

            if (abs($ecart) < 10 ** -Stock::DECIMALES) {
                return null;
            }

            return $ecart > 0
                ? self::entree($produit, $pointDeVenteId, $ecart, MouvementStock::INVENTAIRE, $contexte)
                : self::sortie($produit, $pointDeVenteId, abs($ecart), MouvementStock::INVENTAIRE, $contexte);
        });
    }

    /**
     * Annuler un mouvement par une écriture de sens inverse.
     *
     * C'est la réponse à « il faut défaire ce mouvement ». La ligne d'origine
     * reste, la nouvelle la désigne, et le stock revient à sa valeur.
     * Contre-passer deux fois le même mouvement est refusé : l'erreur
     * fabriquerait de la marchandise.
     */
    public static function contrePasser(MouvementStock $origine, array $contexte = []): MouvementStock
    {
        return DB::transaction(function () use ($origine, $contexte) {
            if ($origine->contrepassePar()->exists()) {
                throw new \LogicException(
                    "Le mouvement #{$origine->id} a déjà été contre-passé : le refaire "
                    . 'créerait de la marchandise qui n\'existe pas.'
                );
            }

            $produit = $origine->produit ?? Produit::findOrFail($origine->produit_id);

            $contexte += [
                'reference'      => $origine->reference_document,
                'piece'          => $origine->piece,
                'contrepasse_id' => $origine->id,
            ];

            $inverse = $origine->type_mouvement === MouvementStock::SORTIE
                ? self::entree($produit, $origine->point_de_vente_id, (float) $origine->quantite,
                    MouvementStock::CONTREPASSATION, $contexte)
                : self::sortie($produit, $origine->point_de_vente_id, (float) $origine->quantite,
                    MouvementStock::CONTREPASSATION, $contexte);

            // `entree`/`sortie` ne rendent null que pour un article non
            // stockable ; l'origine en est un, sinon elle n'existerait pas.
            return $inverse;
        });
    }

    /**
     * Contre-passer tous les mouvements d'une pièce.
     *
     * C'est ce qu'il faut appeler quand on modifie ou annule une vente déjà
     * facturée. `VenteControleur` faisait un `MouvementStock::where(...)
     * ->delete()` : le stock revenait juste, mais l'histoire disparaissait.
     *
     * @return int le nombre de mouvements contre-passés
     */
    public static function contrePasserLaPiece(Model $piece, array $contexte = []): int
    {
        return DB::transaction(function () use ($piece, $contexte) {
            $mouvements = MouvementStock::with('produit')
                ->where('piece_type', $piece->getMorphClass())
                ->where('piece_id', $piece->getKey())
                ->whereDoesntHave('contrepassePar')
                ->where('sous_type', '!=', MouvementStock::CONTREPASSATION)
                ->get();

            foreach ($mouvements as $mouvement) {
                self::contrePasser($mouvement, $contexte);
            }

            return $mouvements->count();
        });
    }

    /**
     * Quantité disponible, lue sous verrou.
     *
     * À utiliser lorsqu'une décision dépend du stock — refuser une vente, par
     * exemple. Lue sans verrou, la valeur est déjà périmée au moment où l'on
     * s'en sert : deux ventes simultanées la liraient toutes deux suffisante.
     */
    public static function disponible(Produit $produit, int $pointDeVenteId): float
    {
        return (float) self::verrouiller($produit, $pointDeVenteId)->quantite_disponible;
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Le geste unique : verrouiller, modifier la fiche, journaliser.
     */
    private static function mouvementer(
        Produit $produit,
        int $pointDeVenteId,
        float $quantite,
        string $sens,
        string $motif,
        array $contexte
    ): ?MouvementStock {
        // Un service ne s'épuise pas : lui tenir une fiche de stock le ferait
        // figurer en permanence dans les alertes de rupture.
        if (!$produit->estStockable()) {
            return null;
        }

        if (!in_array($motif, MouvementStock::MOTIFS, true)) {
            throw new \InvalidArgumentException(
                "Motif de mouvement inconnu : « {$motif} ». "
                . 'Les motifs valides sont des constantes de MouvementStock.'
            );
        }

        $quantite = round($quantite, Stock::DECIMALES);

        if ($quantite <= 0) {
            throw new \InvalidArgumentException(
                "Une quantité de mouvement est strictement positive ({$quantite} reçue). "
                . 'Le sens est porté par le type du mouvement, jamais par le signe.'
            );
        }

        return DB::transaction(function () use ($produit, $pointDeVenteId, $quantite, $sens, $motif, $contexte) {
            $fiche = self::verrouiller($produit, $pointDeVenteId);

            $avant = (float) $fiche->quantite_disponible;
            $apres = round($sens === MouvementStock::ENTREE ? $avant + $quantite : $avant - $quantite,
                Stock::DECIMALES);

            $fiche->update(['quantite_disponible' => $apres]);

            $piece = $contexte['piece'] ?? null;

            return MouvementStock::create([
                'produit_id'                     => $produit->id,
                'point_de_vente_id'              => $pointDeVenteId,
                'type_mouvement'                 => $sens,
                'sous_type'                      => $motif,
                'quantite'                       => $quantite,
                'stock_avant'                    => $avant,
                'stock_apres'                    => $apres,
                'point_de_vente_contrepartie_id' => $contexte['contrepartie_id'] ?? null,
                'utilisateur_id'                 => $contexte['utilisateur_id'] ?? Auth::id(),
                'fournisseur_id'                 => $contexte['fournisseur_id'] ?? null,
                'client_id'                      => $contexte['client_id'] ?? null,
                'reference_document'             => $contexte['reference'] ?? null,
                'piece_type'                     => $piece?->getMorphClass(),
                'piece_id'                       => $piece?->getKey(),
                'contrepasse_id'                 => $contexte['contrepasse_id'] ?? null,
            ]);
        });
    }

    /**
     * La fiche de stock du couple article / site, verrouillée pour cette
     * transaction, créée si elle n'existe pas.
     *
     * `lockForUpdate` fait attendre toute autre transaction qui voudrait la
     * même ligne — c'est ce qui manquait : `stock_avant` était lu librement,
     * et deux ventes simultanées écrivaient chacune un `stock_apres` calculé
     * depuis la même valeur périmée.
     *
     * Le verrou ne s'obtient que dans une transaction ; les méthodes publiques
     * en ouvrent toutes une.
     */
    private static function verrouiller(Produit $produit, int $pointDeVenteId): Stock
    {
        Stock::firstOrCreate(
            ['produit_id' => $produit->id, 'point_de_vente_id' => $pointDeVenteId],
            ['quantite_disponible' => 0, 'stock_minimum' => 5, 'stock_maximum' => 100]
        );

        return Stock::where('produit_id', $produit->id)
            ->where('point_de_vente_id', $pointDeVenteId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
