<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Fournisseur extends Model
{
    protected $table = 'fournisseurs';
    protected $fillable = ['entreprise_id', 'nom', 'telephone', 'email', 'secteur', 'adresse', 'ncc', 'regime_imposition', 'rccm', 'compte_comptable', 'numero_tiers', 'source', 'numero_original'];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    public function achats(): HasMany
    {
        return $this->hasMany(Achat::class, 'fournisseur_id');
    }

    public static function obtenirFournisseursPrioritaires($entrepriseId)
    {
        $hasComptaflow = self::where('entreprise_id', $entrepriseId)->where('source', 'comptaflow')->exists();
        if ($hasComptaflow) {
            return self::where('entreprise_id', $entrepriseId)->where('source', 'comptaflow')->orderBy('nom')->get();
        }
        return self::where('entreprise_id', $entrepriseId)->orderBy('nom')->get();
    }
}
