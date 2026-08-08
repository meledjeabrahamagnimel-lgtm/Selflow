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
        'archive_le',
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

    /**
     * Ce qui ne sert pas s'archive, il ne se supprime pas : un compte ou un
     * journal efface apres avoir servi laisserait des ecritures orphelines.
     */
    public function scopeActifs($query)
    {
        return $query->whereNull('archive_le');
    }

    public function scopeArchives($query)
    {
        return $query->whereNotNull('archive_le');
    }

    public function estArchive(): bool
    {
        return $this->archive_le !== null;
    }
}
