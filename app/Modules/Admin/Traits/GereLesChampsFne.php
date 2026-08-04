<?php

namespace App\Modules\Admin\Traits;

/**
 * Champs exigés par la FNE (DGI) partagés par les pièces de vente et d'achat :
 * remises en pourcentage, taxes personnalisées, rattachement à un reçu (RNE)
 * et mentions libres.
 *
 * Règle métier commune imposée par la DGI : tout taux — remise comme taxe —
 * est un pourcentage compris entre 0 et 100.
 */
trait GereLesChampsFne
{
    /**
     * Longueur maximale des mentions libres transmises à la FNE
     * (`commercialMessage` et `footer`).
     */
    public static int $longueurMaxMention = 248;

    /**
     * Règles de validation des champs FNE communs à la vente et au BAPA.
     */
    protected static function reglesChampsFne(): array
    {
        return [
            // Remise globale, en pourcentage du total HT
            'remise_taux' => ['nullable', 'numeric', 'min:0', 'max:100'],
            // Remise par article, en pourcentage
            'articles.*.remise_taux' => ['nullable', 'numeric', 'min:0', 'max:100'],

            // Rattachement à un reçu normalisé déjà émis
            'est_rne'    => ['nullable', 'boolean'],
            'numero_rne' => ['nullable', 'required_if:est_rne,1', 'string', 'max:64'],

            // Mentions libres
            'autres_mentions' => ['nullable', 'string', 'max:' . self::$longueurMaxMention],
            'pied_de_page'    => ['nullable', 'string', 'max:' . self::$longueurMaxMention],

            // Taxes sur le total TTC
            'taxes_ttc'        => ['nullable', 'array'],
            'taxes_ttc.*.nom'  => ['required_with:taxes_ttc.*.taux', 'string', 'max:100'],
            'taxes_ttc.*.taux' => ['required_with:taxes_ttc.*.nom', 'numeric', 'gt:0', 'max:100'],
        ];
    }

    protected static function messagesChampsFne(): array
    {
        return [
            'numero_rne.required_if'   => 'Veuillez saisir le numéro du reçu auquel cette pièce est rattachée.',
            'remise_taux.max'          => 'La remise globale ne peut pas dépasser 100 %.',
            'articles.*.remise_taux.max' => 'La remise d\'un article ne peut pas dépasser 100 %.',
            'taxes_ttc.*.nom.required_with'  => 'Chaque taxe sur le total TTC doit avoir un nom.',
            'taxes_ttc.*.taux.gt'      => 'Le taux d\'une taxe doit être strictement supérieur à 0 %.',
            'taxes_ttc.*.taux.max'     => 'Le taux d\'une taxe ne peut pas dépasser 100 %.',
        ];
    }

    /**
     * Borne un taux saisi entre 0 et 100.
     */
    protected static function tauxBorne($valeur): float
    {
        return max(0.0, min(100.0, round(floatval($valeur), 2)));
    }

    /**
     * Tronque une mention libre à la longueur acceptée par la FNE.
     */
    protected static function mentionBornee($valeur): ?string
    {
        $texte = trim((string) $valeur);

        return $texte === '' ? null : mb_substr($texte, 0, self::$longueurMaxMention);
    }

    /**
     * Recopie les taxes personnalisées d'un produit sur une ligne de pièce.
     *
     * C'est un instantané : la ligne conserve les taxes telles qu'elles étaient
     * au moment de la facturation, même si la fiche produit change ensuite.
     */
    protected static function copierTaxesProduitSurLigne($ligne, $produit): void
    {
        if (!$produit) {
            return;
        }

        foreach ($produit->taxes as $taxe) {
            $ligne->taxes()->create([
                'nom'  => $taxe->nom,
                'taux' => $taxe->taux,
            ]);
        }
    }

    /**
     * Enregistre les taxes sur le total TTC d'une pièce, en calculant le
     * montant de chacune à partir du TTC fourni.
     */
    protected static function enregistrerTaxesSurTtc($piece, $taxes, float $totalTtc): void
    {
        $piece->taxesPersonnalisees()->delete();

        foreach ((array) $taxes as $taxe) {
            $nom  = trim((string) ($taxe['nom'] ?? ''));
            $taux = self::tauxBorne($taxe['taux'] ?? 0);

            if ($nom === '' || $taux <= 0) {
                continue;
            }

            $piece->taxesPersonnalisees()->create([
                'nom'     => $nom,
                'taux'    => $taux,
                'montant' => round($totalTtc * $taux / 100, 2),
            ]);
        }
    }
}
