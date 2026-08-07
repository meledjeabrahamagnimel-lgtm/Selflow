<?php

namespace App\Modules\Admin\Modeles\Referentiel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Référentiel de préparamétrage — catalogue livré avec l'application.
 *
 * Ces tables ne contiennent aucune donnée client : une entreprise qui souscrit
 * à un profil en reçoit une copie chez elle, qu'elle peut modifier librement
 * sans que le référentiel en soit affecté.
 */
class Profil extends Model
{
    protected $table = 'referentiel_profils';
    protected $fillable = ['code', 'nom', 'categorie_id', 'description',
                           'module_stock', 'module_production',
                           'module_chantiers', 'module_cycles', 'note_gestion'];
    protected $casts = [
        'module_stock' => 'boolean', 'module_production' => 'boolean',
        'module_chantiers' => 'boolean', 'module_cycles' => 'boolean',
    ];

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class, 'categorie_id');
    }

    public function familles(): HasMany
    {
        return $this->hasMany(Famille::class, 'profil_id');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'profil_id');
    }

    /**
     * Modules que ce profil ouvre, sous les noms employés par `modules_actifs`.
     *
     * @return array<int, string>
     */
    public function modulesOuverts(): array
    {
        return array_keys(array_filter([
            'stock'       => $this->module_stock,
            'production'  => $this->module_production,
            'chantiers'   => $this->module_chantiers,
            'cycles'      => $this->module_cycles,
        ]));
    }
}
