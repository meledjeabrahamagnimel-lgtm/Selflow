<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot d'une taxe personnalisée sur une ligne de vente
 * (→ `items[].customTaxes` du payload FNE).
 */
class VenteDetailTaxe extends Model
{
    protected $table = 'vente_detail_taxes';

    protected $fillable = ['vente_detail_id', 'nom', 'taux'];

    protected function casts(): array
    {
        return ['taux' => 'decimal:2'];
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(VenteDetail::class, 'vente_detail_id');
    }
}
