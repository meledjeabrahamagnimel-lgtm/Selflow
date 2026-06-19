<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\TresorerieJournal;
use App\Modules\Admin\Modeles\Vente;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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
        $aujourd_hui    = now()->toDateString();

        // ── Ventes du jour personnelles ──────────────────────────────────────
        $qVentes = Vente::where('utilisateur_id', $utilisateur->id)
            ->whereDate('date_vente', $aujourd_hui);
        if ($pointDeVenteId) $qVentes->where('point_de_vente_id', $pointDeVenteId);

        $ventesAujourdhui  = $qVentes->get();
        $montantVentesJour = $ventesAujourdhui->sum('montant_ttc');
        $nbVentesJour      = $ventesAujourdhui->count();

        // ── Achats du jour personnels ─────────────────────────────────────────
        $qAchats = Achat::where('utilisateur_id', $utilisateur->id)
            ->whereDate('date_achat', $aujourd_hui);
        if ($pointDeVenteId) $qAchats->where('point_de_vente_id', $pointDeVenteId);
        $montantAchatsJour = $qAchats->sum('montant_ttc');

        // ── Ventes de la période (via PeriodeScope) ───────────────────────────
        $qVentesPeriode = Vente::where('utilisateur_id', $utilisateur->id)
            ->where('statut', 'Facturée');
        if ($pointDeVenteId) $qVentesPeriode->where('point_de_vente_id', $pointDeVenteId);
        $totalVentesPeriode = $qVentesPeriode->sum('montant_ttc');
        $nbVentesPeriode    = $qVentesPeriode->count();

        // ── Solde trésorerie personnel ────────────────────────────────────────
        $qTreso = TresorerieJournal::where('utilisateur_id', $utilisateur->id);
        if ($pointDeVenteId) $qTreso->where('point_de_vente_id', $pointDeVenteId);
        $solde = $qTreso->sum(DB::raw('montant_entree - montant_sortie'));

        // ── Alertes stock ─────────────────────────────────────────────────────
        $produitsEnAlerte = Produit::where('entreprise_id', $entreprise->id)
            ->whereRaw('stock_actuel <= stock_minimum')
            ->orderByRaw('stock_actuel - stock_minimum ASC')
            ->limit(8)
            ->get();

        // ── Dernières ventes personnelles ─────────────────────────────────────
        $qLast = Vente::with(['client', 'pointDeVente'])
            ->where('utilisateur_id', $utilisateur->id);
        if ($pointDeVenteId) $qLast->where('point_de_vente_id', $pointDeVenteId);
        $dernieresVentes = $qLast->latest()->limit(8)->get();

        // ── Évolution 7 derniers jours (ventes personnelles - sans PeriodeScope) ──
        $evolution7j = DB::table('ventes')
            ->select(DB::raw("DATE(date_vente) as jour"), DB::raw('SUM(montant_ttc) as total'), DB::raw('COUNT(*) as nb'))
            ->where('utilisateur_id', $utilisateur->id)
            ->where('statut', 'Facturée')
            ->when($pointDeVenteId, fn($q) => $q->where('point_de_vente_id', $pointDeVenteId))
            ->whereBetween('date_vente', [now()->subDays(6)->toDateString(), $aujourd_hui])
            ->groupBy('jour')
            ->orderBy('jour')
            ->get();

        // Remplir les jours manquants
        $jours7 = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $found = $evolution7j->firstWhere('jour', $date);
            $jours7->push(['jour' => $date, 'total' => $found ? $found->total : 0, 'nb' => $found ? $found->nb : 0]);
        }

        // ── Meilleur produit vendu sur la période ─────────────────────────────
        $meilleurProduit = DB::table('vente_details')
            ->join('ventes', 'ventes.id', '=', 'vente_details.vente_id')
            ->select('vente_details.libelle_virtuel', 'vente_details.produit_id',
                DB::raw('SUM(vente_details.montant_ttc) as ca'),
                DB::raw('SUM(vente_details.quantite) as qte'))
            ->where('ventes.utilisateur_id', $utilisateur->id)
            ->where('ventes.statut', 'Facturée')
            ->when($pointDeVenteId, fn($q) => $q->where('ventes.point_de_vente_id', $pointDeVenteId))
            ->groupBy('vente_details.libelle_virtuel', 'vente_details.produit_id')
            ->orderByDesc('ca')
            ->first();

        return view('admin::tableau_de_bord', compact(
            'entreprise', 'montantVentesJour', 'montantAchatsJour',
            'nbVentesJour', 'totalVentesPeriode', 'nbVentesPeriode',
            'produitsEnAlerte', 'solde', 'dernieresVentes',
            'pointDeVenteId', 'jours7', 'meilleurProduit'
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
        $aujourd_hui    = now()->toDateString();

        $pdvIds = $entreprise->pointsDeVente()->pluck('id');

        // ── CA global du jour ─────────────────────────────────────────────────
        $qVentes = Vente::whereIn('point_de_vente_id', $pdvIds)->whereDate('date_vente', $aujourd_hui);
        if ($pointDeVenteId) $qVentes->where('point_de_vente_id', $pointDeVenteId);
        $montantVentesJour = $qVentes->sum('montant_ttc');
        $nbVentesJour      = $qVentes->count();

        // ── Achats globaux du jour ────────────────────────────────────────────
        $qAchats = Achat::whereIn('point_de_vente_id', $pdvIds)->whereDate('date_achat', $aujourd_hui);
        if ($pointDeVenteId) $qAchats->where('point_de_vente_id', $pointDeVenteId);
        $montantAchatsJour = $qAchats->sum('montant_ttc');

        // ── CA global de la période (via PeriodeScope) ────────────────────────
        $qVentesPeriode = Vente::whereIn('point_de_vente_id', $pdvIds)->where('statut', 'Facturée');
        if ($pointDeVenteId) $qVentesPeriode->where('point_de_vente_id', $pointDeVenteId);
        $totalVentesPeriode = $qVentesPeriode->sum('montant_ttc');
        $nbVentesPeriode    = $qVentesPeriode->count();

        // ── Achats de la période ──────────────────────────────────────────────
        $qAchatsPeriode = Achat::whereIn('point_de_vente_id', $pdvIds)->where('statut', 'Facturée');
        if ($pointDeVenteId) $qAchatsPeriode->where('point_de_vente_id', $pointDeVenteId);
        $totalAchatsPeriode = $qAchatsPeriode->sum('montant_ttc');

        // ── Marge brute de la période ─────────────────────────────────────────
        $qVentesHT  = Vente::whereIn('point_de_vente_id', $pdvIds)->where('statut', 'Facturée');
        if ($pointDeVenteId) $qVentesHT->where('point_de_vente_id', $pointDeVenteId);
        $totalVentesHTPeriode = $qVentesHT->sum('montant_ht');
        $margeBrutePeriode    = $totalVentesHTPeriode - $totalAchatsPeriode;
        $tauxMargePeriode     = $totalVentesHTPeriode > 0
            ? round(($margeBrutePeriode / $totalVentesHTPeriode) * 100, 1)
            : 0;

        // ── Alertes stock ─────────────────────────────────────────────────────
        $produitsEnAlerte = Produit::where('entreprise_id', $entreprise->id)
            ->whereRaw('stock_actuel <= stock_minimum')
            ->orderByRaw('stock_actuel - stock_minimum ASC')
            ->limit(8)
            ->get();

        // ── Solde trésorerie global ───────────────────────────────────────────
        $qTreso = TresorerieJournal::whereIn('point_de_vente_id', $pdvIds);
        if ($pointDeVenteId) $qTreso->where('point_de_vente_id', $pointDeVenteId);
        $totalEncaissements = $qTreso->sum('montant_entree');
        $totalDecaissements = $qTreso->sum('montant_sortie');
        $solde              = $totalEncaissements - $totalDecaissements;

        // ── Dernières ventes globales ─────────────────────────────────────────
        $qLast = Vente::with(['client', 'pointDeVente'])->whereIn('point_de_vente_id', $pdvIds);
        if ($pointDeVenteId) $qLast->where('point_de_vente_id', $pointDeVenteId);
        $dernieresVentes = $qLast->latest()->limit(8)->get();

        // ── Points de vente avec métriques du jour ────────────────────────────
        $pointsDeVente = $entreprise->pointsDeVente()
            ->withCount(['ventes as ventes_jour' => fn($q) => $q->whereDate('date_vente', $aujourd_hui)])
            ->withSum(['ventes as montant_ventes_jour' => fn($q) => $q->whereDate('date_vente', $aujourd_hui)], 'montant_ttc')
            ->get();

        // ── Évolution 7 derniers jours (globale - sans PeriodeScope) ──────────
        $evolution7j = DB::table('ventes')
            ->select(DB::raw("DATE(date_vente) as jour"), DB::raw('SUM(montant_ttc) as total'), DB::raw('COUNT(*) as nb'))
            ->whereIn('point_de_vente_id', $pdvIds)
            ->where('statut', 'Facturée')
            ->when($pointDeVenteId, fn($q) => $q->where('point_de_vente_id', $pointDeVenteId))
            ->whereBetween('date_vente', [now()->subDays(6)->toDateString(), $aujourd_hui])
            ->groupBy('jour')
            ->orderBy('jour')
            ->get();

        $jours7 = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->toDateString();
            $found = $evolution7j->firstWhere('jour', $date);
            $jours7->push(['jour' => $date, 'total' => $found ? $found->total : 0, 'nb' => $found ? $found->nb : 0]);
        }

        // ── Meilleurs vendeurs du jour ────────────────────────────────────────
        $topVendeurs = DB::table('ventes')
            ->join('utilisateurs', 'utilisateurs.id', '=', 'ventes.utilisateur_id')
            ->select('ventes.utilisateur_id',
                DB::raw("CONCAT(utilisateurs.prenom, ' ', utilisateurs.nom) as nom_employe"),
                DB::raw('SUM(ventes.montant_ttc) as total'),
                DB::raw('COUNT(*) as nb_ventes'))
            ->whereIn('ventes.point_de_vente_id', $pdvIds)
            ->whereDate('ventes.date_vente', $aujourd_hui)
            ->groupBy('ventes.utilisateur_id', 'utilisateurs.prenom', 'utilisateurs.nom')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        // ── CA par PDV sur la période ─────────────────────────────────────────
        $caPdvPeriode = DB::table('ventes')
            ->join('points_de_vente', 'points_de_vente.id', '=', 'ventes.point_de_vente_id')
            ->select('points_de_vente.nom as pdv_nom',
                DB::raw('SUM(ventes.montant_ttc) as ca'),
                DB::raw('COUNT(*) as nb'))
            ->whereIn('ventes.point_de_vente_id', $pdvIds)
            ->where('ventes.statut', 'Facturée')
            ->groupBy('ventes.point_de_vente_id', 'points_de_vente.nom')
            ->orderByDesc('ca')
            ->get();

        return view('admin::tableau_de_bord_general', compact(
            'entreprise', 'montantVentesJour', 'montantAchatsJour',
            'nbVentesJour', 'totalVentesPeriode', 'nbVentesPeriode',
            'totalAchatsPeriode', 'margeBrutePeriode', 'tauxMargePeriode',
            'produitsEnAlerte', 'solde', 'totalEncaissements', 'totalDecaissements',
            'dernieresVentes', 'pointsDeVente', 'pointDeVenteId',
            'jours7', 'topVendeurs', 'caPdvPeriode'
        ));
    }
}
