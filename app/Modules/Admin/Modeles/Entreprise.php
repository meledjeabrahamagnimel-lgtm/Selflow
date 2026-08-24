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
        // La forme des numeros de tiers : 411001 ou 411KONE. Chaque cabinet
        // comptable a la sienne.
        'numerotation_tiers',
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
     * Les régimes d'imposition, et leur libellé.
     *
     * La liste vivait en dur dans **quatre écrans**, avec quatre contenus
     * différents — et le régime n'est pas une étiquette : `deduireCodeTva()` le
     * compare aux régimes d'exonération légale pour choisir entre TVAC et TVAD.
     * Un sigle qui n'est pas celui du référentiel ne correspond à rien.
     *
     * L'écart le plus coûteux était celui de l'écran du superadministrateur :
     * il proposait « Réel Normal », « Bénéfice Forfaitaire », « Exonéré »… des
     * intitulés que rien ne reconnaît. Une entreprise créée par cette voie et
     * enregistrée « Exonéré » voyait ses lignes à 0 % partir en exonération
     * conventionnelle, quel que soit son régime réel.
     *
     * `REGIMES_EXONERATION_LEGALE`, sur `Produit`, reste gelé : cette liste-ci
     * n'y touche pas, elle fait seulement en sorte que les écrans proposent des
     * valeurs qu'il puisse reconnaître.
     */
    public const REGIMES_IMPOSITION = [
        'TEE' => "TEE — Taxe d'État de l'Entreprenant",
        'TCE' => "TCE — Taxe Communale de l'Entreprenant",
        'RME' => 'RME — Régime des Microentreprises',
        'RNE' => "RNE — Régime du Négoce et de l'Exportation",
        'RSI' => "RSI — Régime Simplifié d'Imposition",
        'RNI' => "RNI — Régime Normal d'Imposition",
    ];

    /**
     * Ce que chaque régime veut dire, en une phrase.
     *
     * L'écran d'inscription portait ces définitions dans son JavaScript, pour
     * quatre régimes sur six. Elles vivent ici pour que les deux écrans de
     * création les affichent, et n'en affichent qu'une version.
     */
    public const REGIMES_NOTICES = [
        'TEE' => "Taxe d'État de l'Entreprenant : pour les très petites entreprises et les auto-entrepreneurs. Taux fixe annuel, pas d'obligation de TVA.",
        'TCE' => "Taxe Communale de l'Entreprenant : la part communale du régime de l'entreprenant, pour les plus petites activités.",
        'RME' => "Régime des Microentreprises : impôt assis sur le chiffre d'affaires, comptabilité allégée.",
        'RNE' => "Régime du Négoce et de l'Exportation : impôt sur le bénéfice, comptabilité simplifiée.",
        'RSI' => "Régime Simplifié d'Imposition : pour les entreprises moyennes. TVA sur option, comptabilité standard.",
        'RNI' => "Régime Normal d'Imposition : TVA obligatoire, comptabilité complète SYSCOHADA.",
    ];

    /**
     * Les régimes qu'un formulaire peut accepter pour CETTE entreprise : le
     * référentiel, plus ce qu'elle porte déjà.
     *
     * Sans ce second terme, une entreprise enregistrée sous l'ancienne liste
     * — « Réel Normal », par exemple — ne pourrait plus enregistrer aucune
     * modification, même sans toucher à son régime. Même raisonnement que
     * `Categorie::domainesAcceptesPour()`.
     *
     * @return array<int, string>
     */
    public static function regimesAcceptesPour(?self $entreprise = null): array
    {
        $codes = array_keys(self::REGIMES_IMPOSITION);

        if ($entreprise?->regime_imposition) {
            $codes[] = $entreprise->regime_imposition;
        }

        return array_values(array_unique($codes));
    }

    /**
     * Les informations que la plateforme FNE exige de l'entreprise.
     *
     * **C'est tout ce que l'entreprise a à fournir.** Les clés d'API et la
     * configuration de la plateforme relèvent du superadministrateur seul ;
     * l'entreprise, elle, déclare si elle a déjà un compte et reporte les
     * informations de son espace — ou rassemble celles qu'il faut pour
     * l'ouvrir.
     *
     * La liste vit ici, et non dans une vue, parce que **deux écrans la
     * lisent** : les paramètres de l'entreprise, qui la font remplir, et le
     * tableau FNE du superadministrateur, qui doit voir d'un coup d'œil à qui
     * il manque quoi avant de configurer une clé. Écrite deux fois, elle aurait
     * divergé au premier champ ajouté.
     *
     * @return array<int, array{champ: string, valeur: ?string, note: string}>
     */
    public function informationsFne(): array
    {
        return [
            ['champ' => 'Raison sociale',
             'valeur' => $this->nom !== '[PENDING_ONBOARDING]' ? $this->nom : null,
             'note' => 'Transmise comme « établissement » à chaque certification.'],
            ['champ' => 'NCC — Numéro de Compte Contribuable',
             'valeur' => $this->ncc,
             'note' => 'Identifie l\'entreprise auprès de la plateforme. Sans lui, rien n\'est certifié.'],
            ['champ' => 'Régime d\'imposition',
             'valeur' => $this->regime_imposition,
             'note' => 'Détermine le code de TVA appliqué aux articles exonérés (TVAC ou TVAD).'],
            ['champ' => 'RCCM',
             'valeur' => $this->rccm,
             'note' => 'Registre du Commerce et du Crédit Mobilier, exigé à l\'inscription.'],
            ['champ' => 'Centre des impôts',
             'valeur' => $this->centre_impots,
             'note' => 'Celui dont dépend l\'entreprise ; figure sur vos documents fiscaux.'],
            ['champ' => 'Adresse de l\'établissement',
             'valeur' => $this->adresse,
             'note' => 'Adresse physique du siège, telle que déclarée à la DGI.'],
            ['champ' => 'Téléphone',
             'valeur' => $this->telephone,
             'note' => 'Contact de l\'entreprise.'],
            ['champ' => 'Adresse e-mail',
             'valeur' => $this->email,
             'note' => 'Reçoit les notifications de la plateforme à chaque facture émise.'],
            ['champ' => 'Gérant : nom, prénom et fonction',
             'valeur' => trim(($this->gerant_nom ?? '') . ' ' . ($this->gerant_prenom ?? '')) ?: null,
             'note' => 'Représentant légal déclaré.'],
            ['champ' => 'Points de vente',
             'valeur' => $this->pointsDeVente()->count() > 0
                 ? $this->pointsDeVente()->count() . ' déclaré(s)'
                 : null,
             'note' => 'Leur nom doit être identique des deux côtés : la FNE refuse une facture dont le point de vente lui est inconnu.'],
        ];
    }

    /** Combien de ces informations manquent encore. */
    public function informationsFneManquantes(): int
    {
        return collect($this->informationsFne())
            ->filter(fn ($i) => blank($i['valeur']))
            ->count();
    }

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

    /**
     * Le nom que l'utilisateur lit dans sa barre latérale.
     *
     * Les écrans de configuration fabriquaient le leur à partir du code :
     * `ucfirst(str_replace('_', ' ', $module))`. Cela donnait « Comptabilite »
     * sans accent, « Points de vente » par chance, et « Fne » pour la section
     * que le menu appelle « Fiscalité & DGI ». L'utilisateur devait deviner que
     * la case qu'il cochait commandait la section qu'il voyait.
     *
     * Cette liste est la copie de celle des `nav-section` du gabarit. Elles
     * doivent rester d'accord ; une épreuve le vérifie.
     */
    public const LIBELLES_MODULES = [
        'principal'       => 'Tableau de bord',
        'ventes'          => 'Ventes',
        'achats'          => 'Achats',
        'stock'           => 'Stock',
        'production'      => 'Production',
        'chantiers'       => 'Chantiers',
        'cycles'          => 'Cycles agricoles',
        'comptabilite'    => 'Comptabilité',
        'points_de_vente' => 'Points de vente',
        'produits'        => 'Produits',
        'tiers'           => 'Tiers',
        'rapports'        => 'Rapports',
        'b2b'             => 'B2B',
        'fne'             => 'Fiscalité & DGI',
    ];

    public static function libelleModule(string $module): string
    {
        return self::LIBELLES_MODULES[$module] ?? ucfirst(str_replace('_', ' ', $module));
    }
}
