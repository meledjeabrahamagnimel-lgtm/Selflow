<?php

namespace App\Modules\Admin\Regles;

use App\Modules\Admin\Modeles\Categorie;
use App\Modules\Admin\Modeles\PointDeVente;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Règles de validation cloisonnées par entreprise.
 *
 * `exists:points_de_vente,id` vérifie qu'un point de vente existe — pas qu'il
 * vous appartient. La vraie surface d'attaque d'une application multi-entreprise
 * n'est pas l'URL, qu'on regarde toujours en premier, mais le corps de la
 * requête : il suffit d'envoyer l'identifiant du point de vente d'une autre
 * entreprise pour y faire écrire une pièce, et la validation laisse passer.
 *
 * Ces fabriques posent la même vérification, cloisonnée. Elles remplacent
 * `'exists:table,id'` par `Appartenance::a('table')` partout où la valeur vient
 * de l'utilisateur.
 */
class Appartenance
{
    /**
     * Tables rattachées directement à l'entreprise.
     */
    private const RATTACHEMENT_DIRECT = [
        'points_de_vente',
        'clients',
        'fournisseurs',
        'produits',
        'categories',
        'codes_journaux',
        'utilisateurs',
        'periodes',
    ];

    /**
     * Tables rattachées à l'entreprise par leur catégorie.
     *
     * `sous_categories` ne porte pas `entreprise_id` : elle pend à une
     * catégorie, qui elle en porte un. Sans ce troisième mode, la table serait
     * refusée par la règle — ce qui est le bon comportement par défaut, mais
     * laissait `SousCategorie` sans cloisonnement du tout dans l'API produits.
     *
     * @var array<int, string>
     */
    private const RATTACHEMENT_PAR_CATEGORIE = [
        'sous_categories',
    ];

    /**
     * Tables rattachées à l'entreprise par leur point de vente.
     */
    private const RATTACHEMENT_PAR_POINT_DE_VENTE = [
        'ventes',
        'achats',
        'stocks',
        'bons_livraison',
        'ordres_production',
    ];

    /**
     * La valeur doit désigner une ligne appartenant à l'entreprise connectée.
     *
     * @param  string  $table    Table visée, telle qu'elle s'écrit en base.
     * @param  string  $colonne  Colonne comparée (`id` sauf cas particulier).
     */
    public static function a(string $table, string $colonne = 'id'): Exists
    {
        $entrepriseId = Auth::user()?->entreprise_id;

        // Sans entreprise rattachée, rien n'appartient : la règle ne peut
        // designer aucune ligne. Un `whereRaw('1 = 0')` serait equivalent, mais
        // un identifiant impossible se lit mieux dans une requete journalisee.
        if (!$entrepriseId) {
            return Rule::exists($table, $colonne)->whereNull('id');
        }

        if (in_array($table, self::RATTACHEMENT_DIRECT, true)) {
            return Rule::exists($table, $colonne)->where('entreprise_id', $entrepriseId);
        }

        if (in_array($table, self::RATTACHEMENT_PAR_POINT_DE_VENTE, true)) {
            return Rule::exists($table, $colonne)->whereIn(
                'point_de_vente_id',
                PointDeVente::where('entreprise_id', $entrepriseId)->pluck('id')
            );
        }

        if (in_array($table, self::RATTACHEMENT_PAR_CATEGORIE, true)) {
            return Rule::exists($table, $colonne)->whereIn(
                'categorie_id',
                Categorie::where('entreprise_id', $entrepriseId)->pluck('id')
            );
        }

        // Une table inconnue est une table dont on ignore le cloisonnement :
        // mieux vaut echouer bruyamment au developpement que laisser passer
        // silencieusement en production.
        throw new \InvalidArgumentException(
            "Cloisonnement inconnu pour la table « {$table} ». Ajoutez-la a "
            . "RATTACHEMENT_DIRECT, RATTACHEMENT_PAR_POINT_DE_VENTE ou "
            . "RATTACHEMENT_PAR_CATEGORIE."
        );
    }
}
