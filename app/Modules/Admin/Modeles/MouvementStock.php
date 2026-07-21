<?php

namespace App\Modules\Admin\Modeles;

use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MouvementStock extends Model
{
    protected $table = 'mouvements_stock';

    protected $fillable = [
        'produit_id',
        'point_de_vente_id',
        'type_mouvement',
        'sous_type',
        'point_de_vente_source_id',
        'utilisateur_id',
        'fournisseur_id',
        'client_id',
        'quantite',
        'stock_avant',
        'stock_apres',
        'reference_document',
    ];

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    public function pointDeVente(): BelongsTo
    {
        return $this->belongsTo(PointDeVente::class, 'point_de_vente_id');
    }

    public function pointDeVenteSource(): BelongsTo
    {
        return $this->belongsTo(PointDeVente::class, 'point_de_vente_source_id');
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(Utilisateur::class, 'utilisateur_id');
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }
}
