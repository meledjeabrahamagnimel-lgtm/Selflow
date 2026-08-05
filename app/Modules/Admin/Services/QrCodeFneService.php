<?php

namespace App\Modules\Admin\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Support\Facades\Log;

/**
 * Code QR de vérification d'une pièce normalisée.
 *
 * Ce que ce service fait — et surtout ce qu'il ne fait pas.
 *
 * Il n'invente aucune certification. La procédure d'intégration de la DGI
 * décrit le champ `token` de la réponse de certification ainsi :
 *
 *     token | string | Code de vérification à convertir en QR code
 *
 * et impose, au chapitre I, une signature électronique « en trois éléments
 * (le QR Code, le visuel FNE et le format de la numérotation) ». Le code QR
 * n'est donc que l'encodage graphique d'une adresse fournie par la
 * plateforme : le convertir est attendu de nous, l'inventer ne le serait pas.
 *
 * D'où la règle unique de ce service : sans jeton renvoyé par la FNE, aucune
 * image n'est produite.
 */
class QrCodeFneService
{
    /**
     * Marge blanche autour du symbole, en modules.
     *
     * La norme ISO/IEC 18004 impose quatre modules : en deçà, beaucoup de
     * lecteurs ne trouvent plus les motifs de repérage.
     */
    private const MARGE_MODULES = 4;

    /**
     * Image SVG du code de vérification, prête à être posée dans un <img>.
     *
     * La taille par défaut n'est pas arbitraire : le symbole compte 45 modules
     * marge comprise, et en deçà d'environ 150 px les modules tombent sous les
     * trois pixels à l'écran — un téléphone n'accroche plus. Mesuré : lisible
     * à 150 px, illisible à 120 px. À l'impression la question ne se pose pas,
     * le tracé étant vectoriel.
     *
     * @param  string|null $token Adresse de vérification renvoyée par la FNE.
     * @return string|null Data-URI de l'image, ou null s'il n'y a rien à encoder.
     */
    public static function imageDeVerification(?string $token, int $taille = 150): ?string
    {
        $matrice = self::matrice($token);

        if ($matrice === null) {
            return null;
        }

        return 'data:image/svg+xml;base64,' . base64_encode(self::svg($matrice, $taille));
    }

    /**
     * Matrice de modules du symbole : true = module sombre.
     *
     * @return array<int,array<int,bool>>|null
     */
    public static function matrice(?string $token): ?array
    {
        $token = trim((string) $token);

        // Pas de jeton : la pièce n'est pas certifiée, il n'y a rien à montrer.
        if ($token === '') {
            return null;
        }

        // La bibliothèque d'encodage est une dépendance Composer. Si elle n'a
        // pas été installée, la facture doit rester consultable : l'adresse de
        // vérification s'affiche alors en clair, en toutes lettres. Une page
        // d'erreur 500 sur une facture serait une réponse disproportionnée à
        // un « composer install » oublié.
        if (!class_exists(Encoder::class)) {
            Log::warning('Code QR FNE non généré : exécutez « composer install » pour installer bacon/bacon-qr-code.');

            return null;
        }

        try {
            // ISO-8859-1 plutôt qu'UTF-8, à dessein : une adresse de
            // vérification ne contient que des caractères ASCII, et demander
            // l'UTF-8 ferait insérer un en-tête ECI que bien des lecteurs de
            // téléphone interprètent mal.
            //
            // Correction de niveau M : le symbole reste lisible avec un quart
            // du motif abîmé — un cachet posé de travers, un pli, une
            // photocopie fatiguée.
            $symbole = Encoder::encode($token, ErrorCorrectionLevel::M(), 'ISO-8859-1');
            $modules = $symbole->getMatrix();

            $matrice = [];
            for ($y = 0; $y < $modules->getHeight(); $y++) {
                $ligne = [];
                for ($x = 0; $x < $modules->getWidth(); $x++) {
                    $ligne[] = (bool) $modules->get($x, $y);
                }
                $matrice[] = $ligne;
            }

            return $matrice;
        } catch (\Throwable $e) {
            Log::warning('Code QR FNE non généré : ' . $e->getMessage());

            return null;
        }
    }

    /**
     * Symbole en SVG, un rectangle par module sombre.
     *
     * Le rendu SVG livré avec la bibliothèque assemble tous les modules en un
     * seul tracé, mis à l'échelle par un facteur fractionnaire. À l'écran comme
     * à l'impression, les bords des modules tombent alors entre deux pixels et
     * l'anticrénelage brouille assez la trame pour que les lecteurs échouent —
     * vérifié : le symbole ainsi produit ne se décodait pas.
     *
     * On dessine donc la trame nous-mêmes, sur une grille entière, en laissant
     * le `viewBox` faire la mise à l'échelle sans jamais déplacer les arêtes.
     *
     * @param array<int,array<int,bool>> $matrice
     */
    private static function svg(array $matrice, int $taille): string
    {
        $modules = count($matrice);
        $cote    = $modules + 2 * self::MARGE_MODULES;

        $rectangles = '';
        foreach ($matrice as $y => $ligne) {
            foreach ($ligne as $x => $sombre) {
                if (!$sombre) {
                    continue;
                }
                $rectangles .= sprintf(
                    '<rect x="%d" y="%d" width="1" height="1"/>',
                    $x + self::MARGE_MODULES,
                    $y + self::MARGE_MODULES
                );
            }
        }

        return sprintf(
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<svg xmlns="http://www.w3.org/2000/svg" width="%1$d" height="%1$d" viewBox="0 0 %2$d %2$d" '
            . 'shape-rendering="crispEdges">'
            . '<rect width="%2$d" height="%2$d" fill="#ffffff"/>'
            . '<g fill="#000000">%3$s</g>'
            . '</svg>',
            $taille,
            $cote,
            $rectangles
        );
    }
}
