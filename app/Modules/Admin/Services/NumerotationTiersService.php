<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\Fournisseur;
use Illuminate\Support\Str;

/**
 * Le numéro d'un tiers, tel que Comptaflow le fabrique.
 *
 * ## Deux notions, deux colonnes
 *
 * | Notion | Colonne | Exemple | Rôle |
 * |---|---|---|---|
 * | **Compte général de rattachement** | `compte_comptable` | `411000` | le compte collectif du grand livre |
 * | **Numéro de tiers** | `numero_tiers` | `410001`, `41KON1` | la fiche auxiliaire de *cette* personne |
 *
 * Le compte général appartient au plan comptable ; son intitulé — « Clients »
 * — ne change jamais. Le numéro de tiers désigne un client précis et n'existe
 * que dans le plan de tiers.
 *
 * ## La règle vient de Comptaflow, et ce n'est pas un détail
 *
 * `ExternalSyncController::tiers()` retrouve un tiers **par égalité de chaîne**
 * sur `numero_de_tiers`. Une convention différente d'un côté et de l'autre, et
 * plus aucun tiers n'est reconnu : chaque écriture déversée retombe sur son
 * compte collectif, sans que rien ne le signale. Cette classe reproduit donc
 * `AdminConfigController::getNextTierNumber()` à l'identique :
 *
 * - **le préfixe tient sur deux caractères** — `41` pour les clients, `40`
 *   pour les fournisseurs. Ce sont les deux premiers chiffres du compte
 *   collectif, pas ses trois premiers ;
 * - **le numéro fait `LONGUEUR` caractères en tout**, séquence comprise ;
 * - en `numeric`, la base est le préfixe seul : `41` + `0001` → `410001` ;
 * - en `alphanumeric`, la base est le préfixe suivi de **trois lettres** du
 *   nom : `41` + `KON` + `1` → `41KON1`.
 *
 * @see \App\Modules\Admin\Services\ComptabiliteService la seule porte d'écriture
 *      des écritures, qui range le général et le tiers dans deux colonnes.
 */
class NumerotationTiersService
{
    /** Le système numérote seul : préfixe + séquence. */
    public const NUMERIQUE = 'numeric';

    /** Le système dérive du nom : préfixe + trois lettres + séquence. */
    public const ALPHANUMERIQUE = 'alphanumeric';

    public const CONVENTIONS = [
        self::NUMERIQUE      => 'Numérique (410001)',
        self::ALPHANUMERIQUE => 'Alphanumérique (41KON1)',
    ];

    /**
     * La longueur totale d'un numéro de tiers.
     *
     * **Elle doit valoir celle de Comptaflow** (`companies.tier_digits`).
     * Six, comme les comptes de Selflow — c'est la décision de numérotation
     * arrêtée pour toute la partie locale.
     */
    public const LONGUEUR = 6;

    /** Le nombre de lettres du nom retenues en convention alphanumérique. */
    private const LETTRES = 3;

    /**
     * Le préfixe d'un compte collectif : ses **deux** premiers chiffres.
     *
     * `411000` → `41`. Comptaflow prend deux caractères, pas trois ; en
     * prendre trois produirait des numéros que sa recherche ne trouverait
     * jamais.
     */
    public static function prefixe(string $compteGeneral): string
    {
        return substr($compteGeneral, 0, 2);
    }

    /**
     * Le numéro de tiers d'un client.
     */
    public static function pourClient(Entreprise $entreprise, string $compteGeneral, string $nom): string
    {
        return self::fabriquer($entreprise, $compteGeneral, $nom, Client::class);
    }

    /**
     * Le numéro de tiers d'un fournisseur.
     */
    public static function pourFournisseur(Entreprise $entreprise, string $compteGeneral, string $nom): string
    {
        return self::fabriquer($entreprise, $compteGeneral, $nom, Fournisseur::class);
    }

    /**
     * Un numéro de tiers est-il cohérent avec son compte de rattachement ?
     *
     * Rien n'empêchait de donner un tiers `41…` à un client rattaché à
     * `401000` : l'écriture partait alors sur le collectif fournisseurs avec
     * un tiers client, et le grand livre devenait faux sans qu'aucun contrôle
     * ne s'en émeuve.
     */
    public static function estCoherent(?string $numeroTiers, ?string $compteGeneral): bool
    {
        if (empty($numeroTiers) || empty($compteGeneral)) {
            return false;
        }

        return str_starts_with($numeroTiers, self::prefixe($compteGeneral));
    }

    /**
     * Le numéro de tiers ne doit jamais être le compte collectif lui-même.
     */
    public static function estLeCompteCollectif(?string $numeroTiers, ?string $compteGeneral): bool
    {
        return $numeroTiers !== null && $compteGeneral !== null && $numeroTiers === $compteGeneral;
    }

    /**
     * Le numéro réservé au tiers « divers » d'un collectif.
     *
     * `410000`, `400000` : la place zéro. Elle précède la séquence, qui
     * démarre à 1, et ne la fera donc jamais avancer — au contraire d'un
     * numéro haut comme `419999`, qui pousserait le suivant hors de la
     * longueur permise.
     */
    public static function divers(string $compteGeneral): string
    {
        $prefixe = self::prefixe($compteGeneral);

        return $prefixe . str_repeat('0', self::LONGUEUR - strlen($prefixe));
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * @param  class-string<Client|Fournisseur>  $modele
     */
    private static function fabriquer(Entreprise $entreprise, string $compteGeneral, string $nom, string $modele): string
    {
        $prefixe = self::prefixe($compteGeneral);
        $base    = $prefixe;

        if ($entreprise->numerotation_tiers === self::ALPHANUMERIQUE) {
            $lettres = self::lettresDuNom($nom);

            // Un nom qui ne laisse aucune lettre — « 123 », « --- » — ne donne
            // pas de radical. Comptaflow y met « XXX » ; on préfère retomber
            // sur la base numérique, qui reste lisible et ne crée pas une
            // famille de tiers tous nommés XXX.
            if ($lettres !== '') {
                $base = $prefixe . $lettres;
            }
        }

        return $base . self::sequence($entreprise, $base, $modele);
    }

    /**
     * Les lettres du nom retenues pour le radical.
     *
     * Les chiffres sont écartés : un radical numérique se confondrait avec la
     * séquence, et ferait sauter le numéro suivant.
     */
    private static function lettresDuNom(string $nom): string
    {
        $propre = Str::of($nom)->ascii()->upper()->replaceMatches('/[^A-Z]/', '')->toString();

        return substr($propre, 0, self::LETTRES);
    }

    /**
     * La séquence qui complète la base jusqu'à la longueur voulue.
     *
     * @param  class-string<Client|Fournisseur>  $modele
     */
    private static function sequence(Entreprise $entreprise, string $base, string $modele): string
    {
        $place = max(0, self::LONGUEUR - strlen($base));

        if ($place === 0) {
            return '';
        }

        $plusHaut = 0;

        foreach ($modele::where('entreprise_id', $entreprise->id)
            ->where('numero_tiers', 'like', $base . '%')
            ->pluck('numero_tiers') as $numero) {
            $suffixe = substr((string) $numero, strlen($base));

            if ($suffixe !== '' && ctype_digit($suffixe)) {
                $plusHaut = max($plusHaut, (int) $suffixe);
            }
        }

        return str_pad((string) ($plusHaut + 1), $place, '0', STR_PAD_LEFT);
    }
}
