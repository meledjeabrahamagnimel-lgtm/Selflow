<?php

namespace App\Modules\Admin\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * Génération du QR code de vérification exigé par la DGI.
 *
 * Le code était auparavant demandé à un service en ligne (api.qrserver.com) :
 * l'image ne s'affichait donc pas sans accès à ce service, ni sur les factures
 * à l'écran, ni dans les PDF exportés. Il est désormais dessiné localement, en
 * SVG, et intégré au document sous forme de data URI — aucune requête réseau,
 * un rendu net à n'importe quelle taille et une capture PDF fidèle.
 */
class QrCodeService
{
    /**
     * QR code d'une valeur, en SVG.
     *
     * @param string $contenu Donnée à encoder (URL de vérification FNE)
     * @param int    $taille  Côté du carré, en pixels
     */
    public static function svg(string $contenu, int $taille = 200): string
    {
        // Correction de niveau M : le compromis usuel entre densité et
        // tolérance aux impressions médiocres.
        $matrice = Encoder::encode($contenu, ErrorCorrectionLevel::M())->getMatrix();

        $modules = $matrice->getWidth();

        // Marge silencieuse : la norme QR impose 4 modules de blanc autour du
        // motif, sans quoi les lecteurs peinent à l'accrocher.
        $marge = 4;
        $total = $modules + ($marge * 2);

        $carres = '';
        for ($ligne = 0; $ligne < $modules; $ligne++) {
            for ($colonne = 0; $colonne < $modules; $colonne++) {
                if ($matrice->get($colonne, $ligne) === 1) {
                    $x = $colonne + $marge;
                    $y = $ligne + $marge;
                    $carres .= sprintf('<rect x="%d" y="%d" width="1" height="1"/>', $x, $y);
                }
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" shape-rendering="crispEdges">'
            . '<rect width="%d" height="%d" fill="#ffffff"/><g fill="#000000">%s</g></svg>',
            $taille,
            $taille,
            $total,
            $total,
            $total,
            $total,
            $carres
        );
    }

    /**
     * Même QR code, encodé en data URI directement utilisable dans un `src`.
     */
    public static function dataUri(string $contenu, int $taille = 200): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::svg($contenu, $taille));
    }
}
