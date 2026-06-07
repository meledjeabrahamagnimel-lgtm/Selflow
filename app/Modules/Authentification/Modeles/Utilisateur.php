<?php

namespace App\Modules\Authentification\Modeles;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Utilisateur extends Authenticatable
{
    use Notifiable;

    protected $table = 'utilisateurs';

    protected $fillable = [
        'entreprise_id',
        'point_de_vente_id',
        'nom',
        'prenom',
        'email',
        'password',
        'role',
        'fonction',
        'date_debut_contrat',
        'date_fin_contrat',
        'statut',
        'notes',
        'habilitations',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'habilitations'     => 'array',
        ];
    }

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Admin\Modeles\Entreprise::class, 'entreprise_id');
    }

    public function pointDeVente(): BelongsTo
    {
        return $this->belongsTo(\App\Modules\Admin\Modeles\PointDeVente::class, 'point_de_vente_id');
    }

    public function estSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function estAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function estCaissier(): bool
    {
        return $this->role === 'caissier';
    }

    /**
     * Vérifier si l'utilisateur a l'habilitation pour une page spécifique.
     */
    public function aHabilitation(string $page): bool
    {
        if ($this->estSuperAdmin() || $this->estAdmin()) {
            return true;
        }

        return is_array($this->habilitations) && in_array($page, $this->habilitations);
    }
}
