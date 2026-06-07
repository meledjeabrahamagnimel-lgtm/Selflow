<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TresorerieJournal extends Model
{
    protected $table = 'tresorerie_journal';
    protected $fillable = [
        'point_de_vente_id', 'date_operation', 'type_operation',
        'libelle', 'mode_paiement', 'montant_entree', 'montant_sortie',
        'solde_resultat', 'reference_document',
    ];

    protected function casts(): array
    {
        return [
            'date_operation' => 'date',
            'montant_entree' => 'decimal:2',
            'montant_sortie' => 'decimal:2',
            'solde_resultat' => 'decimal:2',
        ];
    }

    public function pointDeVente(): BelongsTo
    {
        return $this->belongsTo(PointDeVente::class, 'point_de_vente_id');
    }
}
