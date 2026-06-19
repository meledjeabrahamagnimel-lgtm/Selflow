<?php

namespace App\Modules\Authentification\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Modules\Admin\Modeles\Periode;
use Carbon\Carbon;

class GestionPeriode
{
    /**
     * Gérer la requête entrante.
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $entrepriseId = $user->entreprise_id;
            
            if ($entrepriseId) {
                $today = now()->toDateString();

                // 1. Récupérer la dernière période en date de fin pour cette entreprise
                $latestPeriode = Periode::where('entreprise_id', $entrepriseId)
                    ->orderBy('date_fin', 'desc')
                    ->first();

                // Si aucune période n'existe du tout, on en crée une pour l'année courante
                if (!$latestPeriode) {
                    $currentYear = date('Y');
                    $latestPeriode = Periode::create([
                        'entreprise_id' => $entrepriseId,
                        'nom'           => "Période " . $currentYear,
                        'date_debut'    => $currentYear . '-01-01',
                        'date_fin'      => $currentYear . '-12-31',
                        'est_active'    => true,
                    ]);
                }

                // 2. Si la date d'aujourd'hui dépasse la date de fin de la dernière période
                if ($today > $latestPeriode->date_fin->toDateString()) {
                    // Désactiver toutes les anciennes périodes
                    Periode::where('entreprise_id', $entrepriseId)->update(['est_active' => false]);

                    // Créer automatiquement la période suivante pour 1 an
                    $dateDebut = Carbon::parse($latestPeriode->date_fin)->addDay();
                    $dateFin = $dateDebut->copy()->addYear()->subDay();
                    
                    $latestPeriode = Periode::create([
                        'entreprise_id' => $entrepriseId,
                        'nom'           => "Période " . $dateDebut->format('Y'),
                        'date_debut'    => $dateDebut->toDateString(),
                        'date_fin'      => $dateFin->toDateString(),
                        'est_active'    => true,
                    ]);

                    // Mettre à jour la session avec les nouvelles valeurs
                    session([
                        'active_periode_id'    => $latestPeriode->id,
                        'active_periode_nom'   => $latestPeriode->nom,
                        'active_periode_debut' => $latestPeriode->date_debut->toDateString(),
                        'active_periode_fin'   => $latestPeriode->date_fin->toDateString(),
                    ]);
                }

                // 3. Assurer que la session contient une période active
                if (!session()->has('active_periode_id')) {
                    // On cherche une période qui englobe aujourd'hui
                    $active = Periode::where('entreprise_id', $entrepriseId)
                        ->whereDate('date_debut', '<=', $today)
                        ->whereDate('date_fin', '>=', $today)
                        ->first();

                    // Sinon on prend la dernière période active configurée
                    if (!$active) {
                        $active = Periode::where('entreprise_id', $entrepriseId)
                            ->where('est_active', true)
                            ->first() ?? $latestPeriode;
                    }

                    session([
                        'active_periode_id'    => $active->id,
                        'active_periode_nom'   => $active->nom,
                        'active_periode_debut' => $active->date_debut instanceof Carbon ? $active->date_debut->toDateString() : (is_string($active->date_debut) ? $active->date_debut : Carbon::parse($active->date_debut)->toDateString()),
                        'active_periode_fin'   => $active->date_fin instanceof Carbon ? $active->date_fin->toDateString() : (is_string($active->date_fin) ? $active->date_fin : Carbon::parse($active->date_fin)->toDateString()),
                    ]);
                }
                $global_periodes = Periode::where('entreprise_id', $entrepriseId)
                    ->orderBy('date_debut', 'desc')
                    ->get();
                view()->share('global_periodes', $global_periodes);
            }
        }

        return $next($request);
    }
}
