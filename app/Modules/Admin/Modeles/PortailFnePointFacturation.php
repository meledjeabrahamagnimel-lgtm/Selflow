<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un point de facturation déclaré au portail FNE.
 *
 * Le tableur en rend un par ligne : un nom (« FACTURATION SIEGE »), l'outil qui
 * l'utilise (« Application FNE »), l'établissement auquel il appartient, et son
 * statut. L'identifiant de terminal reste vide pour un point servi par une
 * application — il ne se remplit que pour un matériel de caisse.
 */
class PortailFnePointFacturation extends Model
{
    protected $table = 'portail_fne_points_facturation';

    protected $fillable = [
        'import_id',
        'entreprise_id',
        'login',
        'date_scraping',
        'nom',
        'outil',
        'terminal_id',
        'statut',
        'raison_statut',
        'etablissement_id',
        'cree_a',
        'mis_a_jour_a',
    ];

    protected function casts(): array
    {
        return [
            'date_scraping' => 'date',
            'cree_a'        => 'datetime',
            'mis_a_jour_a'  => 'datetime',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(PortailFneImport::class, 'import_id');
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    /**
     * Le portail rend « 1 » pour un point ouvert. Toute autre valeur est
     * rendue telle quelle par l'import, et n'est pas tenue pour active.
     */
    public function estActif(): bool
    {
        return (string) $this->statut === '1';
    }
}
