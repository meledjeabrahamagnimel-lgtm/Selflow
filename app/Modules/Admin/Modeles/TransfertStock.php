<?php

namespace App\Modules\Admin\Modeles;

use App\Modules\Admin\Traits\JournaliseActions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransfertStock extends Model
{
    use JournaliseActions;

    protected $table = 'transferts_stock';

    protected $fillable = [
        'produit_id',
        'point_de_vente_source_id',
        'point_de_vente_destination_id',
        'quantite',
        'statut',
        'demandeur_id',
        'approbateur_id',
        'note',
        'approuve_le',
    ];

    protected $casts = [
        'approuve_le' => 'datetime',
        'quantite'    => 'float',
    ];

    // ── Relations ────────────────────────────────────────────────

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(PointDeVente::class, 'point_de_vente_source_id');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(PointDeVente::class, 'point_de_vente_destination_id');
    }

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'demandeur_id');
    }

    public function approbateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'approbateur_id');
    }

    // ── Scopes ───────────────────────────────────────────────────

    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    public function scopeApprouve($query)
    {
        return $query->where('statut', 'approuve');
    }

    // ── Helpers ──────────────────────────────────────────────────

    public function estEnAttente(): bool
    {
        return $this->statut === 'en_attente';
    }

    public function badgeStatut(): array
    {
        return match ($this->statut) {
            'approuve'   => ['label' => 'Approuvé',   'color' => '#065f46', 'bg' => '#ecfdf5'],
            'rejete'     => ['label' => 'Rejeté',     'color' => '#991b1b', 'bg' => '#fef2f2'],
            default      => ['label' => 'En attente', 'color' => '#92400e', 'bg' => '#fef3c7'],
        };
    }
}
