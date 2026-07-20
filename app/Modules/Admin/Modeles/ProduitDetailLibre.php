<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProduitDetailLibre extends Model
{
    protected $table = 'produit_details_libres';

    protected $fillable = [
        'produit_id',
        'titre',
        'description',
        'ordre',
    ];

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }
}
