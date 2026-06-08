<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vente extends Model
{
    protected $table = 'ventes';

    protected $fillable = [
        'point_de_vente_id',
        'client_id',
        'numero_facture',
        'date_vente',
        'mode_paiement',
        'montant_ht',
        'montant_tva',
        'montant_ttc',
        'remise',
        'statut',
        'type_facture',
        'normalise',
        'qr_code_data',
    ];

    protected function casts(): array
    {
        return [
            'date_vente'    => 'date',
            'montant_ht'    => 'decimal:2',
            'montant_tva'   => 'decimal:2',
            'montant_ttc'   => 'decimal:2',
            'remise'        => 'decimal:2',
            'normalise'     => 'boolean',
        ];
    }

    public function pointDeVente(): BelongsTo
    {
        return $this->belongsTo(PointDeVente::class, 'point_de_vente_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(VenteDetail::class, 'vente_id');
    }
}
