<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FicheTechniqueDetail extends Model
{
    protected $table = 'fiche_technique_details';

    protected $fillable = [
        'fiche_technique_id',
        'ingredient_id',
        'quantite',
        'unite',
    ];

    protected $casts = [
        'quantite' => 'decimal:4',
    ];

    public function ficheTechnique(): BelongsTo
    {
        return $this->belongsTo(FicheTechnique::class, 'fiche_technique_id');
    }

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'ingredient_id');
    }
}
