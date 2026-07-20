<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Categorie extends Model
{
    protected $table = 'categories';

    protected $fillable = [
        'entreprise_id',
        'nom',
        'prefixe',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    public function sousCategories(): HasMany
    {
        return $this->hasMany(SousCategorie::class, 'categorie_id');
    }

    public function produits(): HasMany
    {
        return $this->hasMany(Produit::class, 'categorie_id');
    }
}
