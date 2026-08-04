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
        'remise',          // montant de la remise globale, en francs
        'remise_taux',     // taux de la remise globale, en % → champ `discount` FNE
        'statut',
        'type_facture',
        'type_piece',      // 'facture' ou 'recu' — nature du document commercial
        'piece_liee_id',   // reçu <-> facture issue l'un de l'autre
        'est_rne',         // → champ `isRne` FNE
        'numero_rne',      // → champ `rne` FNE
        'pied_de_page',    // → champ `footer` FNE
        'autres_mentions', // → champ `commercialMessage` FNE
        'normalise',
        'qr_code_data',
        'fichier_fne_pdf_url',
        'signature_dgi',
        'fne_invoice_id',
        'etape',
        'archived',
        'bon_livraison_id',
        'parent_id',
        'raison_avoir',
        'fne_invoice_id',
        'devise',
        'taux_change',
        'mobile_money_operateur',
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
            'remise_taux'   => 'decimal:2',
            'est_rne'       => 'boolean',
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

    /**
     * Taxes sur le total TTC (→ `customTaxes` à la racine du payload FNE).
     */
    public function taxesPersonnalisees(): HasMany
    {
        return $this->hasMany(VenteTaxe::class, 'vente_id');
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

    /**
     * Natures possibles d'une pièce de vente.
     */
    public const TYPE_FACTURE = 'facture';
    public const TYPE_RECU    = 'recu';

    public function estRecu(): bool
    {
        return $this->type_piece === self::TYPE_RECU;
    }

    /**
     * Reçu dont cette facture est issue, ou facture issue de ce reçu.
     * Le lien est symétrique : les deux pièces se pointent mutuellement.
     */
    public function pieceLiee(): BelongsTo
    {
        return $this->belongsTo(Vente::class, 'piece_liee_id');
    }

    /**
     * Libellé de la pièce, tel qu'affiché dans les registres.
     */
    public function libelleTypeDocument(): string
    {
        if ($this->type_facture === 'avoir') {
            return 'Facture d\'avoir';
        }

        return $this->estRecu() ? 'Reçu' : 'Facture';
    }

    public function avoirs(): HasMany
    {
        return $this->hasMany(Vente::class, 'parent_id');
    }
}
