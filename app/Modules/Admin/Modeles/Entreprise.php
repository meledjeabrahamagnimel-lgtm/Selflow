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
        // Champs DGI / Fiscal
        'idu',
        'reference_cadastrale',
        'proprietaire_local',
        'commune',
        'quartier',
        'sticker_solde_alerte',
        'fne_sticker_balance',
        // Mode de facturation constaté chez la DGI ('stickers' | 'provision')
        // et solde correspondant. Voir FneService::enregistrerSoldeFne().
        'fne_mode_facturation',
        'fne_solde_provision',
        'fne_solde_maj_at',
        'timbre_quittance',
        'bapa',
        'pied_de_page_facture',
        'facture_autres_mentions',
    ];

    protected $casts = [
        'secteur_activite'   => 'array',
        'modules_actifs'     => 'array',
        'timbre_quittance'   => 'boolean',
        'bapa'               => 'boolean',
        'sticker_solde_alerte' => 'integer',
        'fne_sticker_balance' => 'integer',
        'fne_solde_provision' => 'decimal:2',
        'fne_solde_maj_at'    => 'datetime',
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

    /**
     * Vérifie si toutes les informations requises pour l'inscription complète sont présentes.
     */
    public function estInscriptionComplete(): bool
    {
        // Nom ne doit pas être temporaire
        if ($this->nom === '[PENDING_ONBOARDING]') {
            return false;
        }

        return !empty($this->regime_imposition)
            && !empty($this->adresse)
            && !empty($this->rccm)
            && !empty($this->compte_contribuable)
            && !empty($this->gerant_fonction)
            && is_array($this->secteur_activite)
            && count($this->secteur_activite) > 0;
    }
}

