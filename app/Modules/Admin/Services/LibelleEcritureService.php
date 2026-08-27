<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\ModeleLibelle;

/**
 * Rend le libellé d'une opération ou d'une de ses lignes, à partir du gabarit
 * que l'entreprise a posé — ou du gabarit par défaut, qui reproduit ce que
 * l'application écrivait avant d'être paramétrable.
 *
 * Pourquoi ce service existe : jusqu'ici, le libellé d'une écriture de vente
 * était l'**intitulé SYSCOHADA du compte mouvementé** — « Vente de
 * marchandises ». Or le compte porte déjà ce nom : le répéter dans la seule
 * colonne de texte libre du journal la gaspille. Un grand livre du 701 dont
 * chaque ligne dit « Vente de marchandises » n'apprend rien à qui le relit ;
 * ce qu'on veut y lire, c'est **quelle pièce, quel client, quels articles,
 * quel site**.
 *
 * Ce que le service ne fait pas : réécrire le passé. Les écritures déjà
 * enregistrées gardent le libellé qu'elles portaient. Un journal se lit tel
 * qu'il a été tenu.
 */
class LibelleEcritureService
{
    /** La colonne `libelle` fait 255 caractères ; au-delà, MySQL tronquerait. */
    private const LONGUEUR_MAX = 255;

    /**
     * Les caractères qui séparent deux morceaux d'un libellé. Ils servent au
     * nettoyage : un jeton vide ne doit pas laisser derrière lui le séparateur
     * qui l'annonçait.
     */
    private const SEPARATEURS = '\/\-–—,;|';

    /**
     * Les gabarits déjà lus, par entreprise. Une facture écrit une dizaine de
     * lignes ; sans ce cache, chacune relirait la même ligne de la table.
     *
     * @var array<int, array<string, ModeleLibelle>>
     */
    private static array $memo = [];

    /**
     * Le libellé de l'opération elle-même.
     *
     * @param  array<string, string|null>  $jetons
     */
    public static function operation(?int $entrepriseId, string $type, array $jetons): string
    {
        return self::rendre($entrepriseId, $type, 'operation', $jetons);
    }

    /**
     * Le libellé d'une ligne d'écriture. `$role` dit ce que la ligne fait —
     * « Facturation Vente », « TVA Collectée Vente », ou le nom des articles.
     *
     * @param  array<string, string|null>  $jetons
     */
    public static function ligne(?int $entrepriseId, string $type, string $role, array $jetons): string
    {
        return self::rendre($entrepriseId, $type, 'ligne', $jetons + ['role' => $role]);
    }

    /**
     * Applique un gabarit donné à des jetons, sans passer par la base. C'est
     * ce qui sert à l'aperçu de l'écran de paramétrage : l'utilisateur doit
     * voir ce que son gabarit produira **avant** de l'enregistrer.
     *
     * @param  array<string, string|null>  $jetons
     */
    public static function appliquer(string $gabarit, array $jetons): string
    {
        $rendu = $gabarit;

        foreach (ModeleLibelle::JETONS as $jeton => $_) {
            $nom = trim($jeton, '{}');
            // Un jeton absent du tableau vaut la chaîne vide : c'est le cas
            // d'une vente sans client, d'un règlement sans référence.
            $rendu = str_replace($jeton, (string) ($jetons[$nom] ?? ''), $rendu);
        }

        return self::nettoyer($rendu);
    }

    /**
     * Vide le cache des gabarits. Appelé après l'enregistrement du
     * paramétrage, sans quoi la même requête continuerait d'écrire avec
     * l'ancien gabarit.
     */
    public static function oublier(?int $entrepriseId = null): void
    {
        if ($entrepriseId === null) {
            self::$memo = [];
            return;
        }

        unset(self::$memo[$entrepriseId]);
    }

    /**
     * @param  array<string, string|null>  $jetons
     */
    private static function rendre(?int $entrepriseId, string $type, string $cible, array $jetons): string
    {
        $defauts = ModeleLibelle::defaut($type);
        $gabarit = self::gabarit($entrepriseId, $type, $cible) ?: $defauts[$cible];

        $rendu = self::appliquer($gabarit, $jetons);

        // Un gabarit qui ne produit rien — tous ses jetons vides — laisserait
        // une écriture sans libellé, et le journal deviendrait illisible.
        // On retombe alors sur le défaut, puis sur le nom du type.
        if ($rendu === '') {
            $rendu = self::appliquer($defauts[$cible], $jetons);
        }

        if ($rendu === '') {
            $rendu = ModeleLibelle::TYPES[$type] ?? $type;
        }

        return mb_substr($rendu, 0, self::LONGUEUR_MAX);
    }

    private static function gabarit(?int $entrepriseId, string $type, string $cible): ?string
    {
        if (!$entrepriseId) {
            return null;
        }

        if (!isset(self::$memo[$entrepriseId])) {
            self::$memo[$entrepriseId] = ModeleLibelle::where('entreprise_id', $entrepriseId)
                ->get()
                ->keyBy('type_operation')
                ->all();
        }

        $modele = self::$memo[$entrepriseId][$type] ?? null;

        return $modele
            ? (trim((string) $modele->{'gabarit_' . $cible}) ?: null)
            : null;
    }

    /**
     * Retire ce qu'un jeton vide laisse derrière lui.
     *
     * `Rglt/{piece}/{reference}/Vente {produits}` sur un règlement sans
     * référence donnerait `Rglt/FV-1//Vente Ciment` : la double barre est le
     * séparateur d'un morceau qui n'existe pas. De même, `{piece} — {tiers}`
     * sur une vente sans client finirait par un tiret suspendu.
     *
     * Le premier séparateur d'une suite est conservé **avec ses espaces** :
     * c'est ce qui permet à `Rglt/FV-1/Vente` de rester collé là où
     * `FV-1 / Facturation` garde les siens.
     */
    private static function nettoyer(string $texte): string
    {
        $texte = preg_replace('/\s+/u', ' ', $texte) ?? $texte;

        $texte = preg_replace(
            '/(\s*[' . self::SEPARATEURS . ']\s*)(?:[' . self::SEPARATEURS . ']\s*)+/u',
            '$1',
            $texte
        ) ?? $texte;

        // `trim()` avec une liste de caractères travaille octet par octet : il
        // couperait un tiret cadratin en son milieu et laisserait deux octets
        // invalides. Le nettoyage des bords passe donc par une expression
        // régulière en mode Unicode.
        return preg_replace(
            '/^[\s' . self::SEPARATEURS . ']+|[\s' . self::SEPARATEURS . ']+$/u',
            '',
            $texte
        ) ?? $texte;
    }
}
