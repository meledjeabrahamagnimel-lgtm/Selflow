<?php

namespace App\Modules\Admin\Modeles;

use App\Modules\Admin\Modeles\Concerns\IdentifiantOpaque;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Periode extends Model
{
    use IdentifiantOpaque;

    protected $table = 'periodes';

    protected $fillable = [
        'entreprise_id',
        'nom',
        'date_debut',
        'date_fin',
        'est_active',
        'est_cloture',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin'   => 'date',
        'est_active' => 'boolean',
        'est_cloture' => 'boolean',
    ];

    public function estCloture(): bool
    {
        return (bool)$this->est_cloture;
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }
}
