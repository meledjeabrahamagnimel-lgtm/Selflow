<?php

namespace App\Modules\Admin\Modeles;

use App\Modules\Admin\Modeles\Concerns\IdentifiantOpaque;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Entreprise extends Model
{
    use IdentifiantOpaque;

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
        'modules_autorises',
        'souscription_etape',
        'souscription_terminee_le',
        'activite_autre',
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
        // null tant que la question n'a pas ete posee (voir migration).
        'possede_compte_fne',
        // Certifier des l'emission, ou a la main apres verification. Separes :
        // une boutique peut vouloir verifier ses factures et laisser partir ses
        // tickets de caisse tout seuls.
        'normalisation_auto_factures',
        'normalisation_auto_recus',
        'timbre_quittance',
        'bapa',
        'pied_de_page_facture',
        'facture_autres_mentions',
    ];

    protected $casts = [
        'secteur_activite'   => 'array',
        'modules_actifs'     => 'array',
        'modules_autorises'  => 'array',
        'souscription_terminee_le' => 'datetime',
        'timbre_quittance'   => 'boolean',
        'bapa'               => 'boolean',
        'sticker_solde_alerte' => 'integer',
        'fne_sticker_balance' => 'integer',
        'fne_solde_provision' => 'decimal:2',
        'fne_solde_maj_at'    => 'datetime',
        'possede_compte_fne'  => 'boolean',
        'normalisation_auto_factures' => 'boolean',
        'normalisation_auto_recus'    => 'boolean',
    ];

    /**
     * Cette pièce doit-elle être certifiée dès son émission ?
     *
     * Le réglage est distinct pour la facture et pour le reçu. Le défaut est
     * l'automatique, qui était le seul comportement possible jusqu'ici.
     */
    public function normaliseAutomatiquement(\App\Modules\Admin\Modeles\Vente $vente): bool
    {
        $colonne = $vente->estRecu()
            ? 'normalisation_auto_recus'
            : 'normalisation_auto_factures';

        // `null` sur une base dont la migration vient de passer : on retient le
        // comportement d'avant, pour ne rien changer sans qu'on l'ait demandé.
        return $this->{$colonne} ?? true;
    }

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
            // Le compte contribuable (CC) a été retiré des paramètres : il
            // désignait le même numéro que le NCC, saisi deux fois pour rien.
            // C'est donc le NCC qui conditionne désormais la complétude.
            && !empty($this->ncc)
            && !empty($this->gerant_fonction)
            && is_array($this->secteur_activite)
            && count($this->secteur_activite) > 0;
    }

    /**
     * Profils d'activité auxquels cette entreprise a souscrit.
     *
     * Une activité mixte en cumule plusieurs : une quincaillerie qui livre des
     * chantiers souscrit au profil commerce et au profil BTP.
     */
    public function profils(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Modules\Admin\Modeles\Referentiel\Profil::class,
            'entreprise_profils',
            'entreprise_id',
            'profil_id'
        )->withPivot(['familles_creees', 'articles_crees', 'souscrit_le'])->withTimestamps();
    }

    /**
     * Modules que l'entreprise a le droit d'activer.
     *
     * C'est le superadmin qui en décide, et il ouvre tout par défaut : une
     * entreprise sans restriction explicite peut tout activer.
     */
    public function modulesAutorises(): array
    {
        $autorises = $this->modules_autorises;

        if (is_string($autorises)) {
            $autorises = json_decode($autorises, true);
        }

        return is_array($autorises) && $autorises !== [] ? $autorises : self::TOUS_LES_MODULES;
    }

    /**
     * Un module ne peut être actif que s'il est autorisé.
     *
     * Les deux notions vivaient dans un seul tableau : personne ne savait si un
     * module absent venait d'un abonnement restreint ou d'une préférence.
     */
    public function moduleEstActif(string $module): bool
    {
        $actifs = $this->modules_actifs;

        if (is_string($actifs)) {
            $actifs = json_decode($actifs, true);
        }

        if (!is_array($actifs) || $actifs === []) {
            $actifs = $this->modulesAutorises();
        }

        return in_array($module, $actifs, true)
            && in_array($module, $this->modulesAutorises(), true);
    }

    /**
     * Ce que tout le monde reçoit, quel que soit le métier.
     *
     * Cette liste vivait **en double** — dans `SouscriptionControleur` pour
     * afficher les cases à cocher, et dans `SouscriptionProfilService` pour
     * écrire `modules_actifs`. Les deux copies avaient dérivé : `points_de_vente`
     * manquait aux deux, et la section disparaissait de la barre latérale
     * sitôt la souscription enregistrée, sans que rien ne l'explique. Une seule
     * liste, désormais.
     */
    public const MODULES_SOCLE = [
        'principal', 'points_de_vente', 'ventes', 'achats',
        'tiers', 'produits', 'rapports', 'comptabilite',
    ];

    /**
     * Ceux qu'on ne décoche pas.
     *
     * `principal` porte le socle. `points_de_vente` porte les sites, **le
     * personnel et les habilitations** : le décocher retirerait à
     * l'administrateur l'écran où il gère ses propres utilisateurs et leurs
     * droits. Personne ne fait ce choix en connaissance de cause.
     */
    public const MODULES_STRUCTURELS = ['principal', 'points_de_vente'];

    public const TOUS_LES_MODULES = [
        'principal', 'ventes', 'achats', 'stock', 'production', 'chantiers',
        'cycles', 'comptabilite', 'points_de_vente', 'produits', 'tiers',
        'rapports', 'b2b', 'fne',
    ];
}
