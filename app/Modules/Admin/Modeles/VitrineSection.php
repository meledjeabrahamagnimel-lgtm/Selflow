<?php

namespace App\Modules\Admin\Modeles;

use App\Modules\Admin\Modeles\Concerns\IdentifiantOpaque;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un bloc de la page publique.
 *
 * La vitrine n'appartient à aucune entreprise : c'est la page de DC-Knowing,
 * tenue par la plateforme. Elle ne porte donc pas d'`entreprise_id`, et n'est
 * pas cloisonnée — la seule écriture possible passe par l'écran superadmin.
 */
class VitrineSection extends Model
{
    use IdentifiantOpaque;

    protected $table = 'vitrine_sections';

    protected $fillable = [
        'cle', 'titre', 'sous_titre', 'texte',
        'gabarit', 'ordre', 'publiee',
    ];

    protected function casts(): array
    {
        return ['publiee' => 'boolean', 'ordre' => 'integer'];
    }

    /**
     * Les dispositions possibles, et ce que chacune veut dire à l'écran.
     *
     * La liste est fermée : une valeur libre finirait par désigner un gabarit
     * qui n'existe pas, et la page tomberait sur une section sans savoir la
     * dessiner.
     *
     * @var array<string, string>
     */
    public const GABARITS = [
        'bandeau'  => 'Bandeau d\'ouverture — un grand titre et un appel à l\'action',
        'colonnes' => 'Colonnes — les cartes côte à côte, en grille',
        'liste'    => 'Liste — les cartes les unes sous les autres',
        'tarifs'   => 'Tarifs — une carte par offre, avec son prix',
        'texte'    => 'Texte seul — aucune carte, seulement le texte de la section',
    ];

    public function cartes(): HasMany
    {
        return $this->hasMany(VitrineCarte::class, 'section_id')->orderBy('ordre');
    }

    /**
     * Les cartes visibles du public.
     */
    public function cartesPubliees(): HasMany
    {
        return $this->cartes()->where('publiee', true);
    }

    public function scopePubliees($requete)
    {
        return $requete->where('publiee', true)->orderBy('ordre');
    }

    /**
     * Le gabarit, ramené à une valeur connue.
     *
     * Une section dont le gabarit a disparu du code — renommé, retiré —
     * s'affiche en colonnes plutôt que de faire tomber la page.
     */
    public function gabaritSur(): string
    {
        return array_key_exists($this->gabarit, self::GABARITS)
            ? $this->gabarit
            : 'colonnes';
    }
}
