<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un bien que l'entreprise possède et qui sert plus d'un exercice.
 *
 * Rien n'existait : un camion, un four, un ordinateur passaient en charge de
 * l'exercice, ou ne passaient nulle part. Le bilan ne portait pas trace de
 * l'actif immobilisé, le résultat de l'année d'achat était écrasé, et **la
 * charge d'amortissement, déductible, n'était pas prise** — une entreprise qui
 * n'amortit pas paie l'impôt sur un bénéfice qu'elle n'a pas.
 */
class Immobilisation extends Model
{
    protected $table = 'immobilisations';

    protected $fillable = [
        'entreprise_id',
        'point_de_vente_id',
        'code',
        'libelle',
        'description',
        'compte_immobilisation',
        'compte_amortissement',
        'compte_dotation',
        'date_acquisition',
        'date_mise_en_service',
        'valeur_acquisition',
        'valeur_residuelle',
        'duree_mois',
        'mode',
        'statut',
        'date_sortie',
        'prix_cession',
        'fournisseur_id',
        'utilisateur_id',
    ];

    protected function casts(): array
    {
        return [
            'date_acquisition'     => 'date',
            'date_mise_en_service' => 'date',
            'date_sortie'          => 'date',
            'valeur_acquisition'   => 'float',
            'valeur_residuelle'    => 'float',
            'prix_cession'         => 'float',
            'duree_mois'           => 'integer',
        ];
    }

    /** Le bien sert encore. */
    public const EN_SERVICE = 'en_service';

    /** Le bien a été vendu. */
    public const CEDE = 'cede';

    /** Le bien a été mis au rebut : sorti sans contrepartie. */
    public const REBUTE = 'rebute';

    /**
     * Le seul mode calculé.
     *
     * Le dégressif suppose des coefficients fixés par un texte que le dépôt ne
     * contient pas. Les inventer donnerait un plan faux que rien ne
     * signalerait — c'est exactement le genre d'écart qui a produit un timbre
     * de quittance à 1,5 %, taux qui ne figure dans aucun texte.
     */
    public const LINEAIRE = 'lineaire';

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    public function pointDeVente(): BelongsTo
    {
        return $this->belongsTo(PointDeVente::class, 'point_de_vente_id');
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_id');
    }

    /** Le plan d'amortissement : une ligne par exercice. */
    public function dotations(): HasMany
    {
        return $this->hasMany(DotationAmortissement::class, 'immobilisation_id')->orderBy('annee');
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Ce qui s'amortit : la valeur d'acquisition moins ce que le bien vaudra au
     * terme.
     *
     * La valeur résiduelle est nulle le plus souvent ; un véhicule revendu a
     * pourtant une valeur, et l'amortir en entier ferait apparaître une
     * plus-value fictive à la cession.
     */
    public function baseAmortissable(): float
    {
        return round(max(0, $this->valeur_acquisition - $this->valeur_residuelle), 2);
    }

    /**
     * Ce qui a déjà été passé en amortissement, jusqu'à une année comprise.
     */
    public function cumulAmorti(?int $jusquA = null): float
    {
        return round((float) $this->dotations()
            ->whereNotNull('comptabilise_at')
            ->when($jusquA, fn ($q) => $q->where('annee', '<=', $jusquA))
            ->sum('dotation'), 2);
    }

    /**
     * La valeur comptable nette : ce que le bilan porte encore.
     *
     * C'est elle qui part en charge à la cession, sur le compte 81x.
     */
    public function valeurNette(?int $jusquA = null): float
    {
        return round($this->valeur_acquisition - $this->cumulAmorti($jusquA), 2);
    }

    public function estSorti(): bool
    {
        return in_array($this->statut, [self::CEDE, self::REBUTE], true);
    }

    /**
     * Le plan est-il déjà entamé en comptabilité ?
     *
     * Une fiche dont une dotation est passée ne se retouche plus : changer la
     * durée ou la valeur d'un bien à moitié amorti rendrait le plan
     * incohérent avec les écritures déjà au grand livre.
     */
    public function estEngage(): bool
    {
        return $this->dotations()->whereNotNull('comptabilise_at')->exists();
    }
}
