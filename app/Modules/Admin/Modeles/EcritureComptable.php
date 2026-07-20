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

        // Déversement en temps réel vers COMPTAFLOW
        static::created(function ($ecriture) {
            $entreprise = $ecriture->entreprise;
            if ($entreprise && $entreprise->comptaflow_sync_status === 'active' && $entreprise->comptaflow_sync_key) {
                try {
                    $comptaflowUrl = config('selflow.comptaflow_api_url', 'http://127.0.0.1:8000');
                    $secret = config('selflow.comptaflow_api_secret', 'selflow-comptaflow-secret-2026');

                    $dateStr = $ecriture->date_ecriture instanceof \Carbon\Carbon 
                        ? $ecriture->date_ecriture->toDateString() 
                        : (is_string($ecriture->date_ecriture) ? $ecriture->date_ecriture : \Carbon\Carbon::parse($ecriture->date_ecriture)->toDateString());

                    $ecritureData = [
                        'date_ecriture'      => $dateStr,
                        'libelle'            => $ecriture->libelle,
                        'reference_document' => $ecriture->reference_document,
                        'code_journal'       => $ecriture->code_journal,
                        'compte_debit'       => $ecriture->compte_debit,
                        'compte_credit'      => $ecriture->compte_credit,
                        'debit'              => (float) $ecriture->debit,
                        'credit'             => (float) $ecriture->credit,
                    ];

                    $response = \Illuminate\Support\Facades\Http::timeout(3)->post($comptaflowUrl . '/api/external/ecritures/deverser', [
                        'secret'             => $secret,
                        'selflow_company_id' => $entreprise->id,
                        'ecritures'          => [$ecritureData],
                    ]);

                    if ($response->successful() && $response->json('success')) {
                        \Illuminate\Support\Facades\DB::table('ecritures_comptables')
                            ->where('id', $ecriture->id)
                            ->update(['comptaflow_sync_status' => 'synced']);
                    } else {
                        \Illuminate\Support\Facades\DB::table('ecritures_comptables')
                            ->where('id', $ecriture->id)
                            ->update(['comptaflow_sync_status' => 'failed']);
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\DB::table('ecritures_comptables')
                        ->where('id', $ecriture->id)
                        ->update(['comptaflow_sync_status' => 'failed']);
                    \Illuminate\Support\Facades\Log::warning('Real-time sync to COMPTAFLOW failed, scheduled retry: ' . $e->getMessage());
                }
            }
        });
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
}
