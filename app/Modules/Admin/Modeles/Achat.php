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
        'utilisateur_id',
        'fournisseur_id',
        'numero_facture',
        'numero_facture_fournisseur', // N° facture du fournisseur externe (saisie manuelle ou FNE DGI)
        'date_achat',
        'mode_paiement',
        'moyen_bancaire',
        'reference_paiement',
        'montant_ht',
        'montant_tva',
        'montant_ttc',
        'remise',          // montant de la remise globale, en francs
        'remise_taux',     // taux de la remise globale, en % → champ `discount` FNE
        'est_rne',         // → champ `isRne` FNE
        'numero_rne',      // → champ `rne` FNE
        'pied_de_page',    // → champ `footer` FNE
        'autres_mentions', // → champ `commercialMessage` FNE
        'statut',
        'etape',
        'normalise',
        'numero_fne',
        'signature_dgi',
        'qr_code_data',
        'fichier_fne_pdf_url',
        // Donnees renvoyees par la plateforme FNE lors de la certification
        'fne_alerte_stickers',
        'fne_montant_ttc',
        'fne_montant_tva',
        'fne_timbre_fiscal',
        'fne_certifie_at',
        'type_facture',
        'archived',
        'parent_id',
        'raison_avoir',
        'devise',
        'taux_change',
        'mobile_money_operateur',
    ];



    protected static function booted()
    {
        static::addGlobalScope(new \App\Modules\Admin\Scopes\PeriodeScope('date_achat'));

        static::creating(function ($model) {
            if (auth()->check()) {
                $model->utilisateur_id = auth()->id();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'date_achat'  => 'date',
            'montant_ht'  => 'decimal:2',
            'montant_tva' => 'decimal:2',
            'montant_ttc' => 'decimal:2',
            'remise'      => 'decimal:2',
            'remise_taux' => 'decimal:2',
            'est_rne'     => 'boolean',
            'fne_alerte_stickers' => 'boolean',
            'fne_montant_ttc'     => 'decimal:2',
            'fne_montant_tva'     => 'decimal:2',
            'fne_timbre_fiscal'   => 'decimal:2',
            'fne_certifie_at'     => 'datetime',
            'archived'    => 'boolean',
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

    /**
     * Taxes sur le total TTC (→ `customTaxes` à la racine du payload FNE).
     */
    public function taxesPersonnalisees(): HasMany
    {
        return $this->hasMany(AchatTaxe::class, 'achat_id');
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Achat::class, 'parent_id');
    }

    public function avoirs(): HasMany
    {
        return $this->hasMany(Achat::class, 'parent_id');
    }
}
