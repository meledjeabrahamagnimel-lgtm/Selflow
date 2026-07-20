<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    protected $table = 'stocks';

    protected $fillable = [
        'produit_id',
        'point_de_vente_id',
        'quantite_disponible',
        'stock_minimum',
        'stock_maximum',
    ];

    protected function casts(): array
    {
        return [
            'quantite_disponible' => 'integer',
            'stock_minimum'       => 'integer',
            'stock_maximum'       => 'integer',
        ];
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    public function pointDeVente(): BelongsTo
    {
        return $this->belongsTo(PointDeVente::class, 'point_de_vente_id');
    }
}
