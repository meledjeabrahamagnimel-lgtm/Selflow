<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Operation extends Model
{
    protected $table = 'operations';

    protected $fillable = [
        'entreprise_id',
        'point_de_vente_id',
        'date_operation',
        'type_operation',
        'code_journal',
        'numero_saisie',
        'reference_document',
        'libelle_general',
        'solde_equilibre',
        'est_equilibree',
    ];

    protected function casts(): array
    {
        return [
            'date_operation'   => 'date',
            'solde_equilibre'  => 'decimal:2',
            'est_equilibree'   => 'boolean',
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

    public function ecritures(): HasMany
    {
        return $this->hasMany(EcritureComptable::class, 'operation_id');
    }

    /**
     * Génère le prochain numéro de saisie séquentiel pour un journal donné,
     * sur l'exercice (année) de la date d'opération fournie.
     * Format : {CODE_JOURNAL}-{ANNEE}-{SEQUENCE sur 6 chiffres}
     * ex : VTE-2026-000042
     *
     * Utilise un verrou de ligne pour éviter les collisions en cas de
     * créations concurrentes.
     */
    public static function prochainNumeroSaisie(int $entrepriseId, string $codeJournal, string $date): string
    {
        $annee = \Carbon\Carbon::parse($date)->year;
        $prefixe = $codeJournal . '-' . $annee . '-';

        return DB::transaction(function () use ($entrepriseId, $codeJournal, $prefixe) {
            $dernier = self::where('entreprise_id', $entrepriseId)
                ->where('code_journal', $codeJournal)
                ->where('numero_saisie', 'like', $prefixe . '%')
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('numero_saisie');

            $prochainNum = 1;
            if ($dernier) {
                $dernierNum = (int) substr($dernier, strlen($prefixe));
                $prochainNum = $dernierNum + 1;
            }

            return $prefixe . str_pad((string) $prochainNum, 6, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Crée une opération et retourne son id, prête à recevoir des écritures liées.
     */
    public static function creer(
        int $entrepriseId,
        ?int $pdvId,
        string $date,
        string $typeOperation,
        string $codeJournal,
        ?string $referenceDocument = null,
        ?string $libelleGeneral = null
    ): self {
        $numeroSaisie = self::prochainNumeroSaisie($entrepriseId, $codeJournal, $date);

        return self::create([
            'entreprise_id'       => $entrepriseId,
            'point_de_vente_id'   => $pdvId,
            'date_operation'      => $date,
            'type_operation'      => $typeOperation,
            'code_journal'        => $codeJournal,
            'numero_saisie'       => $numeroSaisie,
            'reference_document'  => $referenceDocument,
            'libelle_general'     => $libelleGeneral,
            'solde_equilibre'     => 0,
            'est_equilibree'      => false,
        ]);
    }

    /**
     * Recalcule et enregistre l'équilibre débit/crédit de l'opération
     * à partir de ses lignes d'écriture actuelles. À appeler après avoir
     * créé toutes les lignes de l'opération.
     */
    public function cloturerEquilibre(): void
    {
        $totaux = $this->ecritures()
            ->selectRaw('COALESCE(SUM(debit),0) as total_debit, COALESCE(SUM(credit),0) as total_credit')
            ->first();

        $solde = round((float) $totaux->total_debit - (float) $totaux->total_credit, 2);

        $this->update([
            'solde_equilibre' => $solde,
            'est_equilibree'  => abs($solde) < 0.01,
        ]);

        if (abs($solde) >= 0.01) {
            \Illuminate\Support\Facades\Log::error(
                "Opération #{$this->numero_saisie} déséquilibrée : solde = {$solde}"
            );
        }
    }
}
