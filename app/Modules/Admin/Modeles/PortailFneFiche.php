<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * La fiche d'une entreprise telle que le portail FNE l'affiche, à une date.
 *
 * C'est un **constat**, pas un paramétrage : ces valeurs ne pilotent rien dans
 * Selflow. Les comparer à celles de l'entreprise est le rôle de qui les lit.
 */
class PortailFneFiche extends Model
{
    protected $table = 'portail_fne_fiches';

    protected $fillable = [
        'import_id',
        'entreprise_id',
        'login',
        'date_scraping',
        'email',
        'telephone',
        'adresse',
        'commune',
        'quartier',
        'reference_cadastrale',
        'idu',
        'proprietaire_local',
        'ref_bancaire',
        'sticker_solde_alerte',
        'timbre_quittance',
        'bapa',
        'pied_de_page_facture',
        'facture_autres_mentions',
        'champs_inconnus',
    ];

    protected function casts(): array
    {
        return [
            'date_scraping'        => 'date',
            'sticker_solde_alerte' => 'integer',
            'timbre_quittance'     => 'boolean',
            'bapa'                 => 'boolean',
            'champs_inconnus'      => 'array',
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
     * Les champs où le portail et l'entreprise paramétrée dans Selflow ne
     * disent pas la même chose.
     *
     * Rendu sous la forme `champ => ['portail' => …, 'selflow' => …]`. Aucune
     * écriture : le rapprochement se regarde, il ne s'applique pas tout seul —
     * `timbre_quittance` et `bapa` changent ce qui part à la DGI.
     *
     * @return array<string, array{portail: mixed, selflow: mixed}>
     */
    public function ecartsAvecEntreprise(): array
    {
        $entreprise = $this->entreprise;

        if (!$entreprise) {
            return [];
        }

        $champs = [
            'email',
            'telephone',
            'adresse',
            'commune',
            'quartier',
            'reference_cadastrale',
            'idu',
            'proprietaire_local',
            'ref_bancaire',
            'sticker_solde_alerte',
            'timbre_quittance',
            'bapa',
            'pied_de_page_facture',
            'facture_autres_mentions',
        ];

        $ecarts = [];

        foreach ($champs as $champ) {
            $portail = $this->{$champ};

            // Un champ que le portail n'a pas rendu ne prouve rien : il ne
            // constitue pas un écart avec ce que Selflow a enregistré.
            if ($portail === null || $portail === '') {
                continue;
            }

            $selflow = $entreprise->{$champ} ?? null;

            if ((string) $portail !== (string) $selflow) {
                $ecarts[$champ] = ['portail' => $portail, 'selflow' => $selflow];
            }
        }

        return $ecarts;
    }
}
