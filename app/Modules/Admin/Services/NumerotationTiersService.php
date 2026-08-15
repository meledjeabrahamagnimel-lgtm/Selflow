<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\Fournisseur;
use Illuminate\Support\Str;

/**
 * Le numéro d'un tiers, et ce qu'il ne faut pas confondre avec lui.
 *
 * ## Deux notions, deux colonnes
 *
 * | Notion | Colonne | Exemple | Rôle |
 * |---|---|---|---|
 * | **Compte général de rattachement** | `compte_comptable` | `411000` | le compte collectif du grand livre |
 * | **Numéro de tiers** | `numero_tiers` | `411001`, `411KONE` | la fiche auxiliaire de *cette* personne |
 *
 * Le compte général est celui du plan comptable ; son intitulé — « Clients » —
 * ne change jamais. Le numéro de tiers désigne un client précis et n'existe
 * que dans le plan de tiers.
 *
 * **La numérotation automatique démarrait à `411000`** : le premier client
 * créé portait donc, comme numéro de tiers, le numéro du compte collectif
 * lui-même. Les deux notions se rejoignaient en base, et un relevé de ce
 * client remontait le solde de tous. La séquence part désormais de `001`, et
 * `411000` est refusé explicitement.
 *
 * ## Deux conventions, au choix de l'entreprise
 *
 * Elles se valent, et le cabinet comptable de chacun a la sienne :
 *
 * - **`sequence`** — `411001`, `411002`. Jamais d'homonyme, illisible sans la
 *   fiche ;
 * - **`nom`** — `411KONE`, `401KOFFI`. Lisible en grand livre, c'est la
 *   convention Sage. Les homonymes reçoivent un suffixe : `411KONE2`.
 */
class NumerotationTiersService
{
    public const SEQUENCE = 'sequence';
    public const NOM      = 'nom';

    public const CONVENTIONS = [
        self::SEQUENCE => 'Racine + séquence (411001)',
        self::NOM      => 'Racine + nom (411KONE)',
    ];

    /** Nombre de chiffres de la séquence : 411001 → 411999. */
    private const LARGEUR_SEQUENCE = 3;

    /**
     * La racine d'un compte général : les trois premiers chiffres.
     *
     * `411000` → `411`. C'est elle que porte le numéro de tiers, et c'est par
     * elle qu'on vérifie qu'un tiers est rattaché au bon collectif.
     */
    public static function racine(string $compteGeneral): string
    {
        return substr($compteGeneral, 0, 3);
    }

    /**
     * Le numéro de tiers suivant, pour un client.
     */
    public static function pourClient(Entreprise $entreprise, string $compteGeneral, string $nom): string
    {
        return self::suivant($entreprise, $compteGeneral, $nom, Client::class);
    }

    /**
     * Le numéro de tiers suivant, pour un fournisseur.
     */
    public static function pourFournisseur(Entreprise $entreprise, string $compteGeneral, string $nom): string
    {
        return self::suivant($entreprise, $compteGeneral, $nom, Fournisseur::class);
    }

    /**
     * Un numéro de tiers est-il cohérent avec son compte de rattachement ?
     *
     * Rien n'empêchait de donner `411002` à un client et de le rattacher à
     * `401000`. L'écriture partait alors sur le collectif fournisseurs avec un
     * tiers client, et le grand livre devenait faux sans qu'aucun contrôle ne
     * s'en émeuve.
     */
    public static function estCoherent(?string $numeroTiers, ?string $compteGeneral): bool
    {
        if (empty($numeroTiers) || empty($compteGeneral)) {
            return false;
        }

        return str_starts_with($numeroTiers, self::racine($compteGeneral));
    }

    /**
     * Le numéro de tiers ne doit **jamais** être le compte général lui-même.
     *
     * C'est la confusion des deux notions, écrite en base : un tiers `411000`
     * rattaché au collectif `411000` fait remonter, dans le relevé de ce
     * client, le solde de tous les autres.
     */
    public static function estLeCompteCollectif(?string $numeroTiers, ?string $compteGeneral): bool
    {
        return $numeroTiers !== null && $compteGeneral !== null
            && $numeroTiers === $compteGeneral;
    }

    /**
     * La règle de validation d'un numéro saisi à la main.
     *
     * Elle n'exige plus des chiffres seulement : `411KONE` est une convention
     * répandue, et l'ancienne expression `^411[0-9]*$` la refusait.
     *
     * @return array<int, string>
     */
    public static function reglesDeSaisie(string $racine): array
    {
        return ['required', 'string', 'max:32', 'regex:/^' . preg_quote($racine, '/') . '[A-Z0-9]+$/'];
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * @param  class-string<Client|Fournisseur>  $modele
     */
    private static function suivant(Entreprise $entreprise, string $compteGeneral, string $nom, string $modele): string
    {
        $racine = self::racine($compteGeneral);

        return $entreprise->numerotation_tiers === self::NOM
            ? self::parLeNom($entreprise, $racine, $nom, $modele)
            : self::parLaSequence($entreprise, $racine, $modele);
    }

    /**
     * @param  class-string<Client|Fournisseur>  $modele
     */
    private static function parLaSequence(Entreprise $entreprise, string $racine, string $modele): string
    {
        $existants = $modele::where('entreprise_id', $entreprise->id)
            ->where('numero_tiers', 'like', $racine . '%')
            ->pluck('numero_tiers');

        $plusHaut = 0;

        foreach ($existants as $numero) {
            $suffixe = substr((string) $numero, strlen($racine));

            // Un numéro à convention « nom » — 411KONE — n'entre pas dans le
            // calcul : les deux conventions peuvent cohabiter sur une même
            // entreprise qui a changé d'avis en cours de route.
            if ($suffixe !== '' && ctype_digit($suffixe)) {
                $plusHaut = max($plusHaut, (int) $suffixe);
            }
        }

        // La séquence part de 001 : à zéro, le numéro de tiers vaudrait le
        // compte collectif.
        return $racine . str_pad((string) ($plusHaut + 1), self::LARGEUR_SEQUENCE, '0', STR_PAD_LEFT);
    }

    /**
     * @param  class-string<Client|Fournisseur>  $modele
     */
    private static function parLeNom(Entreprise $entreprise, string $racine, string $nom, string $modele): string
    {
        $radical = Str::of($nom)->ascii()->upper()->replaceMatches('/[^A-Z0-9]/', '')->limit(8, '')->toString();

        // Un nom qui ne laisse **aucune lettre** — « 123 », « --- » — ne donne
        // pas de radical utilisable. Deux raisons de repasser à la séquence
        // plutôt que d'accepter `411123` :
        //
        // - un radical vide réduirait le numéro à sa racine, c'est-à-dire au
        //   compte collectif ;
        // - un radical tout en chiffres se confond avec un numéro de séquence,
        //   et ferait sauter la suite à `411124`.
        if ($radical === '' || ctype_digit($radical)) {
            return self::parLaSequence($entreprise, $racine, $modele);
        }

        $candidat = $racine . $radical;
        $rang     = 1;

        while ($modele::where('entreprise_id', $entreprise->id)->where('numero_tiers', $candidat)->exists()) {
            $candidat = $racine . $radical . (++$rang);
        }

        return $candidat;
    }
}
