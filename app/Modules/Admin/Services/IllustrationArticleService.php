<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\Produit;

/**
 * L'illustration d'un article qui n'a pas de photo.
 *
 * Le catalogue et l'écran de caisse posaient le **même sac gris** sous chaque
 * article : trente cartes identiques, où seul le texte distinguait un sac de
 * riz d'une prestation de conseil. Sur un écran de caisse, on cherche l'article
 * à sa forme avant de lire son nom ; une grille uniforme oblige à tout lire.
 *
 * ## Ce que ce n'est pas
 *
 * **Ce n'est pas une photo, et cela ne prétend pas l'être.** Aucune image
 * d'internet n'est allée chercher « une bouteille qui ressemble à la vôtre » :
 * ce serait montrer une marchandise que l'entreprise ne vend pas. Ce sont des
 * dessins au trait, tenus dans le dépôt, choisis d'après ce que l'article dit
 * de lui-même. La vraie photo, quand elle existe, passe toujours devant.
 *
 * ## Comment le choix se fait
 *
 * Par mots, dans cet ordre : le nom de l'article, puis sa famille, puis sa
 * catégorie. Le premier mot reconnu l'emporte — l'ordre des entrées compte
 * donc, et les plus précises viennent en tête : « eau de javel » relève de
 * l'hygiène, non de l'eau minérale.
 *
 * Rien n'est deviné au-delà : un article dont aucun mot ne parle reçoit
 * l'illustration de son type — marchandise ou prestation — plutôt qu'une
 * image prise au hasard qui raconterait autre chose.
 */
class IllustrationArticleService
{
    /**
     * Les mots qui désignent une illustration, du plus précis au plus large.
     *
     * L'ordre fait la règle : « eau de javel » doit rencontrer « javel » avant
     * de rencontrer « eau », sans quoi le produit d'entretien s'illustrerait
     * d'une goutte d'eau minérale.
     */
    private const MOTS = [
        'hygiene'       => ['javel', 'savon', 'detergent', 'nettoyant', 'desinfect', 'hygiene',
                            'entretien', 'lessive', 'dentifrice', 'shampo', 'couche', 'papier hygien',
                            'serviette', 'gel', 'balai', 'serpill'],
        'huile'         => ['huile', 'bidon'],
        'eau'           => ['eau minerale', 'eau de source', 'bouteille d\'eau', 'sachet d\'eau'],
        'boisson'       => ['boisson', 'jus', 'soda', 'biere', 'canette', 'sucrerie', 'limonade',
                            'vin', 'liqueur', 'whisky', 'cafe', 'the ', 'sirop'],
        'boulangerie'   => ['pain', 'baguette', 'croissant', 'patisser', 'boulanger', 'gateau',
                            'viennoiser', 'brioche'],
        'alimentation'  => ['riz', 'mais', 'mil ', 'farine', 'sucre', 'sel ', 'lait', 'conserve',
                            'tomate', 'pate alimentaire', 'spaghetti', 'attieke', 'igname',
                            'manioc', 'vivres', 'alimentation', 'epice', 'cube', 'assaisonn'],
        'informatique'  => ['ordinateur', 'portable', 'clavier', 'souris', 'ecran', 'imprimante',
                            'informatique', 'cartouche', 'toner', 'disque', 'cle usb', 'onduleur',
                            'logiciel', 'licence', 'abonnement'],
        'telephonie'    => ['telephone', 'smartphone', 'mobile', 'sim ', 'recharge', 'credit de communication'],
        'bureau'        => ['bureau', 'stylo', 'cahier', 'ramette', 'classeur', 'agrafe',
                            'chemise', 'registre', 'papeterie', 'enveloppe', 'marqueur'],
        'mobilier'      => ['chaise', 'table', 'fauteuil', 'armoire', 'etagere', 'mobilier', 'bureau meuble'],
        'construction'  => ['ciment', 'fer ', 'brique', 'sable', 'gravier', 'peinture', 'tole',
                            'quincaill', 'clou', 'vis ', 'tuyau', 'carreau', 'btp', 'chantier',
                            'plomberie', 'electricite batiment'],
        'habillement'   => ['pagne', 'tissu', 'chemise homme', 'pantalon', 'robe', 'chaussure',
                            'habillement', 'vetement', 'uniforme', 'tenue'],
        'sante'         => ['medicament', 'pharmac', 'comprime', 'sirop medical', 'pansement',
                            'consultation', 'soin', 'analyse medicale', 'sante'],
        'formation'     => ['formation', 'seminaire', 'atelier', 'cours', 'stage', 'enseignement',
                            'scolaire', 'manuel'],
        'transport'     => ['transport', 'livraison', 'fret', 'carburant', 'essence', 'gasoil',
                            'course', 'vehicule', 'pneu'],
        'energie'       => ['bougie', 'ampoule', 'lampe', 'pile', 'batterie', 'groupe electrogene',
                            'gaz', 'eclairage', 'energie'],
        'emballage'     => ['casier', 'caisse', 'emballage', 'consigne', 'carton', 'sachet', 'bidon vide'],
        'agriculture'   => ['engrais', 'semence', 'plant', 'agricole', 'elevage', 'volaille',
                            'poussin', 'aliment betail', 'cacao', 'cafe cerise', 'hevea', 'anacarde'],
        // « formalité » ne dit pas de quel côté on est : « Frais de greffe,
        // timbres et formalités » est un débours, « Création d'entreprise
        // (formalités) » est un honoraire. On s'appuie sur ce qui les sépare.
        'debours'       => ['debours', 'refactur', 'frais de greffe',
                            'droit d\'enregistrement'],
        'conseil'       => ['conseil', 'assistance', 'audit', 'expertise', 'accompagnement',
                            'declaration', 'etats financiers', 'liasse', 'comptab', 'juridique',
                            'honoraire', 'mission', 'creation d\'entreprise'],
    ];

