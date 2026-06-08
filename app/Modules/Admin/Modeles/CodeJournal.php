<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CodeJournal extends Model
{
    protected $table = 'codes_journaux';
    protected $fillable = ['entreprise_id', 'type', 'code', 'intitule', 'compte'];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }
}
