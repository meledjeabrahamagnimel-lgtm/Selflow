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
        'avatar_path',
        'password',
        'role',
        'fonction',
        'date_debut_contrat',
        'date_fin_contrat',
        'statut',
        'notes',
        'habilitations',
        'jeton_api',
        'doit_changer_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'     => 'datetime',
            'password'              => 'hashed',
            'habilitations'         => 'array',
            'doit_changer_password' => 'boolean',
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

    public function estAdminSecondaire(): bool
    {
        return $this->role === 'admin_secondaire';
    }

    public function estResponsablePdv(): bool
    {
        return $this->role === 'responsable_pdv';
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
        if ($this->estSuperAdmin() || $this->estAdmin() || $this->estAdminSecondaire()) {
            return true;
        }

        return is_array($this->habilitations) && in_array($page, $this->habilitations);
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar_path) {
            return \Illuminate\Support\Facades\Storage::disk('public')->url($this->avatar_path);
        }
        return 'data:image/svg+xml;utf8,' . rawurlencode($this->genererAvatarSvg());
    }

    public function genererAvatarSvg(): string
    {
        $prenomInitial = !empty($this->prenom) ? substr($this->prenom, 0, 1) : '';
        $nomInitial = !empty($this->nom) ? substr($this->nom, 0, 1) : '';
        $initials = strtoupper($prenomInitial . $nomInitial);
        if (empty($initials)) {
            $initials = 'U';
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" width="128" height="128"><rect width="128" height="128" fill="#002B5C"/><text x="50%" y="54%" dominant-baseline="middle" text-anchor="middle" font-family="system-ui, -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif" font-size="48" font-weight="800" fill="#FFFFFF">' . $initials . '</text></svg>';
    }
}
