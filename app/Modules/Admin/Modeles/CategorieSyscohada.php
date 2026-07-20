<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategorieSyscohada extends Model
{
    protected $table = 'categories_syscohada';

    protected $fillable = [
        'entreprise_id',
        'libelle_affiche',
        'compte_comptable_reel',
        'type_lie',
        'type_produit_lie',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }
}
