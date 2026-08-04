<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Taxe personnalisée par défaut d'un produit (champ `customTaxes` de l'article
 * dans le payload FNE). Exemples DGI : GRA, AIRSI.
 */
class ProduitTaxe extends Model
{
    protected $table = 'produit_taxes';

    protected $fillable = ['produit_id', 'nom', 'taux'];

    protected function casts(): array
    {
        return ['taux' => 'decimal:2'];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }
}
