<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodeJournal extends Model
{
    protected $table = 'codes_journaux';
    protected $fillable = ['entreprise_id', 'type', 'code', 'numero_original', 'intitule', 'compte', 'source'];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    public static function obtenirJournauxPrioritaires($entrepriseId)
    {
        $hasComptaflow = self::where('entreprise_id', $entrepriseId)->where('source', 'comptaflow')->exists();
        if ($hasComptaflow) {
            return self::where('entreprise_id', $entrepriseId)->where('source', 'comptaflow')->orderBy('code')->get();
        }
        return self::where('entreprise_id', $entrepriseId)->orderBy('code')->get();
    }
}
