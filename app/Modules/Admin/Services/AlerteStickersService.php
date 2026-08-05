<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\Entreprise;

/**
 * Alerte de réapprovisionnement en stickers de certification.
 *
 * Chaque pièce certifiée consomme un sticker. À zéro, la plateforme refuse de
 * certifier : les ventes continuent d'être enregistrées dans Selflow, mais
 * elles ne sont plus normalisées — donc plus conformes tant que le stock n'est
 * pas reconstitué. L'alerte doit prévenir avant ce point, pas après.
 *
 * Le solde vient de la dernière réponse de certification (`balance_sticker`) :
 * c'est un relevé, pas un compteur tenu par Selflow. Il ne se met à jour qu'à
 * la certification suivante.
 */
class AlerteStickersService
{
    /**
     * État de l'alerte, ou null s'il n'y a rien à signaler.
     *
     * @return array{niveau:string,solde:int,seuil:int,valeur:float,pieces_restantes:int}|null
     */
    public static function pour(?Entreprise $entreprise): ?array
    {
        // Le solde n'a de sens qu'en mode stickers. En mode provision, la DGI
        // décompte des francs et ne renvoie aucun nombre de vignettes.
        if (!$entreprise || $entreprise->fne_mode_facturation !== 'stickers') {
            return null;
        }

        $solde = (int) ($entreprise->fne_sticker_balance ?? 0);
        $seuil = (int) ($entreprise->sticker_solde_alerte
            ?: config('selflow.sticker_seuil_alerte_defaut', 5));

        if ($solde > $seuil) {
            return null;
        }

        return [
            // À zéro, ce n'est plus une alerte mais un arrêt : plus aucune
            // pièce ne peut être certifiée.
            'niveau'           => $solde <= 0 ? 'epuise' : 'bas',
            'solde'            => $solde,
            'seuil'            => $seuil,
            'valeur'           => $solde * (float) config('selflow.sticker_prix_unitaire', 20),
            'pieces_restantes' => max(0, $solde),
        ];
    }
}
