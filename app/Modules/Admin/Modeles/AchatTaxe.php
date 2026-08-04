<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Taxe sur le total TTC d'un achat / bordereau BAPA.
 */
class AchatTaxe extends Model
{
    protected $table = 'achat_taxes';

    protected $fillable = ['achat_id', 'nom', 'taux', 'montant'];

    protected function casts(): array
    {
        return [
            'taux'    => 'decimal:2',
            'montant' => 'decimal:2',
        ];
    }

    public function achat(): BelongsTo
    {
        return $this->belongsTo(Achat::class, 'achat_id');
    }
}
