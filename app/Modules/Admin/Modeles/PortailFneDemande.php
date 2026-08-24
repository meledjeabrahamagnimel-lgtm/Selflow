<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une demande de relevé adressée au scraper.
 *
 * ## Le contrat, et pourquoi il est aussi maigre
 *
 * Selflow ne va pas sur le portail : il dépose ici « il me faudrait un relevé
 * frais pour le login X », et le scraper — quel qu'il soit, script lancé à la
 * main ou tâche planifiée — vient lire cette file. Le contrat tient en deux
 * champs, `login` et `motif`, parce que le scraper ne connaît ni les
 * entreprises de Selflow, ni ses pièces, ni ses rejets.
 *
 * ## Ce qui ferme une demande
 *
 * **L'arrivée d'un relevé, jamais la parole du scraper.** Une demande passe à
 * `servie` quand `ImportPortailFneService` range un fichier portant ce login à
 * une date au moins aussi récente que la demande. Un scraper qui échoue en
 * silence laisse donc sa demande ouverte, ce qui est exactement ce qu'on veut
 * voir.
 */
class PortailFneDemande extends Model
{
    protected $table = 'portail_fne_demandes';

    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_SERVIE     = 'servie';
    public const STATUT_ABANDONNEE = 'abandonnee';

    protected $fillable = [
        'entreprise_id',
        'login',
        'motif',
        'rejet_id',
        'statut',
        'import_id',
        'servie_at',
    ];

    protected function casts(): array
    {
        return ['servie_at' => 'datetime'];
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    public function rejet(): BelongsTo
    {
        return $this->belongsTo(FneRejet::class, 'rejet_id');
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PortailFneImport::class, 'import_id');
    }

    /**
     * Demande un relevé pour un login, sans en empiler deux.
     *
     * Dix factures rejetées d'affilée pour le même point de vente mal orthographié
     * décrivent un seul problème et appellent un seul relevé. Sans ce
     * dédoublonnage, une soirée de saisie remplirait la file de demandes
     * identiques que le scraper servirait une à une, pour rien.
     */
    public static function pour(
        string $login,
        ?string $motif = null,
        ?int $entrepriseId = null,
        ?int $rejetId = null
    ): ?self {
        $login = trim($login);

        if ($login === '') {
            return null;
        }

        $enCours = self::where('login', $login)
            ->where('statut', self::STATUT_EN_ATTENTE)
            ->first();

        if ($enCours) {
            return $enCours;
        }

        return self::create([
            'entreprise_id' => $entrepriseId,
            'login'         => $login,
            'motif'         => $motif,
            'rejet_id'      => $rejetId,
            'statut'        => self::STATUT_EN_ATTENTE,
        ]);
    }

    public function servir(PortailFneImport $import): void
    {
        $this->update([
            'statut'    => self::STATUT_SERVIE,
            'import_id' => $import->id,
            'servie_at' => now(),
        ]);
    }
}
