<?php

namespace App\Modules\Admin\Regles;

use App\Modules\Admin\Modeles\Stock;

/**
 * La règle de validation d'une quantité physique.
 *
 * Énoncée une fois, appliquée partout. Elle était recopiée dans onze
 * contrôleurs, et sept d'entre eux disaient `integer` : une réception de
 * 12,5 kg de cacao était refusée à la saisie, ou pire, acceptée puis tronquée.
 * Les quatre autres disaient `numeric` sans borner les décimales, ce qui
 * laissait entrer 12,5555 kg dans une colonne qui n'en garde que trois — la
 * base arrondissait, et le stock cessait de correspondre à ce que
 * l'utilisateur avait tapé.
 *
 * Trois bornes, et la raison de chacune :
 *
 * - **`decimal:0,3`** — le gramme sur le kilo. Au-delà, la colonne arrondirait
 *   sans le dire ; mieux vaut refuser la saisie que la déformer.
 * - **`min`** — une quantité nulle ou négative n'est pas une quantité. Un
 *   retour de marchandise est une entrée, pas une sortie négative : le sens du
 *   mouvement est porté par son type, jamais par le signe.
 * - **`max`** — la colonne est un `decimal(15,3)`, soit douze chiffres avant la
 *   virgule. Sans borne, une saisie à seize chiffres provoquerait une erreur de
 *   base de données au lieu d'un message de formulaire.
 */
class Quantite
{
    /** Plus petite quantité représentable, à trois décimales. */
    public const PAS = 0.001;

    /** Plus grande quantité que la colonne `decimal(15,3)` accepte. */
    public const PLAFOND = 999999999999;

    /**
     * Une quantité physique strictement positive : ce que l'on vend, achète,
     * livre, réceptionne, transfère ou produit.
     *
     * @return array<int, string>
     */
    public static function physique(): array
    {
        return ['required', 'numeric', 'decimal:0,' . Stock::DECIMALES,
                'min:' . self::PAS, 'max:' . self::PLAFOND];
    }

    /**
     * Une quantité qui peut être nulle : un seuil d'alerte, un stock
     * d'ouverture à zéro, une ligne comptée vide à l'inventaire.
     *
     * @return array<int, string>
     */
    public static function facultative(): array
    {
        return ['nullable', 'numeric', 'decimal:0,' . Stock::DECIMALES,
                'min:0', 'max:' . self::PLAFOND];
    }
}
