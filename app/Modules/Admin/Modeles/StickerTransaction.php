<?php

namespace App\Modules\Admin\Modeles;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StickerTransaction extends Model
{
    protected $table = 'sticker_transactions';

    protected $fillable = [
        'entreprise_id',
        'id_transaction_externe',
        'operateur',
        'telephone',
        'nombre_stickers',
        'montant',
        'statut',
    ];

    protected $casts = [
        'nombre_stickers' => 'integer',
        'montant'         => 'decimal:2',
    ];

    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Entreprise::class, 'entreprise_id');
    }

    /**
     * Libellé coloré du statut pour les vues.
     */
    public function statutLabel(): string
    {
        return match ($this->statut) {
            'succes'     => 'Succès',
            'en_attente' => 'En attente',
            'echec'      => 'Échec',
            default      => ucfirst($this->statut),
        };
    }

    public function statutBadgeStyle(): string
    {
        return match ($this->statut) {
            'succes'     => 'background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;',
            'en_attente' => 'background:#fef3c7;color:#92400e;border:1px solid #fde68a;',
            'echec'      => 'background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;',
            default      => 'background:var(--bg3);color:var(--text-2);',
        };
    }
}