    /** Ce qu'on montre quand aucun mot ne parle. */
    private const DEFAUTS = [
        'service'                  => 'service',
        'consommable_non_stockable' => 'service',
    ];

    private const DEFAUT_GENERAL = 'marchandise';

    /**
     * L'adresse de l'illustration d'un article.
     *
     * Toujours une adresse : c'est ce qu'on attend d'une image d'attente.
     */
    public static function pour(Produit $produit): string
    {
        return asset('images/articles/' . self::cle($produit) . '.svg');
    }

    /**
     * Le nom du dessin retenu.
     *
     * Rendu à part de l'adresse : les épreuves y lisent le choix sans avoir à
     * démonter une adresse, et la vue s'en sert comme classe.
     */
    public static function cle(Produit $produit): string
    {
        // On ne va pas chercher les relations en base : la méthode est appelée
        // une fois par carte, et trente cartes feraient soixante requêtes de
        // plus à chaque ouverture de la caisse. Ce qui a été chargé sert ; le
        // reste est simplement absent du texte examiné.
        $sources = [$produit->nom];

        foreach (['sousCategorieRelation', 'category'] as $relation) {
            if ($produit->relationLoaded($relation)) {
                $sources[] = $produit->getRelation($relation)?->nom;
            }
        }

        $matiere = self::normaliser(implode(' ', array_filter($sources)));

        foreach (self::MOTS as $dessin => $mots) {
            foreach ($mots as $mot) {
                if (str_contains($matiere, self::normaliser($mot))) {
                    return $dessin;
                }
            }
        }

        return self::DEFAUTS[$produit->type] ?? self::DEFAUT_GENERAL;
    }

    /**
     * Sans accents, sans casse.
     *
     * « Hygiène » et « HYGIENE » désignent la même chose, et le catalogue
     * porte les deux — l'écran du propriétaire montre « INFORMATIQUE » et
     * « Hygiène et entretien » côte à côte.
     */
    private static function normaliser(string $texte): string
    {
        $texte = mb_strtolower(trim($texte));

        return strtr($texte, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i',
            'ô' => 'o', 'ö' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c',
        ]);
    }
}
