<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Une pièce que la plateforme FNE a refusée.
 *
 * ## Pourquoi cette table existe
 *
 * `FneService::messageRejet()` assemble déjà un message précis — le champ
 * fautif, la valeur envoyée, la raison de la DGI. Ce message était rendu à
 * l'appelant, journalisé, puis perdu : le job de normalisation se contentait
 * d'un `Log::warning`. Un rejet survenu la nuit ne laissait donc, au matin,
 * qu'une ligne dans un fichier de log que personne ne lit.
 *
 * ## Ce que cette table ne fait pas
 *
 * Elle **ne rejoue rien** et **ne corrige rien**. Consigner un rejet n'a aucun
 * effet sur la pièce, sur l'entreprise, ni sur ce qui sera envoyé la prochaine
 * fois. C'est un constat daté, au même titre qu'un relevé du portail.
 */
class FneRejet extends Model
{
    protected $table = 'fne_rejets';

    public const STATUT_OUVERT       = 'ouvert';
    public const STATUT_DIAGNOSTIQUE = 'diagnostique';
    public const STATUT_RESOLU       = 'resolu';

    protected $fillable = [
        'entreprise_id',
        'piece_type',
        'piece_id',
        'numero_piece',
        'login',
        'message',
        'champs',
        'statut',
        'diagnostic',
    ];

    protected function casts(): array
    {
        return [
            'champs'     => 'array',
            'diagnostic' => 'array',
        ];
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    public function demandes(): HasMany
    {
        return $this->hasMany(PortailFneDemande::class, 'rejet_id');
    }

    /**
     * Consigne un rejet à partir du résultat rendu par `FneService`.
     *
     * Appelée là où l'on constatait `success === false` sans rien en faire.
     * Elle n'échoue jamais bruyamment : un défaut de journalisation ne doit pas
     * transformer un rejet — déjà mauvais — en exception qui masque le rejet
     * lui-même.
     *
     * **Elle ouvre aussi la demande de relevé**, plutôt que de laisser chaque
     * appelant y penser. Un rejet est le seul moment où un relevé du portail
     * sert vraiment à quelque chose ; confier cette ouverture aux trois — demain
     * quatre — endroits qui normalisent une pièce, c'est s'assurer qu'un
     * d'entre eux l'oubliera.
     *
     * @param  Vente|Achat            $piece
     * @param  array<string, mixed>   $resultat  Le retour de `normaliserFacture()`
     *                                           ou `normaliserAchatBapa()`.
     */
    public static function consigner($piece, array $resultat): ?self
    {
        if (($resultat['success'] ?? false) === true) {
            return null;
        }

        try {
            // Ni `ventes` ni `achats` ne portent `entreprise_id` : l'entreprise
            // se joint par le point de vente, comme le fait FneService pour
            // bâtir son payload (`FneService.php:28`).
            $entreprise = $piece->pointDeVente?->entreprise;

            $rejet = self::create([
                'entreprise_id' => $entreprise?->id,
                'piece_type'    => $piece instanceof Achat ? 'achat' : 'vente',
                'piece_id'      => $piece->id,
                'numero_piece'  => $piece->numero_facture ?? $piece->numero ?? null,
                'login'         => $entreprise?->ncc,
                'message'       => $resultat['message'] ?? null,
                'champs'        => self::champsRejetes($resultat),
                'statut'        => self::STATUT_OUVERT,
            ]);

            if ($rejet->login) {
                PortailFneDemande::pour(
                    $rejet->login,
                    trim(sprintf(
                        'Rejet %s%s',
                        $rejet->numero_piece ?: "pièce #{$rejet->piece_id}",
                        $rejet->nomsDesChamps() ? ' sur ' . implode(', ', $rejet->nomsDesChamps()) : ''
                    )),
                    $rejet->entreprise_id,
                    $rejet->id
                );
            }

            return $rejet;
        } catch (Throwable $e) {
            Log::error('FneRejet : consignation impossible', [
                'piece'  => $piece->id ?? null,
                'erreur' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Les champs que la DGI a nommés dans son refus.
     *
     * La plateforme les rend dans la clé `errors` de sa réponse, que
     * `FneService` conserve brute sous `errors.api_error`. On la relit ici
     * plutôt que d'analyser le message en français assemblé par
     * `messageRejet()` : un message est fait pour être lu, pas découpé.
     *
     * @param  array<string, mixed>  $resultat
     * @return array<string, mixed>|null
     */
    private static function champsRejetes(array $resultat): ?array
    {
        $erreurs = $resultat['errors'] ?? null;

        if (!is_array($erreurs)) {
            return null;
        }

        // Cas courant : un rejet HTTP, dont le corps brut porte le détail.
        if (isset($erreurs['api_error']) && is_string($erreurs['api_error'])) {
            $corps = json_decode($erreurs['api_error'], true);

            if (is_array($corps) && is_array($corps['errors'] ?? null)) {
                return $corps['errors'];
            }
        }

        // Cas des refus prononcés par Selflow avant tout appel — clé API
        // absente, avoir sans facture d'origine. La clé suffit à les nommer.
        return $erreurs ?: null;
    }

    /**
     * La pièce refusée, quand elle existe encore.
     */
    public function piece(): ?Model
    {
        return $this->piece_type === 'achat'
            ? Achat::find($this->piece_id)
            : Vente::find($this->piece_id);
    }

    /**
     * Les noms de champs que la DGI a mis en cause.
     *
     * @return array<int, string>
     */
    public function nomsDesChamps(): array
    {
        return array_keys($this->champs ?? []);
    }

    public function estOuvert(): bool
    {
        return $this->statut === self::STATUT_OUVERT;
    }
}
