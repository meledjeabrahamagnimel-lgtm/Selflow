<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle pour la table journal_audit.
 * Cette table est en INSERTION UNIQUEMENT - jamais modifiable ni supprimable par un utilisateur.
 */
class JournalAudit extends Model
{
    protected $table = 'journal_audit';

    // Pas de updated_at (insert only)
    public const UPDATED_AT = null;

    protected $fillable = [
        'utilisateur_id',
        'action',
        'entite',
        'entite_id',
        'ancienne_valeur',
        'nouvelle_valeur',
        'adresse_ip',
        'point_de_vente_id',
    ];

    protected $casts = [
        'ancienne_valeur' => 'array',
        'nouvelle_valeur' => 'array',
    ];
}
