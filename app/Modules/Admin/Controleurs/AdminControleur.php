<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Vente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminControleur
{
    /**
     * Afficher le tableau de bord de l'administrateur (activité personnelle).
     */
    public function tableauDeBord(Request $request): View
    {
        $utilisateur    = Auth::user();
        $entreprise     = $utilisateur->entreprise;
        $pointDeVenteId = session('point_de_vente_actif_id');

        // ── Métriques du jour ──
        $aujourd_hui = now()->toDateString();

        // Ventes du jour personnelles (créées par cet utilisateur)
        $queryVentes = Vente::where('utilisateur_id', $utilisateur->id)
            ->whereDate('date_vente', $aujourd_hui);

        if ($pointDeVenteId) {
            $queryVentes->where('point_de_vente_id', $pointDeVenteId);
        }

        $ventesAujourdhui   = $queryVentes->get();
        $montantVentesJour  = $ventesAujourdhui->sum('montant_ttc');

        // Achats du jour personnels
        $queryAchats = \App\Modules\Admin\Modeles\Achat::where('utilisateur_id', $utilisateur->id)
            ->whereDate('date_achat', $aujourd_hui);

        if ($pointDeVenteId) {
            $queryAchats->where('point_de_vente_id', $pointDeVenteId);
        }
        $montantAchatsJour = $queryAchats->sum('montant_ttc');

        // Alertes stock de l'entreprise
        $produitsEnAlerte = Produit::where('entreprise_id', $entreprise->id)
            ->whereRaw('stock_actuel <= stock_minimum')
            ->orderByRaw('stock_actuel - stock_minimum ASC')
            ->get();

        // Solde trésorerie personnel
        $queryTreso = \App\Modules\Admin\Modeles\TresorerieJournal::where('utilisateur_id', $utilisateur->id);
        if ($pointDeVenteId) {
            $queryTreso->where('point_de_vente_id', $pointDeVenteId);
        }
        $solde = $queryTreso->sum(\Illuminate\Support\Facades\DB::raw('montant_entree - montant_sortie'));

        // Dernières ventes personnelles
        $queryLast = Vente::with(['client', 'pointDeVente'])
            ->where('utilisateur_id', $utilisateur->id);
        if ($pointDeVenteId) {
            $queryLast->where('point_de_vente_id', $pointDeVenteId);
        }
        $dernieresVentes = $queryLast->latest()->limit(5)->get();

        return view('admin::tableau_de_bord', compact(
            'entreprise',
            'montantVentesJour',
            'montantAchatsJour',
            'produitsEnAlerte',
            'solde',
            'dernieresVentes',
            'pointDeVenteId'
        ));
    }

    /**
     * Afficher le tableau de bord général de l'entreprise.
     */
    public function tableauDeBordGeneral(Request $request): View
    {
        $utilisateur    = Auth::user();
        $entreprise     = $utilisateur->entreprise;
        $pointDeVenteId = session('point_de_vente_actif_id');

        // ── Métriques du jour ──
        $aujourd_hui = now()->toDateString();

        // Ventes globales du jour
        $queryVentes = Vente::whereHas('pointDeVente', function ($q) use ($entreprise) {
            $q->where('entreprise_id', $entreprise->id);
        })->whereDate('date_vente', $aujourd_hui);

        if ($pointDeVenteId) {
            $queryVentes->where('point_de_vente_id', $pointDeVenteId);
        }

        $ventesAujourdhui   = $queryVentes->get();
        $montantVentesJour  = $ventesAujourdhui->sum('montant_ttc');

        // Achats globaux du jour
        $queryAchats = \App\Modules\Admin\Modeles\Achat::whereHas('pointDeVente', function ($q) use ($entreprise) {
            $q->where('entreprise_id', $entreprise->id);
        })->whereDate('date_achat', $aujourd_hui);

        if ($pointDeVenteId) {
            $queryAchats->where('point_de_vente_id', $pointDeVenteId);
        }
        $montantAchatsJour = $queryAchats->sum('montant_ttc');

        // Alertes stock
        $produitsEnAlerte = Produit::where('entreprise_id', $entreprise->id)
            ->whereRaw('stock_actuel <= stock_minimum')
            ->orderByRaw('stock_actuel - stock_minimum ASC')
            ->get();

        // Solde trésorerie global
        $queryTreso = \App\Modules\Admin\Modeles\TresorerieJournal::whereHas('pointDeVente', function ($q) use ($entreprise) {
            $q->where('entreprise_id', $entreprise->id);
        });
        if ($pointDeVenteId) {
            $queryTreso->where('point_de_vente_id', $pointDeVenteId);
        }
        $solde = $queryTreso->sum(\Illuminate\Support\Facades\DB::raw('montant_entree - montant_sortie'));

        // Dernières ventes globales
        $queryLast = Vente::with(['client', 'pointDeVente'])
            ->whereHas('pointDeVente', function ($q) use ($entreprise) {
                $q->where('entreprise_id', $entreprise->id);
            });
        if ($pointDeVenteId) {
            $queryLast->where('point_de_vente_id', $pointDeVenteId);
        }
        $dernieresVentes = $queryLast->latest()->limit(5)->get();

        // Points de vente actifs
        $pointsDeVente = $entreprise->pointsDeVente()
            ->withCount(['ventes as ventes_jour' => function ($q) use ($aujourd_hui) {
                $q->whereDate('date_vente', $aujourd_hui);
            }])
            ->withSum(['ventes as montant_ventes_jour' => function ($q) use ($aujourd_hui) {
                $q->whereDate('date_vente', $aujourd_hui);
            }], 'montant_ttc')
            ->get();

        return view('admin::tableau_de_bord_general', compact(
            'entreprise',
            'montantVentesJour',
            'montantAchatsJour',
            'produitsEnAlerte',
            'solde',
            'dernieresVentes',
            'pointsDeVente',
            'pointDeVenteId'
        ));
    }
}
