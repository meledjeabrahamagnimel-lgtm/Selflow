<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La ventilation d'un mouvement de stock entre les lots qu'il a touchés.
 *
 * Un mouvement de stock reste **un seul mouvement** : la comptabilité, le CUMP
 * (Coût Unitaire Moyen Pondéré) et le journal ne changent pas. Une sortie de
 * trente unités prise sur deux arrivages écrit donc une ligne de journal et
 * deux lignes ici.
 *
 * C'est ce qui permet de répondre à un rappel du fabricant sans réécrire
 * l'inventaire permanent : le lot rappelé dit quelles sorties l'ont emporté, et
 * chaque sortie dit chez quel client.
 */
class MouvementLot extends Model
{
    protected $table = 'mouvement_lots';

    protected $fillable = [
        'mouvement_stock_id',
        'lot_id',
        'quantite',
    ];

    protected function casts(): array
    {
        return ['quantite' => 'float'];
    }

    public function mouvement(): BelongsTo
    {
        return $this->belongsTo(MouvementStock::class, 'mouvement_stock_id');
    }

    public function lot(): BelongsTo
    {
        return $this->belongsTo(Lot::class, 'lot_id');
    }
}
