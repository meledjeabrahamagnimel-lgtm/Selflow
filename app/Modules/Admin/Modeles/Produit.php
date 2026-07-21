<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Produit extends Model
{
    protected $table = 'produits';

    /**
     * Les 6 types d'articles et leurs libellés affichés.
     */
    public const TYPES = [
        'marchandise'               => 'Marchandise (Stockable)',
        'matiere_premiere'          => 'Matière Première',
        'produit_fini'              => 'Produit Fini (Fabriqué)',
        'consommable_stockable'     => 'Consommable Stockable',
        'consommable_non_stockable' => 'Consommable Non Stockable',
        'service'                   => 'Service (Non Physique)',
    ];

    /**
     * Types pour lesquels le stock est géré (décrémenté à la vente / incrémenté à l'achat).
     */
    public const TYPES_STOCKABLES = [
        'marchandise',
        'matiere_premiere',
        'produit_fini',
        'consommable_stockable',
    ];

    /**
     * Indique si ce produit gère un stock physique.
     */
    public function estStockable(): bool
    {
        return in_array($this->type, self::TYPES_STOCKABLES);
    }

    /**
     * Libellé affiché du type.
     */
    public function libelleType(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }

    protected $fillable = [
        'entreprise_id',
        'reference',
        'nom',
        'type',
        'categorie_id',
        'sous_categorie_id',
        'unite',
        'prix_achat',
        'prix_vente',
        'taux_tva',
        'compte_vente',
        'compte_achat',
        'quantite_commandee',
        'quantite_a_receptionner',
        // Phase 1 — catalogue enrichi
        'photo',
        'date_arrivee',
        'date_peremption',
        'provenance',
        'description_inventaire',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'prix_achat'              => 'decimal:2',
            'prix_vente'              => 'decimal:2',
            'taux_tva'                => 'decimal:2',
            'categorie_id'            => 'integer',
            'sous_categorie_id'       => 'integer',
            'quantite_commandee'      => 'integer',
            'quantite_a_receptionner' => 'integer',
            'date_arrivee'            => 'date',
            'date_peremption'         => 'date',
        ];
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($produit) {
            if (empty($produit->reference)) {
                $produit->reference = self::genererReference($produit->entreprise_id, $produit->categorie_id);
            }
        });
    }

    public static function genererReference($entrepriseId, $categorieId): string
    {
        $categorie = Categorie::find($categorieId);
        $prefixe = $categorie ? strtoupper($categorie->prefixe) : 'PROD';
        
        $count = self::where('entreprise_id', $entrepriseId)
            ->where('categorie_id', $categorieId)
            ->count();

        $sequence = $count + 1;

        do {
            $reference = $prefixe . '-' . str_pad($sequence, 5, '0', STR_PAD_LEFT);
            $exists = self::where('entreprise_id', $entrepriseId)
                ->where('reference', $reference)
                ->exists();
            if (!$exists) {
                return $reference;
            }
            $sequence++;
        } while (true);
    }

    // ─── Scopes Phase 1 ──────────────────────────────────────────────────────

    public function scopeActifs($query)
    {
        return $query->where('statut', 'actif');
    }

    public function scopeArchives($query)
    {
        return $query->where('statut', 'archive');
    }

    // ─── Accessor Photo ──────────────────────────────────────────────────────

    public function getPhotoUrlAttribute(): string
    {
        if ($this->photo) {
            if (str_starts_with($this->photo, 'http://') || str_starts_with($this->photo, 'https://')) {
                return $this->photo;
            }
            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($this->photo)) {
                return asset('storage/' . $this->photo);
            }
        }
        return asset('images/placeholder-produit.png');
    }

    // ─── Helpers Phase 1 ─────────────────────────────────────────────────────

    public function estArchive(): bool
    {
        return $this->statut === 'archive';
    }

    public function estPerime(): bool
    {
        return $this->date_peremption && $this->date_peremption->isPast();
    }

    public function bientotPerime(int $joursAlerte = 30): bool
    {
        return $this->date_peremption
            && !$this->estPerime()
            && $this->date_peremption->diffInDays(now()) <= $joursAlerte;
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    public function detailsLibres(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProduitDetailLibre::class, 'produit_id')->orderBy('ordre');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }

    public function sousCategorieRelation(): BelongsTo
    {
        return $this->belongsTo(SousCategorie::class, 'sous_categorie_id');
    }

    /**
     * Accesseurs de compatibilité pour l'ancienne valeur texte
     */
    public function getCategorieAttribute(): string
    {
        return $this->category ? $this->category->nom : '';
    }

    public function getSousCategorieAttribute(): string
    {
        return $this->sousCategorieRelation ? $this->sousCategorieRelation->nom : '';
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class, 'produit_id');
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

    public function ficheTechnique(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(FicheTechnique::class, 'produit_fini_id');
    }

    /**
     * Obtenir la quantité disponible pour un point de vente donné.
     */
    public function stockActuel($pointDeVenteId): int
    {
        $stock = $this->stocks->where('point_de_vente_id', $pointDeVenteId)->first();
        return $stock ? $stock->quantite_disponible : 0;
    }

    /**
     * Obtenir le stock minimum pour un point de vente donné.
     */
    public function stockMinimum($pointDeVenteId): int
    {
        $stock = $this->stocks->where('point_de_vente_id', $pointDeVenteId)->first();
        return $stock ? $stock->stock_minimum : 5;
    }

    /**
     * Obtenir le stock maximum pour un point de vente donné.
     */
    public function stockMaximum($pointDeVenteId): int
    {
        $stock = $this->stocks->where('point_de_vente_id', $pointDeVenteId)->first();
        return $stock ? $stock->stock_maximum : 100;
    }

    /**
     * Accesseurs dynamiques de compatibilité basés sur le point de vente actif.
     */
    public static function getActivePdvId(): ?int
    {
        if (session()->has('point_de_vente_actif_id')) {
            return session('point_de_vente_actif_id');
        }
        if (auth()->check()) {
            if (auth()->user()->point_de_vente_id) {
                session([
                    'point_de_vente_actif_id' => auth()->user()->point_de_vente_id,
                    'point_de_vente_actif_nom' => optional(auth()->user()->pointDeVente)->nom ?? 'Siège'
                ]);
                return auth()->user()->point_de_vente_id;
            }
            $pdv = \App\Modules\Admin\Modeles\PointDeVente::where('entreprise_id', auth()->user()->entreprise_id)
                ->where('nom', 'Siège')
                ->first() 
                ?? \App\Modules\Admin\Modeles\PointDeVente::where('entreprise_id', auth()->user()->entreprise_id)->first();
            if ($pdv) {
                session([
                    'point_de_vente_actif_id' => $pdv->id,
                    'point_de_vente_actif_nom' => $pdv->nom
                ]);
                return $pdv->id;
            }
        }
        return null;
    }

    public function getStockActuelAttribute(): int
    {
        $pdvId = self::getActivePdvId();
        if (!$pdvId) {
            $stock = $this->stocks->first();
            return $stock ? $stock->quantite_disponible : 0;
        }
        return $this->stockActuel($pdvId);
    }

    public function getStockMinimumAttribute(): int
    {
        $pdvId = self::getActivePdvId();
        if (!$pdvId) {
            $stock = $this->stocks->first();
            return $stock ? $stock->stock_minimum : 5;
        }
        return $this->stockMinimum($pdvId);
    }

    public function setStockActuelAttribute($value): void
    {
        $pdvId = self::getActivePdvId();
        if ($pdvId) {
            Stock::updateOrCreate([
                'produit_id'        => $this->id,
                'point_de_vente_id' => $pdvId,
            ], [
                'quantite_disponible' => $value,
            ]);
        }
    }

    public function setStockMinimumAttribute($value): void
    {
        $pdvId = self::getActivePdvId();
        if ($pdvId) {
            Stock::updateOrCreate([
                'produit_id'        => $this->id,
                'point_de_vente_id' => $pdvId,
            ], [
                'stock_minimum' => $value,
            ]);
        }
    }

    /**
     * Incrémenter le stock pour un point de vente donné.
     */
    public function incrementStock($pointDeVenteId, $quantite): void
    {
        $stock = Stock::firstOrCreate([
            'produit_id'        => $this->id,
            'point_de_vente_id' => $pointDeVenteId,
        ], [
            'quantite_disponible' => 0,
            'stock_minimum'       => 5,
            'stock_maximum'       => 100,
        ]);

        $stock->increment('quantite_disponible', $quantite);
    }

    /**
     * Décrémenter le stock pour un point de vente donné.
     */
    public function decrementStock($pointDeVenteId, $quantite): void
    {
        $stock = Stock::firstOrCreate([
            'produit_id'        => $this->id,
            'point_de_vente_id' => $pointDeVenteId,
        ], [
            'quantite_disponible' => 0,
            'stock_minimum'       => 5,
            'stock_maximum'       => 100,
        ]);

        $stock->decrement('quantite_disponible', $quantite);
    }

    /**
     * Calcule la quantité prévisionnelle de stock :
     * Prévision = Stock Actuel - Quantité Commandée + Quantité à Réceptionner
     */
    public function getPrevisionAttribute(): int
    {
        return $this->stock_actuel - $this->quantite_commandee + $this->quantite_a_receptionner;
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
