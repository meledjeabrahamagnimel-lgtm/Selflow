<?php

namespace App\Modules\Admin\Modeles;

use App\Modules\Admin\Modeles\Concerns\IdentifiantOpaque;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Un emballage prêté contre une somme qui sera rendue s'il revient.
 *
 * **La consignation reçue est une dette, non un produit.** Une caisse consignée
 * 2 000 francs gonflait le chiffre d'affaires de 2 000 francs que l'entreprise
 * devra rendre. Elle vit au passif — compte `419400` — jusqu'au retour de
 * l'emballage.
 *
 * La consignation versée à un fournisseur est l'inverse exact : une créance,
 * compte `409400`. Confondre les deux met le bilan à l'envers.
 */
class Consignation extends Model
{
    use IdentifiantOpaque;

    protected $table = 'consignations';

    protected $fillable = [
        'entreprise_id',
        'point_de_vente_id',
        'sens',
        'client_id',
        'fournisseur_id',
        'produit_id',
        'designation',
        'piece_type',
        'piece_id',
        'reference_document',
        'quantite',
        'prix_consigne',
        'montant',
        'quantite_rendue',
        'montant_rembourse',
        'boni',
        'date_consignation',
        'date_limite_retour',
        'date_cloture',
        'statut',
        'utilisateur_id',
    ];

    protected function casts(): array
    {
        return [
            'date_consignation'  => 'date',
            'date_limite_retour' => 'date',
            'date_cloture'       => 'date',
            'quantite'           => 'float',
            'quantite_rendue'    => 'float',
            'prix_consigne'      => 'float',
            'montant'            => 'float',
            'montant_rembourse'  => 'float',
            'boni'               => 'float',
        ];
    }

    /** Ce qu'on consigne à un client : une dette, compte `419400`. */
    public const AU_CLIENT = 'client';

    /** Ce qu'un fournisseur nous consigne : une créance, compte `409400`. */
    public const DU_FOURNISSEUR = 'fournisseur';

    public const EN_COURS   = 'en_cours';
    public const RENDUE     = 'rendue';
    public const NON_RENDUE = 'non_rendue';

    /** Clients, dettes pour emballages et matériels consignés. */
    public const COMPTE_DETTE = '419400';

    /** Fournisseurs, créances pour emballages et matériels à rendre. */
    public const COMPTE_CREANCE = '409400';

    /** Bonis sur reprises et cessions d'emballages. */
    public const COMPTE_BONI = '707400';

    /** Malis sur emballages. */
    public const COMPTE_MALI = '622400';

    /** Précision des quantités, alignée sur le stock. */
    public const DECIMALES = 3;

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    public function pointDeVente(): BelongsTo
    {
        return $this->belongsTo(PointDeVente::class, 'point_de_vente_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_id');
    }

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    public function piece(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'piece_type', 'piece_id');
    }

    // ─────────────────────────────────────────────────────────────────

    public function estAuClient(): bool
    {
        return $this->sens === self::AU_CLIENT;
    }

    /**
     * Ce qui n'est pas encore revenu.
     */
    public function quantiteDehors(): float
    {
        return round(max(0, $this->quantite - $this->quantite_rendue), self::DECIMALES);
    }

    /**
     * Ce qui reste dû, dans un sens ou dans l'autre.
     *
     * Au passif si c'est le client qui a consigné chez nous, à l'actif si c'est
     * nous qui avons consigné chez un fournisseur.
     */
    public function resteDu(): float
    {
        return round(max(0, $this->montant - $this->montant_rembourse - $this->boni), 2);
    }

    /**
     * Le délai de retour est-il dépassé ?
     *
     * Une consignation sans délai n'expire pas : c'est l'usage pour une
     * bouteille de gaz, qu'un ménage garde des années.
     */
    public function estEnRetard(): bool
    {
        return $this->statut === self::EN_COURS
            && $this->date_limite_retour !== null
            && $this->date_limite_retour->endOfDay()->isPast();
    }

    public function estClose(): bool
    {
        return in_array($this->statut, [self::RENDUE, self::NON_RENDUE], true);
    }

    /**
     * Le compte de contrepartie de la consignation, selon le sens.
     *
     * Une dette d'un côté, une créance de l'autre : les confondre mettrait le
     * bilan à l'envers.
     */
    public function compteDeConsignation(): string
    {
        return $this->estAuClient() ? self::COMPTE_DETTE : self::COMPTE_CREANCE;
    }

    public function scopeEnCours($query)
    {
        return $query->where('statut', self::EN_COURS);
    }

    public function scopeEnRetard($query)
    {
        return $query->where('statut', self::EN_COURS)
            ->whereNotNull('date_limite_retour')
            ->whereDate('date_limite_retour', '<', now());
    }
}
