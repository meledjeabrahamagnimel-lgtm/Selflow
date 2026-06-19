<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Achat extends Model
{
    protected $table = 'achats';

    protected $fillable = [
        'point_de_vente_id',
        'fournisseur_id',
        'numero_facture',
        'date_achat',
        'mode_paiement',
        'montant_ht',
        'montant_tva',
        'montant_ttc',
        'statut',
        'etape',
    ];

    protected function casts(): array
    {
        return [
            'date_achat'  => 'date',
            'montant_ht'  => 'decimal:2',
            'montant_tva' => 'decimal:2',
            'montant_ttc' => 'decimal:2',
        ];
    }

    public function pointDeVente(): BelongsTo
    {
        return $this->belongsTo(PointDeVente::class, 'point_de_vente_id');
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_id');
    }

    protected $appends = ['montant_paye'];
    public function details(): HasMany
    {
        return $this->hasMany(AchatDetail::class, 'achat_id');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(TresorerieJournal::class, 'reference_document', 'numero_facture')
            ->where('type_operation', 'Décaissement');
    }

    public function getMontantPayeAttribute()
    {
        return $this->paiements()->sum('montant_sortie');
    }
}
