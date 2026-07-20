<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonLivraisonDetail extends Model
{
    protected $table = 'bon_livraison_details';

    protected $fillable = [
        'bon_livraison_id',
        'produit_id',
        'libelle',
        'unite',
        'qte_commandee',
        'qte_livree',
    ];

    protected $appends = ['reliquat'];

    // ── Relations ──────────────────────────────────────────────────────────────

    public function bonLivraison(): BelongsTo
    {
        return $this->belongsTo(BonLivraison::class, 'bon_livraison_id');
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    // ── Accesseurs ─────────────────────────────────────────────────────────────

    /** Quantité restante à livrer */
    public function getReliquatAttribute(): int
    {
        return max(0, $this->qte_commandee - $this->qte_livree);
    }
}
