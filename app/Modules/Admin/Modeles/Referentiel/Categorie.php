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

    /**
     * Les domaines qu'un formulaire doit accepter d'une entreprise donnée.
     *
     * Le référentiel, **plus ce que l'entreprise porte déjà**. Sans ce second
     * terme, deux situations bloquent un formulaire tout entier :
     *
     * - une entreprise enregistrée sous l'ancien vocabulaire — « Commercial »,
     *   « Agro-industrie » — ne peut plus enregistrer **aucune** modification,
     *   même sans toucher à son domaine : la valeur déjà en base revient dans
     *   le formulaire et se fait refuser ;
     * - sur une base dont le référentiel n'est pas chargé, la liste se réduit
     *   à « Autre » et tout le reste devient invalide.
     *
     * Rien ne se perd, et rien de neuf ne se glisse : une valeur hors
     * référentiel n'est acceptée que si l'entreprise la portait déjà.
     *
     * Elle servait à l'écran des paramètres de l'entreprise, où le secteur se
     * cochait à la main. Ce bloc a été retiré le 24/08/2026 — le domaine se
     * choisit au parcours de configuration. La méthode reste : les écrans du
     * superadministrateur valident encore sur `domaines()` seul, et y
     * refuseront un jour une entreprise au vocabulaire d'avant.
     *
     * @return array<int, string>
     */
    public static function domainesAcceptesPour(?\App\Modules\Admin\Modeles\Entreprise $entreprise): array
    {
        $deja = is_array($entreprise?->secteur_activite) ? $entreprise->secteur_activite : [];

        return array_values(array_unique(array_merge(static::domaines(), $deja)));
    }
}
