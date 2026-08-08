<?php

namespace App\Modules\Admin\Modeles\Referentiel;

use Illuminate\Database\Eloquent\Model;

/**
 * Référentiel de préparamétrage — catalogue livré avec l'application.
 *
 * Ces tables ne contiennent aucune donnée client : une entreprise qui souscrit
 * à un profil en reçoit une copie chez elle, qu'elle peut modifier librement
 * sans que le référentiel en soit affecté.
 */
class TypeArticle extends Model
{
    protected $table = 'referentiel_types_articles';
    protected $fillable = ['code', 'libelle', 'compte_vente', 'compte_achat',
                           'compte_stock', 'compte_variation', 'stockable'];
    protected $casts = ['stockable' => 'boolean'];

    /**
     * Un type sans compte de stock ne se compte pas : un service n'a ni
     * quantité disponible, ni seuil, ni rupture.
     */
    public function estStockable(): bool
    {
        return (bool) $this->stockable;
    }

    /**
     * Type de produit correspondant, dans le vocabulaire de `Produit`.
     *
     * Le référentiel distingue dix natures comptables ; le catalogue n'en
     * connaît que six, et ne se soucie que d'une chose : le stock se compte-t-il
     * ou non. Travaux, services, sous-traitance et financements se rejoignent
     * donc sous « service » — aucun ne se stocke.
     */
    public function typeProduit(): string
    {
        return self::CORRESPONDANCE[$this->code] ?? 'service';
    }

    /**
     * @var array<string, string>
     */
    private const CORRESPONDANCE = [
        'MARCHANDISE'      => 'marchandise',
        'PRODUIT_FINI'     => 'produit_fini',
        'MATIERE_PREMIERE' => 'matiere_premiere',
        'CONSOMMABLE'      => 'consommable_stockable',
        'AUTRES_ACHATS'    => 'consommable_non_stockable',
        'SERVICE'          => 'service',
        'TRAVAUX'          => 'service',
        'ACCESSOIRE'       => 'service',
        'SOUS_TRAITANCE'   => 'service',
        'FINANCEMENT'      => 'service',
    ];
}
