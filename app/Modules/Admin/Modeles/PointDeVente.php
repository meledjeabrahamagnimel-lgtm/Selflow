<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PointDeVente extends Model
{
    protected $table = 'points_de_vente';

    protected $fillable = [
        'entreprise_id',
        'nom',
        'ville',
        'commune',
        'responsable',
        'telephone',
        'statut',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    public function ventes(): HasMany
    {
        return $this->hasMany(Vente::class, 'point_de_vente_id');
    }

    public function achats(): HasMany
    {
        return $this->hasMany(Achat::class, 'point_de_vente_id');
    }

    public function mouvementsStock(): HasMany
    {
        return $this->hasMany(MouvementStock::class, 'point_de_vente_id');
    }

    public function tresorerieJournal(): HasMany
    {
        return $this->hasMany(TresorerieJournal::class, 'point_de_vente_id');
    }

    public function utilisateurs(): HasMany
    {
        return $this->hasMany(\App\Modules\Authentification\Modeles\Utilisateur::class, 'point_de_vente_id');
    }
}
