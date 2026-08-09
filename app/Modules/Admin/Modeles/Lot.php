<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un arrivage : un numéro, une date de péremption, une quantité, sur un site.
 *
 * `produits.date_peremption` portait **une seule date par article**. Une
 * pharmacie qui reçoit trois arrivages de paracétamol n'en enregistrait qu'un :
 * la saisie du troisième écrasait les deux premiers, et les boîtes de mars
 * restaient en rayon sans que rien ne les signale.
 */
class Lot extends Model
{
    protected $table = 'lots';

    protected $fillable = [
        'entreprise_id',
        'produit_id',
        'point_de_vente_id',
        'numero_lot',
        'date_peremption',
        'date_fabrication',
        'quantite',
        'cout_unitaire',
        'fournisseur_id',
    ];

    protected function casts(): array
    {
        return [
            'date_peremption'  => 'date',
            'date_fabrication' => 'date',
            // Comme le stock : le cast `decimal` de Laravel rend des chaînes,
            // qui ne s'additionnent pas dans un `array_sum` et se comparent
            // mal. La colonne est un `decimal(15,3)` en base, elle fait foi.
            'quantite'         => 'float',
            'cout_unitaire'    => 'float',
        ];
    }

    /** Précision des quantités, alignée sur `Stock::DECIMALES`. */
    public const DECIMALES = 3;

    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    public function pointDeVente(): BelongsTo
    {
        return $this->belongsTo(PointDeVente::class, 'point_de_vente_id');
    }

    public function fournisseur(): BelongsTo
    {
        return $this->belongsTo(Fournisseur::class, 'fournisseur_id');
    }

    /**
     * Les mouvements qui ont consommé ou alimenté ce lot.
     *
     * C'est ce qui permet de répondre à un rappel du fabricant : le lot dit
     * quelles sorties l'ont emporté, et chaque sortie dit chez quel client.
     */
    public function mouvements(): HasMany
    {
        return $this->hasMany(MouvementLot::class, 'lot_id');
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Ce lot a-t-il passé sa date ?
     *
     * Le jour de péremption est compris : un lot au 30 se vend le 30. C'est ce
     * que dit la mention « à consommer jusqu'au ».
     */
    public function estPerime(): bool
    {
        return $this->date_peremption !== null && $this->date_peremption->endOfDay()->isPast();
    }

    /**
     * Ce lot périme-t-il dans les `$jours` qui viennent ?
     *
     * **Le calcul d'origine était faux**, sur `Produit::bientotPerime()` :
     *
     *     $this->date_peremption->diffInDays(now()) <= $joursAlerte
     *
     * `diffInDays()` rend une différence **signée** — une date future donne un
     * nombre négatif —, si bien que la comparaison était vraie pour *toutes*
     * les dates à venir, quelle que soit leur distance. L'écran des rebuts
     * annonçait donc le catalogue entier comme proche de la péremption, et une
     * alerte qui crie tout le temps ne se lit plus.
     */
    public function bientotPerime(?int $jours = null): bool
    {
        if ($this->date_peremption === null || $this->estPerime()) {
            return false;
        }

        $preavis = $jours ?? (int) ($this->produit?->preavis_peremption ?? 30);

        return now()->startOfDay()->diffInDays($this->date_peremption->startOfDay(), false) <= $preavis;
    }

    /**
     * Jours restants avant la date, négatif si elle est passée.
     */
    public function joursRestants(): ?int
    {
        return $this->date_peremption === null
            ? null
            : (int) now()->startOfDay()->diffInDays($this->date_peremption->startOfDay(), false);
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Les lots qui portent encore de la marchandise.
     */
    public function scopeNonVides($query)
    {
        return $query->where('quantite', '>', 0);
    }

    /**
     * L'ordre FEFO — *First Expired, First Out*.
     *
     * On sert d'abord ce qui périme le plus tôt, et non ce qui est arrivé le
     * premier. Les deux coïncident souvent, jamais toujours : un arrivage
     * récent à date courte doit partir avant un arrivage ancien à date longue,
     * et le FIFO laisserait périmer le premier.
     *
     * Les lots sans date passent en dernier : ils ne périment pas, rien ne
     * presse de les sortir.
     */
    public function scopeFefo($query)
    {
        return $query
            ->orderByRaw('CASE WHEN date_peremption IS NULL THEN 1 ELSE 0 END')
            ->orderBy('date_peremption')
            ->orderBy('id');
    }

    public function scopePerimes($query)
    {
        return $query->whereNotNull('date_peremption')->whereDate('date_peremption', '<', now());
    }
}
