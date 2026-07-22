<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\Produit;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class DashboardApiController extends Controller
{
    public function getExercices(Request $request)
    {
        $currentYear = (int) date('Y');
        return response()->json([
            'status' => 'success',
            'data' => [
                ['id' => (string)$currentYear, 'name' => (string)$currentYear],
                ['id' => (string)($currentYear - 1), 'name' => (string)($currentYear - 1)],
            ]
        ]);
    }

    public function getKpis(Request $request)
    {
        $companyId = $request->query('company_id') ?? $request->header('X-Company-Id');
        $exerciceId = $request->query('exercice_id');
        if (empty($exerciceId)) {
            $exerciceId = date('Y');
        }

        $entreprise = $companyId
            ? Entreprise::select('id', 'nom')->find($companyId)
            : null;

        // Ventes
        $ventesQuery = Vente::whereHas('pointDeVente', function ($q) use ($companyId) {
            if ($companyId) {
                $q->where('entreprise_id', $companyId);
            }
        })->whereYear('date_vente', $exerciceId);

        $totalVentes = (float) $ventesQuery->sum('montant_ttc');

        // Achats
        $achatsQuery = Achat::whereHas('pointDeVente', function ($q) use ($companyId) {
            if ($companyId) {
                $q->where('entreprise_id', $companyId);
            }
        })->whereYear('date_achat', $exerciceId);

        $totalAchats = (float) $achatsQuery->sum('montant_ttc');

        // Factures impayées (Ventes)
        $ventesList = $ventesQuery->get();
        $facturesImpayees = $ventesList->sum(function($v) {
            return max(0, $v->montant_ttc - $v->montant_paye);
        });

        // Stock critique
        // Désactivé temporairement car stock_actuel n'existe plus dans la table produits
        $produitsStockCritique = 0;

        // Top Produits
        $topProduits = DB::table('vente_details')
            ->join('ventes', 'ventes.id', '=', 'vente_details.vente_id')
            ->join('produits', 'produits.id', '=', 'vente_details.produit_id')
            ->join('points_de_vente', 'points_de_vente.id', '=', 'ventes.point_de_vente_id')
            ->whereYear('ventes.date_vente', $exerciceId)
            ->when($companyId, function($q) use ($companyId) {
                return $q->where('points_de_vente.entreprise_id', $companyId);
            })
            ->select('produits.nom', DB::raw('SUM(vente_details.quantite) as quantite'), DB::raw('SUM(vente_details.montant_ttc) as ca'))
            ->groupBy('produits.nom')
            ->orderByDesc('quantite')
            ->limit(5)
            ->get();

        // Dernières factures
        $dernieresFactures = Vente::whereHas('pointDeVente', function ($q) use ($companyId) {
                if ($companyId) {
                    $q->where('entreprise_id', $companyId);
                }
            })
            ->whereYear('date_vente', $exerciceId)
            ->with('client')
            ->orderBy('date_vente', 'desc')
            ->limit(5)
            ->get()
            ->map(function($v) {
                return [
                    'ref' => $v->numero_facture,
                    'client' => $v->client->nom ?? 'Inconnu',
                    'montant' => $v->montant_ttc,
                    'statut' => ($v->statut === 'Payée') ? 'payee' : 'impayee',
                ];
            });

        // Graphique Ventes
        $labels = [];
        $data = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthStr = sprintf('%s-%02d', $exerciceId, $i);
            $labels[] = Carbon::createFromFormat('Y-m', $monthStr)->translatedFormat('M');
            $q = Vente::whereHas('pointDeVente', function ($q) use ($companyId) {
                if ($companyId) {
                    $q->where('entreprise_id', $companyId);
                }
            })->where('date_vente', 'like', $monthStr . '-%');
            $data[] = (float) $q->sum('montant_ttc');
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'company_name' => $entreprise->nom ?? null,
                'total_ventes' => $totalVentes,
                'total_achats' => $totalAchats,
                'factures_impayees' => $facturesImpayees,
                'produits_stock_critique' => $produitsStockCritique,
                'top_produits' => $topProduits,
                'dernieres_factures' => $dernieresFactures,
                'chart_ventes' => [
                    'labels' => $labels,
                    'data' => $data,
                ],
            ],
        ]);
    }

    public function getCompanies(Request $request)
    {
        $entreprises = Entreprise::select('id', 'nom')
            ->orderBy('nom')
            ->get()
            ->map(function ($e) {
                return [
                    'id' => $e->id,
                    'name' => $e->nom, // on renomme "nom" -> "name" pour matcher le format attendu par le Hub
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => $entreprises,
        ]);
    }
}