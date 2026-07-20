<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FicheTechnique extends Model
{
    protected $table = 'fiches_techniques';

    protected $fillable = [
        'entreprise_id',
        'produit_fini_id',
        'description',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    public function produitFini(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_fini_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(FicheTechniqueDetail::class, 'fiche_technique_id');
    }
}
