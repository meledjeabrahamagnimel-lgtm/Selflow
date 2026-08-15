<?php

namespace App\Modules\Admin\Modeles;

use App\Modules\Admin\Modeles\Concerns\IdentifiantOpaque;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ce qu'une section de la vitrine contient.
 *
 * Les champs sont volontairement génériques : selon le gabarit de sa section,
 * la même carte devient un argument, une offre tarifaire ou une entrée de
 * liste. Un modèle par gabarit aurait multiplié les tables pour la même chose.
 */
class VitrineCarte extends Model
{
    use IdentifiantOpaque;

    protected $table = 'vitrine_cartes';

    protected $fillable = [
        'section_id', 'titre', 'texte', 'role', 'icone', 'image_path',
        'lien_libelle', 'lien_url', 'lien_secondaire_libelle', 'lien_secondaire_url',
        'valeur', 'mention',
        'ordre', 'publiee',
    ];

    /**
     * Les initiales, quand la photo manque encore.
     *
     * Un portrait vide est une case grise ; deux lettres tenues dans un rond
     * font une carte qui se regarde en attendant l'image.
     */
    public function initiales(): string
    {
        $mots = preg_split('/\s+/', trim((string) $this->titre)) ?: [];
        $mots = array_values(array_filter($mots));

        if ($mots === []) {
            return '?';
        }

        $premiere = mb_substr($mots[0], 0, 1);
        $seconde  = count($mots) > 1 ? mb_substr($mots[count($mots) - 1], 0, 1) : '';

        return mb_strtoupper($premiere . $seconde);
    }

    protected function casts(): array
    {
        return ['publiee' => 'boolean', 'ordre' => 'integer'];
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(VitrineSection::class, 'section_id');
    }

    /**
     * L'adresse de l'image, qu'elle ait été déposée ou qu'elle pointe ailleurs.
     */
    public function imageUrl(): ?string
    {
        if (empty($this->image_path)) {
            return null;
        }

        if (\Illuminate\Support\Str::startsWith($this->image_path, ['http://', 'https://'])) {
            return $this->image_path;
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->url($this->image_path);
    }
}
