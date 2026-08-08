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

    /**
     * Précision des quantités physiques : le gramme sur le kilo, le millilitre
     * sur le litre. Un `integer` faisait entrer 12,5 kg de cacao pour 12.
     */
    public const DECIMALES = 3;

    /**
     * Les quantités sont lues en `float` et non en `decimal:3`.
     *
     * Le cast `decimal` de Laravel rend des chaînes — « 12.500 » — qui se
     * comparent mal, ne s'additionnent pas dans un `array_sum` et ressortent
     * telles quelles en JSON. Le `float` garde l'arithmétique naturelle ; la
     * dérive binaire est écartée autrement : la colonne est un `decimal(15,3)`
     * en base, elle fait foi, et toute écriture passe par `StockService` qui
     * arrondit à trois décimales. Aucun cumul ne se fait en mémoire d'une
     * opération à l'autre.
     */
    protected function casts(): array
    {
        return [
            'quantite_disponible' => 'float',
            'stock_minimum'       => 'float',
            'stock_maximum'       => 'float',
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
