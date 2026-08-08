<?php

namespace App\Modules\Admin\Modeles;

use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un lettrage : le lien entre une facture et le règlement qui la solde.
 *
 * Les écritures qui partagent un code sont lettrées ensemble. La structure
 * reprend celle de Comptaflow, pour que le déversement n'ait rien à traduire.
 */
class Lettrage extends Model
{
    protected $table = 'lettrages';

    protected $fillable = [
        'entreprise_id',
        'code',
        'date_lettrage',
        'utilisateur_id',
    ];

    protected function casts(): array
    {
        return ['date_lettrage' => 'date'];
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
    }

    public function ecritures(): HasMany
    {
        return $this->hasMany(EcritureComptable::class, 'lettrage_id');
    }
}
