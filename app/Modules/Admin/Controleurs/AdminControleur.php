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
     * Afficher le tableau de bord de l'administrateur.
     */
    public function tableauDeBord(Request $request): View
    {
        $utilisateur    = Auth::user();
        $entreprise     = $utilisateur->entreprise;
        $pointDeVenteId = session('point_de_vente_actif_id');

        // ── Métriques du jour ──
        $aujourd_hui = now()->toDateString();

        // Ventes du jour (filtré par PDV actif si sélectionné)
        $queryVentes = Vente::whereHas('pointDeVente', function ($q) use ($entreprise) {
            $q->where('entreprise_id', $entreprise->id);
        })->whereDate('date_vente', $aujourd_hui);

        if ($pointDeVenteId) {
            $queryVentes->where('point_de_vente_id', $pointDeVenteId);
        }

        $ventesAujourdhui   = $queryVentes->get();
        $montantVentesJour  = $ventesAujourdhui->sum('montant_ttc');

        // Achats du jour
        $montantAchatsJour = \App\Modules\Admin\Modeles\Achat::whereHas('pointDeVente', function ($q) use ($entreprise) {
            $q->where('entreprise_id', $entreprise->id);
        })->whereDate('date_achat', $aujourd_hui)->sum('montant_ttc');

        // Alertes stock
        $produitsEnAlerte = Produit::where('entreprise_id', $entreprise->id)
            ->whereRaw('stock_actuel <= stock_minimum')
            ->orderByRaw('stock_actuel - stock_minimum ASC')
            ->get();

        // Solde trésorerie
        $solde = \App\Modules\Admin\Modeles\TresorerieJournal::whereHas('pointDeVente', function ($q) use ($entreprise) {
            $q->where('entreprise_id', $entreprise->id);
        })->sum(\Illuminate\Support\Facades\DB::raw('montant_entree - montant_sortie'));

        // Dernières ventes
        $dernieresVentes = Vente::with(['client', 'pointDeVente'])
            ->whereHas('pointDeVente', function ($q) use ($entreprise) {
                $q->where('entreprise_id', $entreprise->id);
            })
            ->latest()
            ->limit(5)
            ->get();

        // Points de vente actifs
        $pointsDeVente = $entreprise->pointsDeVente()
            ->withCount(['ventes as ventes_jour' => function ($q) use ($aujourd_hui) {
                $q->whereDate('date_vente', $aujourd_hui);
            }])
            ->withSum(['ventes as montant_ventes_jour' => function ($q) use ($aujourd_hui) {
                $q->whereDate('date_vente', $aujourd_hui);
            }], 'montant_ttc')
            ->get();

        return view('admin::tableau_de_bord', compact(
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
