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

        // Verrouillage des écritures sur exercice clôturé (création/modification)
        static::saving(function ($ecriture) {
            $cloture = \App\Modules\Admin\Modeles\Periode::where('entreprise_id', $ecriture->entreprise_id)
                ->where('est_cloture', true)
                ->whereDate('date_debut', '<=', $ecriture->date_ecriture)
                ->whereDate('date_fin', '>=', $ecriture->date_ecriture)
                ->exists();
            if ($cloture) {
                abort(403, "Action impossible : l'exercice comptable pour cette date d'écriture est clôturé.");
            }
        });

        // Verrouillage des écritures sur exercice clôturé (suppression)
        static::deleting(function ($ecriture) {
            $cloture = \App\Modules\Admin\Modeles\Periode::where('entreprise_id', $ecriture->entreprise_id)
                ->where('est_cloture', true)
                ->whereDate('date_debut', '<=', $ecriture->date_ecriture)
                ->whereDate('date_fin', '>=', $ecriture->date_ecriture)
                ->exists();
            if ($cloture) {
                abort(403, "Action impossible : l'exercice comptable pour cette date d'écriture est clôturé.");
            }
        });

        // Déversement vers COMPTAFLOW, en arrière-plan : voir
        // `App\Jobs\DeverserEcritureComptaflow`. L'appel partait en
        // synchrone ici même, et faisait attendre la caisse la réponse de
        // Comptaflow (jusqu'à 3 secondes) à chaque écriture.
        static::created(function ($ecriture) {
            $entreprise = $ecriture->entreprise;
            if ($entreprise && $entreprise->comptaflow_sync_status === 'active' && $entreprise->comptaflow_sync_key) {
                \App\Jobs\DeverserEcritureComptaflow::dispatch($ecriture);
            }
        });
    }

    protected $fillable = [
        'operation_id',
        'entreprise_id',
        'point_de_vente_id',
        'date_ecriture',
        'libelle',
        'reference_document',
        'code_journal',
        'compte_debit',
        'compte_credit',
        'compte_tiers',
        'debit',
        'credit',
        'lettrage_id',
        'description',
        'comptaflow_sync_status',
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

    public function operation(): BelongsTo
    {
        return $this->belongsTo(Operation::class, 'operation_id');
    }

    /**
     * Le lettrage qui rapproche cette écriture d'une autre.
     *
     * Nul tant que la pièce n'est pas soldée : c'est précisément ce qui permet
     * de répondre à « que me doit-on encore ? ».
     */
    public function lettrage()
    {
        return $this->belongsTo(Lettrage::class, 'lettrage_id');
    }
}
