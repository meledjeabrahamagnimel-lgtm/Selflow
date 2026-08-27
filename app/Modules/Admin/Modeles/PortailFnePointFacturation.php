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

    /**
     * Ce que le portail a changé dans les points de facturation d'un login,
     * entre son dernier relevé et le précédent.
     *
     * La comparaison porte sur le **contenu lu**, jamais sur l'empreinte du
     * fichier : le tableur du portail embarque un horodatage de génération
     * (`dcterms:created`), et deux exports identiques peuvent donc différer
     * octet pour octet. Se fier aux octets annoncerait un changement chaque
     * nuit ; se fier au contenu ne dit quelque chose que quand il y en a un.
     *
     * L'identité d'un point est son `etablissement_id` quand il en porte un —
     * un point renommé reste le même point, et c'est justement le renommage
     * qu'on veut voir. À défaut, son nom.
     *
     * @return array{
     *     apparus: array<int, self>,
     *     disparus: array<int, self>,
     *     modifies: array<int, array{point: self, avant: self, changements: array<int, string>}>
     * }
     */
    public static function changementsDepuisLePrecedent(string $login): array
    {
        $vide = ['apparus' => [], 'disparus' => [], 'modifies' => []];

        $dates = self::where('login', $login)
            ->distinct()
            ->orderByDesc('date_scraping')
            ->limit(2)
            ->pluck('date_scraping');

        // Un seul relevé : rien à comparer, et surtout pas de quoi annoncer
        // que tous les points viennent d'apparaître.
        if ($dates->count() < 2) {
            return $vide;
        }

        $indexer = fn ($points) => $points->keyBy(
            fn (self $p) => $p->etablissement_id ?: 'nom:' . $p->nom
        );

        $maintenant = $indexer(self::where('login', $login)->where('date_scraping', $dates[0])->get());
        $avant      = $indexer(self::where('login', $login)->where('date_scraping', $dates[1])->get());

        $resultat = $vide;

        foreach ($maintenant as $cle => $point) {
            $ancien = $avant->get($cle);

            if (!$ancien) {
                $resultat['apparus'][] = $point;
                continue;
            }

            $changements = [];
            foreach (['nom' => 'nom', 'outil' => 'outil', 'terminal_id' => 'terminal',
                      'statut' => 'statut', 'raison_statut' => 'raison'] as $colonne => $libelle) {
                if ((string) ($ancien->{$colonne} ?? '') !== (string) ($point->{$colonne} ?? '')) {
                    $changements[] = sprintf(
                        '%s : « %s » → « %s »',
                        $libelle,
                        $ancien->{$colonne} ?? '—',
                        $point->{$colonne} ?? '—'
                    );
                }
            }

            if ($changements) {
                $resultat['modifies'][] = [
                    'point' => $point, 'avant' => $ancien, 'changements' => $changements,
                ];
            }
        }

        foreach ($avant as $cle => $point) {
            if (!$maintenant->has($cle)) {
                $resultat['disparus'][] = $point;
            }
        }

        return $resultat;
    }
}
