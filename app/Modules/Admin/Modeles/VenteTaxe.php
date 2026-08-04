<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Taxe sur le total TTC d'une vente (→ `customTaxes` à la racine du payload FNE).
 * Exemple DGI : DTD.
 */
class VenteTaxe extends Model
{
    protected $table = 'vente_taxes';

    protected $fillable = ['vente_id', 'nom', 'taux', 'montant'];

    protected function casts(): array
    {
        return [
            'taux'    => 'decimal:2',
            'montant' => 'decimal:2',
        ];
    }

    public function vente(): BelongsTo
    {
        return $this->belongsTo(Vente::class, 'vente_id');
    }
}
