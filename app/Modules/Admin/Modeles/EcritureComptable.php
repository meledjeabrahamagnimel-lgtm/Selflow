<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EcritureComptable extends Model
{
    protected $table = 'ecritures_comptables';

    protected static function booted()
    {
        static::addGlobalScope(new \App\Modules\Admin\Scopes\PeriodeScope('date_ecriture'));
    }

    protected $fillable = [
        'entreprise_id',
        'point_de_vente_id',
        'date_ecriture',
        'libelle',
        'reference_document',
        'code_journal',
        'compte_debit',
        'compte_credit',
        'debit',
        'credit',
    ];

    protected function casts(): array
    {
        return [
            'date_ecriture' => 'date',
            'debit'         => 'decimal:2',
            'credit'        => 'decimal:2',
        ];
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    public function pointDeVente(): BelongsTo
    {
        return $this->belongsTo(PointDeVente::class, 'point_de_vente_id');
    }
}
