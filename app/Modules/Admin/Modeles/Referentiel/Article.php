<?php

namespace App\Modules\Admin\Modeles\Referentiel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Référentiel de préparamétrage — catalogue livré avec l'application.
 *
 * Ces tables ne contiennent aucune donnée client : une entreprise qui souscrit
 * à un profil en reçoit une copie chez elle, qu'elle peut modifier librement
 * sans que le référentiel en soit affecté.
 */
class Article extends Model
{
    protected $table = 'referentiel_articles';
    protected $fillable = ['profil_id', 'famille_id', 'code', 'designation',
                           'unite', 'type_article_id', 'compte_vente',
                           'compte_achat', 'compte_stock'];

    public function profil(): BelongsTo
    {
        return $this->belongsTo(Profil::class, 'profil_id');
    }

    public function famille(): BelongsTo
    {
        return $this->belongsTo(Famille::class, 'famille_id');
    }

    public function typeArticle(): BelongsTo
    {
        return $this->belongsTo(TypeArticle::class, 'type_article_id');
    }
}
