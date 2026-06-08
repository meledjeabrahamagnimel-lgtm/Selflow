<?php

namespace App\Modules\Admin\Controleurs;

use App\Modules\Admin\Modeles\PointDeVente;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class PointDeVenteControleur
{
    public function index(): View
    {
        $entreprise   = Auth::user()->entreprise;
        $pointsDeVente = PointDeVente::where('entreprise_id', $entreprise->id)
            ->withCount('utilisateurs')
            ->withCount('ventes')
            ->orderBy('nom')
            ->get();

        $quotaMax = $entreprise->quota_points_de_vente;

        return view('admin::points_de_vente.index', compact('pointsDeVente', 'quotaMax'));
    }

    public function creer(Request $request): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;

        $request->validate([
            'nom'       => ['required', 'string', 'max:100'],
            'ville'     => ['required', 'string', 'max:100'],
            'commune'   => ['nullable', 'string', 'max:100'],
            'responsable'=> ['nullable', 'string', 'max:150'],
            'telephone' => ['nullable', 'string', 'max:30'],
        ]);

        if ($entreprise->pointsDeVente()->count() >= $entreprise->quota_points_de_vente) {
            return back()->withErrors(['general' => 'Quota de points de vente atteint pour votre abonnement.']);
        }

        PointDeVente::create(array_merge(
            $request->only(['nom', 'ville', 'commune', 'responsable', 'telephone']),
            ['entreprise_id' => $entreprise->id, 'statut' => 'Ouvert']
        ));

        return back()->with('succes', 'Point de vente créé avec succès.');
    }

    public function activerSession(Request $request, PointDeVente $pdv): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;
        abort_unless($pdv->entreprise_id === $entreprise->id, 403);

        session(['point_de_vente_actif_id' => $pdv->id, 'point_de_vente_actif_nom' => $pdv->nom]);

        return back()->with('succes', "Point de vente « {$pdv->nom} » activé pour cette session.");
    }

    public function activerApercu(PointDeVente $pdv): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;
        abort_unless($pdv->entreprise_id === $entreprise->id, 403);

        // Activons l'aperçu en stockant dans la session
        session([
            'apercu_pdv_id' => $pdv->id,
            'apercu_pdv_nom' => $pdv->nom,
            // Pour que l'interface pense aussi qu'on est sur ce point de vente
            'point_de_vente_actif_id' => $pdv->id,
            'point_de_vente_actif_nom' => $pdv->nom,
        ]);

        return redirect()->route('caissier.tableau_de_bord')->with('succes', "Aperçu du point de vente « {$pdv->nom} » activé en mode lecture seule.");
    }

    public function desactiverApercu(): RedirectResponse
    {
        session()->forget(['apercu_pdv_id', 'apercu_pdv_nom', 'point_de_vente_actif_id', 'point_de_vente_actif_nom']);

        return redirect()->route('admin.pdv.index')->with('succes', "Mode aperçu désactivé. Retour à l'administration principale.");
    }
}
