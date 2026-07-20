<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2bNegotiation extends Model
{
    protected $table = 'b2b_negotiations';

    protected $fillable = [
        'entreprise_client_id',
        'entreprise_fournisseur_id',
        'statut',
        'produits_demandes',
        'prix_final',
        'type_facturation',
        'historique_discussions',
    ];

    protected $casts = [
        'produits_demandes'      => 'array',
        'historique_discussions' => 'array',
        'prix_final'             => 'decimal:2',
    ];

    public function entrepriseClient(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_client_id');
    }

    public function entrepriseFournisseur(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_fournisseur_id');
    }
}
