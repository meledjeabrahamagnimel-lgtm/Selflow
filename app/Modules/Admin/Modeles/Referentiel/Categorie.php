<?php

namespace App\Modules\Admin\Modeles\Referentiel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Référentiel de préparamétrage — catalogue livré avec l'application.
 *
 * Ces tables ne contiennent aucune donnée client : une entreprise qui souscrit
 * à un profil en reçoit une copie chez elle, qu'elle peut modifier librement
 * sans que le référentiel en soit affecté.
 */
class Categorie extends Model
{
    protected $table = 'referentiel_categories';
    protected $fillable = ['nom', 'ordre'];

    public function profils(): HasMany
    {
        return $this->hasMany(Profil::class, 'categorie_id');
    }

    /**
     * Valeur réservée à ce qui ne rentre dans aucune catégorie.
     *
     * Le référentiel couvre douze domaines et soixante-et-onze métiers ; il ne
     * couvrira jamais tout. Partout où l'utilisateur choisit dans une liste
     * fermée, il doit pouvoir en sortir — sinon il coche au hasard, et la
     * donnée ment.
     */
    public const AUTRE = 'Autre';

    /**
     * Les domaines d'activité proposés à l'utilisateur, dans l'ordre du
     * classeur.
     *
     * **Une seule liste pour toute l'application.** Trois listes écrites en
     * dur cohabitaient — dix valeurs à l'inscription, douze autres dans les
     * paramètres, douze autres encore chez le superadministrateur — et aucune
     * ne correspondait aux douze catégories du référentiel, celles-là mêmes
     * que la souscription propose à sa première étape. Une entreprise cochait
     * donc « Commercial » à l'inscription pour choisir « Commerce » à
     * l'étape suivante, sans qu'aucun des deux ne parle à l'autre.
     *
     * @return array<int, string>
     */
    public static function domaines(): array
    {
        $noms = static::orderBy('ordre')->pluck('nom')->all();

        // Le référentiel peut ne pas être chargé — une base neuve, un
        // environnement de test. L'écran doit rester utilisable : mieux vaut
        // la seule sortie libre qu'une liste vide et un formulaire qui exige
        // un choix impossible.
        $noms[] = self::AUTRE;

        return $noms;
    }
}
