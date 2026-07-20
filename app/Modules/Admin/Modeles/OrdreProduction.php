<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdreProduction extends Model
{
    protected $table = 'ordres_production';

    protected $fillable = [
        'entreprise_id',
        'point_de_vente_id',
        'produit_fini_id',
        'code_ordre',
        'quantite_cible',
        'statut',
        'date_production',
    ];

    protected $casts = [
        'quantite_cible' => 'decimal:4',
        'date_production' => 'date',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    public function pointDeVente(): BelongsTo
    {
        return $this->belongsTo(PointDeVente::class, 'point_de_vente_id');
    }

    public function produitFini(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_fini_id');
    }

    /**
     * Générer un code unique pour un nouvel ordre de production.
     */
    public static function genererCode(int $entrepriseId): string
    {
        $annee = now()->year;
        $compteur = self::where('entreprise_id', $entrepriseId)
            ->whereYear('created_at', $annee)
            ->count() + 1;

        return 'OP-' . $annee . '-' . str_pad($compteur, 4, '0', STR_PAD_LEFT);
    }
}
