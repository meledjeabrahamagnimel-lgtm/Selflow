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

    /**
     * Intitulé du compte de cette famille, tel qu'il figurera au plan comptable
     * de l'entreprise.
     *
     * Il se construit depuis le compte du TYPE d'article, pas depuis le numéro
     * de la famille — et c'est volontaire. Le classeur subdivise `701` en
     * `7011`, `7012`… pour ses familles, alors que l'acte uniforme réserve ces
     * quatre positions à la ventilation géographique des ventes (« Dans la
     * Région », « Hors Région »…). Lire l'intitulé du numéro de la famille
     * donnerait donc « Dans la Région » sur les vivres d'une boutique.
     *
     *     Vivres, compte de vente 701100
     *     → « Ventes de marchandises — Vivres et alimentation »
     *
     * @param  string  $champ  compte_vente, compte_achat, compte_stock ou compte_variation
     */
    public function intituleCompte(string $champ): ?string
    {
        if (empty($this->$champ)) {
            return null;
        }

        $racine = $this->typeArticle?->$champ;
        $base   = $racine ? Compte::nommer($racine) : Compte::nommer($this->$champ);

        return $base ? "{$base} — {$this->nom}" : $this->nom;
    }
}
