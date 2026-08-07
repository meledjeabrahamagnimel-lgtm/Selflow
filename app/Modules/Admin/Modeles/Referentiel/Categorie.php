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
}
