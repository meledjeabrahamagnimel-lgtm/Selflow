<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produit extends Model
{
    protected $table = 'produits';

    protected $fillable = [
        'entreprise_id',
        'reference',
        'nom',
        'categorie',
        'prix_achat',
        'prix_vente',
        'stock_actuel',
        'stock_minimum',
    ];

    protected function casts(): array
    {
        return [
            'prix_achat'   => 'decimal:2',
            'prix_vente'   => 'decimal:2',
            'stock_actuel' => 'integer',
            'stock_minimum'=> 'integer',
        ];
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    public function venteDetails(): HasMany
    {
        return $this->hasMany(VenteDetail::class, 'produit_id');
    }

    public function achatDetails(): HasMany
    {
        return $this->hasMany(AchatDetail::class, 'produit_id');
    }

    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class, 'produit_id');
    }

    /**
     * Détermine l'état du stock de l'article.
     */
    public function etatStock(): string
    {
        if ($this->stock_actuel <= 0) {
            return 'Rupture';
        }
        if ($this->stock_actuel <= $this->stock_minimum) {
            return 'Faible';
        }
        return 'Normal';
    }
}
