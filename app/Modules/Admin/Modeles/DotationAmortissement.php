<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne du plan d'amortissement : ce qu'un exercice porte en dotation.
 *
 * Le plan se calcule d'avance, à la mise en service. C'est lui que le comptable
 * présente au contrôle, et c'est ce qui permet de savoir, avant de la passer,
 * ce que la dotation de l'année vaudra.
 *
 * `comptabilise_at` empêche la dotation de passer deux fois. Une dotation
 * passée deux fois double la charge et amortit le bien au double de sa valeur —
 * l'erreur ne se voit qu'au bilan, l'année suivante.
 */
class DotationAmortissement extends Model
{
    protected $table = 'dotations_amortissement';

    protected $fillable = [
        'immobilisation_id',
        'entreprise_id',
        'annee',
        'date_debut',
        'date_fin',
        'base_amortissable',
        'dotation',
        'cumul',
        'valeur_nette',
        'comptabilise_at',
        'operation_id',
    ];

    protected function casts(): array
    {
        return [
            'date_debut'        => 'date',
            'date_fin'          => 'date',
            'base_amortissable' => 'float',
            'dotation'          => 'float',
            'cumul'             => 'float',
            'valeur_nette'      => 'float',
            'comptabilise_at'   => 'datetime',
            'annee'             => 'integer',
        ];
    }

    public function immobilisation(): BelongsTo
    {
        return $this->belongsTo(Immobilisation::class, 'immobilisation_id');
    }

    public function estComptabilisee(): bool
    {
        return $this->comptabilise_at !== null;
    }
}
