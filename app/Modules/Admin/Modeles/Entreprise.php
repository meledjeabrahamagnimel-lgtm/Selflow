<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Entreprise extends Model
{
    protected $table = 'entreprises';

    protected $fillable = [
        'nom',
        'forme_juridique',
        'gerant_nom',
        'gerant_prenom',
        'gerant_fonction',
        'adresse',
        'telephone',
        'email',
        'rccm',
        'compte_contribuable',
        'ncc',
        'regime_imposition',
        'centre_impots',
        'ref_bancaire',
        'logo_path',
        'logo_fne_path',
        'quota_points_de_vente',
        'plan_abonnement',
        'secteur_activite',
        'modules_actifs',
        'comptaflow_sync_key',
        'comptaflow_sync_status',
        'comptaflow_last_sync_at',
        'comptaflow_company_id',
    ];

    protected $casts = [
        'secteur_activite' => 'array',
        'modules_actifs' => 'array',
    ];

    public function pointsDeVente(): HasMany
    {
        return $this->hasMany(PointDeVente::class, 'entreprise_id');
    }

    public function utilisateurs(): HasMany
    {
        return $this->hasMany(\App\Modules\Authentification\Modeles\Utilisateur::class, 'entreprise_id');
    }

    public function produits(): HasMany
    {
        return $this->hasMany(Produit::class, 'entreprise_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class, 'entreprise_id');
    }

    public function fournisseurs(): HasMany
    {
        return $this->hasMany(Fournisseur::class, 'entreprise_id');
    }

    public function fneCredential(): HasOne
    {
        return $this->hasOne(FneCredential::class, 'entreprise_id');
    }
}
