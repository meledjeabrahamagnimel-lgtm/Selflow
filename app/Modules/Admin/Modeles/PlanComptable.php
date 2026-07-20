<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;

class PlanComptable extends Model
{
    protected $table = 'plan_comptable';

    protected $fillable = [
        'entreprise_id',
        'numero',
        'numero_original',
        'libelle',
        'source',
    ];

    public static function obtenirComptesPrioritaires($entrepriseId)
    {
        $hasComptaflow = self::where('entreprise_id', $entrepriseId)->where('source', 'comptaflow')->exists();
        if ($hasComptaflow) {
            return self::where('entreprise_id', $entrepriseId)->where('source', 'comptaflow')->orderBy('numero')->get();
        }
        return self::where(function($q) use ($entrepriseId) {
            $q->whereNull('entreprise_id')
              ->orWhere('entreprise_id', $entrepriseId);
        })->orderBy('numero')->get();
    }
}
