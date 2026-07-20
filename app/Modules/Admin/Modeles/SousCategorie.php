<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SousCategorie extends Model
{
    protected $table = 'sous_categories';

    protected $fillable = [
        'categorie_id',
        'nom',
    ];

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }

    public function produits(): HasMany
    {
        return $this->hasMany(Produit::class, 'sous_categorie_id');
    }
}
