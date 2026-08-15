<?php

namespace App\Modules\Authentification\Modeles;

use App\Modules\Admin\Modeles\Concerns\IdentifiantOpaque;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Utilisateur extends Authenticatable
{
    use IdentifiantOpaque;

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
        'visite_guidee_terminee_le',
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
            'visite_guidee_terminee_le' => 'datetime',
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
     * Les accès délégués : le même espace, mais pas les mêmes droits.
     *
     * Un administrateur qui veut confier son travail ne crée pas un second
     * propriétaire — il ouvre un accès à **son** espace, et choisit ce que la
     * personne y voit. Ces rôles franchissent donc `role:admin`, et ce sont
     * les habilitations qui tranchent ensuite, écran par écran.
     */
    public const ROLES_DELEGUES = ['admin_secondaire', 'responsable_pdv'];

    /**
     * Cet utilisateur travaille-t-il dans l'espace d'administration ?
     *
     * Le propriétaire et ses délégués, oui. La différence entre eux ne se joue
     * pas ici mais dans `aHabilitation()`.
     */
    public function partageLEspaceAdmin(): bool
    {
        return $this->estAdmin() || in_array($this->role, self::ROLES_DELEGUES, true);
    }

    /**
     * Vérifier si l'utilisateur a l'habilitation pour une page spécifique.
     *
     * **`admin_secondaire` recevait `true` pour tout**, au même titre que le
     * propriétaire. Déléguer revenait donc à céder l'entreprise entière : le
     * compte créé « pour aider aux ventes » atteignait la comptabilité, les
     * paramètres fiscaux, et l'écran qui distribue les droits — d'où il
     * pouvait s'en accorder d'autres, ou en retirer au propriétaire.
     *
     * Seuls le superadministrateur et le propriétaire ont tout. Un délégué a
     * ce qu'on lui a donné, et rien de plus. Un compte sans habilitation ne
     * voit donc rien tant que son administrateur n'a pas coché : c'est le sens
     * d'un contrôle qui ferme par défaut.
     */
    public function aHabilitation(string $page): bool
    {
        if ($this->estSuperAdmin() || $this->estAdmin()) {
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

    /**
     * A-t-il déjà fait la visite guidée ?
     *
     * Elle se retient par utilisateur : un vendeur qui rejoint une entreprise
     * déjà configurée découvre l'application pour la première fois, lui aussi.
     */
    public function aFaitLaVisite(): bool
    {
        return $this->visite_guidee_terminee_le !== null;
    }
}
