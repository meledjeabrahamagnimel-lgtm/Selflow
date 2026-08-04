<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AchatDetail extends Model
{
    protected $table = 'achat_details';
    protected $fillable = ['achat_id', 'produit_id', 'libelle_virtuel', 'quantite', 'quantite_receptionnee', 'unite', 'prix_unitaire', 'remise_taux', 'montant_tva', 'montant_ttc'];

    protected function casts(): array
    {
        return ['remise_taux' => 'decimal:2'];
    }

    public function achat(): BelongsTo
    {
        return $this->belongsTo(Achat::class, 'achat_id');
    }

    /**
     * Taxes personnalisées de la ligne (→ `items[].customTaxes` du payload FNE).
     */
    public function taxes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AchatDetailTaxe::class, 'achat_detail_id');
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }
}
