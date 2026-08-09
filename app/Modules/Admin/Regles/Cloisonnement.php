<?php

namespace App\Modules\Admin\Regles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Ce qui appartient à l'entreprise de celui qui demande — et ce qui n'existe
 * pas pour lui.
 *
 * ## Pourquoi 404 et non 403
 *
 * Les gardes d'appartenance répondaient « accès refusé ». C'est exact, et c'est
 * précisément le problème : **répondre 403 sur la pièce d'un autre et 404 sur
 * une pièce inexistante distingue les deux**, et la différence se lit. Les
 * identifiants étant séquentiels, il suffisait de demander
 * `/admin/ventes/facture/1`, `/2`, `/3` … et de compter les 403 pour connaître
 * **le nombre de factures de toute la plateforme**, puis son rythme de
 * croissance en recommençant une semaine plus tard.
 *
 * Ce n'était pas une faille d'autorisation — le cloisonnement, lui, tenait :
 * aucune donnée d'autrui n'était rendue. C'était une **fuite de volume**, et
 * pour une plateforme vendue à des entreprises concurrentes, le volume est une
 * information commerciale.
 *
 * Une pièce qui n'est pas la vôtre n'existe pas pour vous : elle répond donc
 * comme tout ce qui n'existe pas.
 *
 * ## Ce que cela ne remplace pas
 *
 * Le 404 supprime l'oracle, non le besoin d'identifiants opaques : une adresse
 * qui porte `4213` dit encore quelque chose à qui la voit passer — dans un
 * courriel transféré, une capture d'écran, un billet d'assistance. Les modèles
 * qui ne sont pas exposés à l'API portent donc aussi un identifiant tiré au
 * hasard.
 */
class Cloisonnement
{
    /**
     * Refuser l'accès à ce qui n'appartient pas à l'entreprise de l'appelant.
     *
     * @param  Model|null  $piece      la pièce demandée
     * @param  string      $colonne    le chemin vers l'identifiant d'entreprise
     */
    public static function verifier(?Model $piece, string $colonne = 'entreprise_id'): void
    {
        abort_if($piece === null, 404);

        abort_unless(self::appartient($piece, $colonne), 404);
    }

    /**
     * La pièce appartient-elle à l'entreprise de l'appelant ?
     */
    public static function appartient(Model $piece, string $colonne = 'entreprise_id'): bool
    {
        $mienne = Auth::user()?->entreprise_id;

        if ($mienne === null) {
            return false;
        }

        // Certaines pièces portent l'entreprise par leur point de vente : une
        // vente appartient à un magasin, qui appartient à une entreprise.
        $sienne = $piece->{$colonne}
            ?? $piece->pointDeVente?->entreprise_id;

        return $sienne !== null && (int) $sienne === (int) $mienne;
    }
}
