<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AchatDetail extends Model
{
    protected $table = 'achat_details';
    protected $fillable = ['achat_id', 'produit_id', 'libelle_virtuel', 'quantite', 'quantite_receptionnee', 'unite', 'prix_unitaire', 'montant_tva', 'montant_ttc'];

    public function achat(): BelongsTo
    {
        return $this->belongsTo(Achat::class, 'achat_id');
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }
}
