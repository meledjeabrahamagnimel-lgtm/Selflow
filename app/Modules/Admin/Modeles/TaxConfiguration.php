<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxConfiguration extends Model
{
    protected $table = 'tax_configurations';

    protected $fillable = [
        'entreprise_id',
        'regime',
        'categorie',
        'tva_active',
        'tva_taux',
        'tse_active',
        'tse_taux',
        'tdt_active',
        'tdt_taux',
        'tdt_seuil',
    ];

    protected $casts = [
        'tva_active' => 'boolean',
        'tse_active' => 'boolean',
        'tdt_active' => 'boolean',
        'tva_taux'   => 'decimal:2',
        'tse_taux'   => 'decimal:2',
        'tdt_taux'   => 'decimal:2',
        'tdt_seuil'  => 'decimal:2',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    /**
     * Déduire automatiquement la catégorie (CAS A ou CAS B) depuis le régime.
     */
    public static function categorieDepuisRegime(?string $regime): string
    {
        return in_array($regime, ['RNI', 'RSI']) ? 'CAS_A' : 'CAS_B';
    }

    /**
     * Calculer le montant TSE sur un CA HT donné.
     */
    public function calculerTSE(float $caHt): float
    {
        if (!$this->tse_active || $this->categorie === 'CAS_B') {
            return 0;
        }
        return round($caHt * ($this->tse_taux / 100), 2);
    }

    /**
     * Calculer le TDT sur un montant espèces TTC.
     */
    public function calculerTDT(float $montantEspeces): float
    {
        if (!$this->tdt_active || $montantEspeces <= (float) $this->tdt_seuil) {
            return 0;
        }
        return round($montantEspeces * ($this->tdt_taux / 100), 2);
    }

    /**
     * Libellé de la catégorie.
     */
    public function categorieLabel(): string
    {
        return $this->categorie === 'CAS_A'
            ? 'CAS A — Assujetti TVA (RNI / RSI)'
            : 'CAS B — Non-assujetti (TEE / RNE)';
    }
}
