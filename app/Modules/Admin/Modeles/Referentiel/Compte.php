<?php

namespace App\Modules\Admin\Modeles\Referentiel;

use Illuminate\Database\Eloquent\Model;

/**
 * Référentiel de préparamétrage — catalogue livré avec l'application.
 *
 * Ces tables ne contiennent aucune donnée client : une entreprise qui souscrit
 * à un profil en reçoit une copie chez elle, qu'elle peut modifier librement
 * sans que le référentiel en soit affecté.
 */
class Compte extends Model
{
    protected $table = 'referentiel_comptes';
    protected $fillable = ['numero', 'racine', 'intitule', 'classe', 'commun'];
    protected $casts = ['commun' => 'boolean'];

    /**
     * Intitulé d'un compte, même s'il n'est pas à l'acte uniforme.
     *
     * Le référentiel impute sur des subdivisions que le plan ne liste pas —
     * `603110` par exemple. Elles héritent de leur racine la plus longue :
     * `6031`, « Variations des stocks de marchandises », jamais `60` qui ne
     * dirait rien d'utile.
     */
    public static function nommer(string $numero): ?string
    {
        $exact = static::where('numero', $numero)->value('intitule');

        if ($exact) {
            return $exact;
        }

        for ($longueur = 5; $longueur >= 2; $longueur--) {
            $racine = substr($numero, 0, $longueur) . str_repeat('0', 6 - $longueur);
            $intitule = static::where('numero', $racine)->value('intitule');

            if ($intitule) {
                return $intitule;
            }
        }

        return null;
    }
}
