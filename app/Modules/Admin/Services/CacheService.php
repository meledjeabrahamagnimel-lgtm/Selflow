<?php

namespace App\Modules\Admin\Services;

use App\Modules\Admin\Modeles\PointDeVente;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Service de Cache centralisé pour les données peu changeantes.
 *
 * Stratégie :
 *   - TTL de 60 minutes pour les listes (points de vente, etc.)
 *   - TTL de 120 minutes pour les paramètres entreprise
 *   - Invalidation manuelle via les méthodes `invalider*()` lors de toute mise à jour
 *
 * Clés de cache : toutes préfixées par "selflow_{entreprise_id}_" pour
 * garantir l'isolation par entreprise (multi-tenant).
 *
 * Section 18.4 de la feuille de route.
 */
class CacheService
{
    // TTL en secondes
    private const TTL_COURT  = 3600;   // 60 minutes — données moyennement stables
    private const TTL_LONG   = 7200;   // 120 minutes — données très stables

    // -------------------------------------------------------------------------
    // Points de vente
    // -------------------------------------------------------------------------

    /**
     * Obtenir les points de vente d'une entreprise (avec cache).
     */
    public static function pointsDeVente(int $entrepriseId): Collection
    {
        $cle = "selflow_{$entrepriseId}_points_de_vente";
        $resultat = Cache::get($cle);

        if ($resultat instanceof \__PHP_Incomplete_Class || !($resultat instanceof Collection)) {
            Cache::forget($cle);
            $resultat = PointDeVente::where('entreprise_id', $entrepriseId)
                ->orderBy('nom')
                ->get();
            Cache::put($cle, $resultat, self::TTL_COURT);
        }

        return $resultat;
    }

    /**
     * Invalider le cache des points de vente d'une entreprise.
     * À appeler après création / modification / suppression d'un PDV.
     */
    public static function invaliderPointsDeVente(int $entrepriseId): void
    {
        Cache::forget("selflow_{$entrepriseId}_points_de_vente");
    }

    // -------------------------------------------------------------------------
    // Paramètres entreprise
    // -------------------------------------------------------------------------

    /**
     * Obtenir les données de l'entreprise (avec cache).
     * Utile pour éviter des requêtes répétées sur chaque requête HTTP.
     */
    public static function entreprise(int $entrepriseId): ?\App\Modules\Admin\Modeles\Entreprise
    {
        $cle = "selflow_{$entrepriseId}_entreprise";
        $resultat = Cache::get($cle);

        if ($resultat instanceof \__PHP_Incomplete_Class || ($resultat !== null && !($resultat instanceof \App\Modules\Admin\Modeles\Entreprise))) {
            Cache::forget($cle);
            $resultat = \App\Modules\Admin\Modeles\Entreprise::find($entrepriseId);
            if ($resultat) {
                Cache::put($cle, $resultat, self::TTL_LONG);
            }
        }

        return $resultat;
    }

    /**
     * Invalider le cache de l'entreprise.
     * À appeler après modification des paramètres entreprise.
     */
    public static function invaliderEntreprise(int $entrepriseId): void
    {
        Cache::forget("selflow_{$entrepriseId}_entreprise");
    }

    // -------------------------------------------------------------------------
    // Plan comptable (codes journaux)
    // -------------------------------------------------------------------------

    /**
     * Obtenir les codes journaux d'une entreprise (avec cache).
     */
    public static function codesJournaux(int $entrepriseId): Collection
    {
        $cle = "selflow_{$entrepriseId}_codes_journaux";
        $resultat = Cache::get($cle);

        if ($resultat instanceof \__PHP_Incomplete_Class || !($resultat instanceof Collection)) {
            Cache::forget($cle);
            $resultat = \App\Modules\Admin\Modeles\CodeJournal::where('entreprise_id', $entrepriseId)
                ->orderBy('code')
                ->get();
            Cache::put($cle, $resultat, self::TTL_LONG);
        }

        return $resultat;
    }

    /**
     * Invalider le cache des codes journaux.
     */
    public static function invaliderCodesJournaux(int $entrepriseId): void
    {
        Cache::forget("selflow_{$entrepriseId}_codes_journaux");
    }

    // -------------------------------------------------------------------------
    // Utilitaire : invalider TOUT le cache d'une entreprise
    // -------------------------------------------------------------------------

    /**
     * Invalider toutes les entrées de cache pour une entreprise donnée.
     * Pratique lors d'une reconfiguration complète.
     */
    public static function invaliderTout(int $entrepriseId): void
    {
        self::invaliderPointsDeVente($entrepriseId);
        self::invaliderEntreprise($entrepriseId);
        self::invaliderCodesJournaux($entrepriseId);
    }

    // -------------------------------------------------------------------------
    // Raccourci : obtenir les PDV de l'utilisateur courant
    // -------------------------------------------------------------------------

    /**
     * Retourne les PDV de l'utilisateur authentifié courant.
     */
    public static function pointsDeVenteCourant(): Collection
    {
        $entrepriseId = Auth::user()?->entreprise_id;
        if (!$entrepriseId) {
            return collect();
        }
        return self::pointsDeVente($entrepriseId);
    }
}
