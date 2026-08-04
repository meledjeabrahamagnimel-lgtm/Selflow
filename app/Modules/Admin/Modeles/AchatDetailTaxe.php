<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot d'une taxe personnalisée sur une ligne d'achat / de bordereau BAPA.
 */
class AchatDetailTaxe extends Model
{
    protected $table = 'achat_detail_taxes';

    protected $fillable = ['achat_detail_id', 'nom', 'taux'];

    protected function casts(): array
    {
        return ['taux' => 'decimal:2'];
    }

    public function detail(): BelongsTo
    {
        return $this->belongsTo(AchatDetail::class, 'achat_detail_id');
    }
}
