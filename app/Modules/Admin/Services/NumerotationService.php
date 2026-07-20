<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\BonLivraison;
use App\Modules\Admin\Modeles\Vente;
use Illuminate\Support\Facades\DB;

class NumerotationService
{
    /**
     * Génère un numéro de document unique et séquentiel scoppé à l'entreprise pour les achats.
     */
    public static function genererNumeroAchat(int $entrepriseId, string $etape, ?string $typeFacture = null): string
    {
        $prefixe = 'AC-';
        if ($etape === 'Demande de prix') {
            $prefixe = 'DP-';
        } elseif ($etape === 'Bon de commande') {
            $prefixe = 'BC-';
        } elseif ($typeFacture === 'avoir') {
            $prefixe = 'AV-';
        } elseif ($typeFacture === 'bapa') {
            $prefixe = 'BA-';
        }

        $annee = now()->year;
        
        $compte = Achat::whereYear('created_at', $annee)
            ->whereHas('pointDeVente', function ($query) use ($entrepriseId) {
                $query->where('entreprise_id', $entrepriseId);
            })
            ->where('numero_facture', 'LIKE', $prefixe . '%')
            ->count();

        $sequence = str_pad($compte + 1, 4, '0', STR_PAD_LEFT);
        return $prefixe . now()->format('d-m-Y') . '-' . $sequence;
    }

    /**
     * Génère un numéro de document unique et séquentiel scoppé à l'entreprise pour les ventes.
     */
    public static function genererNumeroVente(int $entrepriseId, string $etape, ?string $typeFacture = null): string
    {
        $prefixe = 'VT-';
        if ($etape === 'Devis') {
            $prefixe = 'DV-';
        } elseif ($etape === 'Bon de commande') {
            $prefixe = 'BC-';
        } elseif ($typeFacture === 'avoir') {
            $prefixe = 'AV-';
        }

        $annee = now()->year;

        $compte = Vente::whereYear('created_at', $annee)
            ->whereHas('pointDeVente', function ($query) use ($entrepriseId) {
                $query->where('entreprise_id', $entrepriseId);
            })
            ->where('numero_facture', 'LIKE', $prefixe . '%')
            ->count();

        $sequence = str_pad($compte + 1, 4, '0', STR_PAD_LEFT);
        return $prefixe . now()->format('d-m-Y') . '-' . $sequence;
    }

    /**
     * Génère un numéro de Bon de Livraison unique et séquentiel (BL-DD-MM-AAAA-XXXX).
     */
    public static function genererNumeroBL(int $entrepriseId): string
    {
        $prefixe = 'BL-';
        $annee   = now()->year;

        $compte = BonLivraison::whereYear('created_at', $annee)
            ->whereHas('pointDeVente', function ($query) use ($entrepriseId) {
                $query->where('entreprise_id', $entrepriseId);
            })
            ->count();

        $sequence = str_pad($compte + 1, 4, '0', STR_PAD_LEFT);
        return $prefixe . now()->format('d-m-Y') . '-' . $sequence;
    }
}
