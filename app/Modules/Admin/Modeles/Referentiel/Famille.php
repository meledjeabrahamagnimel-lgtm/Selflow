<?php

namespace App\Modules\Admin\Modeles\Referentiel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Référentiel de préparamétrage — catalogue livré avec l'application.
 *
 * Ces tables ne contiennent aucune donnée client : une entreprise qui souscrit
 * à un profil en reçoit une copie chez elle, qu'elle peut modifier librement
 * sans que le référentiel en soit affecté.
 */
class Famille extends Model
{
    protected $table = 'referentiel_familles';
    protected $fillable = ['profil_id', 'code', 'nom', 'type_article_id',
                           'compte_vente', 'compte_achat', 'compte_stock',
                           'compte_variation'];

    public function profil(): BelongsTo
    {
        return $this->belongsTo(Profil::class, 'profil_id');
    }

    public function typeArticle(): BelongsTo
    {
        return $this->belongsTo(TypeArticle::class, 'type_article_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'famille_id');
    }
}
