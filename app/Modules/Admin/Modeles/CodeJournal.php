<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodeJournal extends Model
{
    protected $table = 'codes_journaux';
    protected $fillable = ['entreprise_id', 'type', 'code', 'numero_original', 'intitule', 'compte', 'source', 'archive_le', 'systeme'];

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

    /**
     * Journaux proposés à la saisie : ni archivés, ni pilotés par le système.
     *
     * Le report à nouveau est écrit par la clôture ; le proposer dans une liste
     * de saisie inviterait à passer une écriture à la main dans un journal que
     * l'application recalcule.
     */
    public function scopeSaisissables($query)
    {
        return $query->whereNull('archive_le')->where('systeme', false);
    }

    public function estArchive(): bool
    {
        return $this->archive_le !== null;
    }

    protected function casts(): array
    {
        return ['systeme' => 'boolean', 'archive_le' => 'datetime'];
    }
}
