<?php

namespace App\Modules\Admin\Modeles;

use App\Modules\Admin\Modeles\Concerns\IdentifiantOpaque;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use IdentifiantOpaque;

    protected $table = 'clients';
    protected $fillable = ['entreprise_id', 'type_facturation', 'nom', 'telephone', 'email', 'adresse', 'ncc', 'regime_imposition', 'rccm', 'compte_comptable', 'numero_tiers', 'source', 'numero_original'];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    public function ventes(): HasMany
    {
        return $this->hasMany(Vente::class, 'client_id');
    }

    /** Le numéro de tiers du client de passage, chez toutes les entreprises. */
    public const NUMERO_DIVERS = '411DIVERS';

    /**
     * Le client de passage.
     *
     * Une vente sans client nommé laissait `compte_tiers` vide : tout se
     * rangeait sur le collectif `411000`, et le grand livre ne distinguait
     * plus les ventes de comptoir des créances d'un client identifié. Une
     * fiche unique par entreprise suffit à les séparer — inutile d'en créer
     * une par ticket de caisse.
     *
     * Elle est posée à la souscription, mais se crée aussi à la demande : les
     * entreprises déjà en service n'ont pas repassé par le parcours.
     */
    public static function divers(Entreprise $entreprise): self
    {
        return self::firstOrCreate(
            ['entreprise_id' => $entreprise->id, 'numero_tiers' => self::NUMERO_DIVERS],
            [
                'nom'              => 'Client divers',
                'type_facturation' => 'B2C',
                'compte_comptable' => config('selflow.plan_comptable_defaut.client_collectif'),
                'source'           => 'systeme',
            ]
        );
    }

    public static function obtenirClientsPrioritaires($entrepriseId)
    {
        $hasComptaflow = self::where('entreprise_id', $entrepriseId)->where('source', 'comptaflow')->exists();
        if ($hasComptaflow) {
            return self::where('entreprise_id', $entrepriseId)->where('source', 'comptaflow')->orderBy('nom')->get();
        }
        return self::where('entreprise_id', $entrepriseId)->orderBy('nom')->get();
    }
}
