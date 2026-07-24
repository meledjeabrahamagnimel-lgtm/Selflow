<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vente extends Model
{
    protected $table = 'ventes';

    protected $fillable = [
        'point_de_vente_id',
        'utilisateur_id',
        'client_id',
        'numero_facture',
        'numero_fne',
        'date_vente',
        'mode_paiement',
        'moyen_bancaire',
        'reference_paiement',
        'montant_ht',
        'montant_tva',
        'montant_ttc',
        'remise',
        'statut',
        'type_facture',
        'normalise',
        'qr_code_data',
        'fichier_fne_pdf_url',
        'signature_dgi',
        'etape',
        'archived',
        'bon_livraison_id',
        'parent_id',
        'raison_avoir',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new \App\Modules\Admin\Scopes\PeriodeScope('date_vente'));

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->utilisateur_id = auth()->id();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'date_vente'    => 'date',
            'montant_ht'    => 'decimal:2',
            'montant_tva'   => 'decimal:2',
            'montant_ttc'   => 'decimal:2',
            'remise'        => 'decimal:2',
            'normalise'     => 'boolean',
            'archived'      => 'boolean',
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

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Authentification\Modeles\Utilisateur::class, 'utilisateur_id');
    }

    protected $appends = ['montant_paye'];

    public function details(): HasMany
    {
        return $this->hasMany(VenteDetail::class, 'vente_id');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(TresorerieJournal::class, 'reference_document', 'numero_facture')
            ->where('type_operation', 'Encaissement');
    }

    public function getMontantPayeAttribute()
    {
        return $this->paiements()->sum('montant_entree');
    }

    /**
     * Si ce BC a généré un Bon de Livraison
     */
    public function bonLivraison(): HasOne
    {
        return $this->hasOne(BonLivraison::class, 'vente_id');
    }

    /**
     * Le BL dont cette Facture est issue (via bon_livraison_id)
     */
    public function bonLivraisonSource(): BelongsTo
    {
        return $this->belongsTo(BonLivraison::class, 'bon_livraison_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Vente::class, 'parent_id');
    }

    public function avoirs(): HasMany
    {
        return $this->hasMany(Vente::class, 'parent_id');
    }
}
