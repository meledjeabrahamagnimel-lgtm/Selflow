<?php

namespace App\Modules\Admin\Controleurs;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Modules\Admin\Traits\JournaliseActions;
use Illuminate\View\View;

class EntrepriseControleur
{
    use JournaliseActions;
    /**
     * Afficher la page des paramètres de l'entreprise.
     */
    public function parametres(): View
    {
        $entreprise = Auth::user()->entreprise;
        $periodes = \App\Modules\Admin\Modeles\Periode::where('entreprise_id', $entreprise->id)
            ->orderBy('date_debut', 'desc')
            ->get();
        return view('admin::entreprise.parametres', compact('entreprise', 'periodes'));
    }

    /**
     * Enregistrer les modifications des paramètres de l'entreprise.
     */
    public function enregistrerParametres(Request $request): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;

        $request->validate([
            'nom'               => ['required', 'string', 'max:150'],
            'gerant_nom'        => ['nullable', 'string', 'max:100'],
            'gerant_prenom'     => ['nullable', 'string', 'max:150'],
            'gerant_fonction'   => ['nullable', 'string', 'max:150'],
            'adresse'           => ['nullable', 'string', 'max:255'],
            'telephone'         => ['nullable', 'string', 'max:30'],
            'email'             => ['nullable', 'email', 'max:150'],
            'ref_bancaire'      => ['nullable', 'string', 'max:1000'],
            'logo'              => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'logo_fne'          => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'comptaflow_sync_key'=> ['nullable', 'string', 'max:255'],
        ]);

        $data = $request->only([
            'nom', 'gerant_nom', 'gerant_prenom', 'gerant_fonction',
            'adresse', 'telephone', 'email', 'ref_bancaire', 'comptaflow_sync_key',
        ]);

        $syncKeyChanged = $request->filled('comptaflow_sync_key') && ($request->comptaflow_sync_key !== $entreprise->comptaflow_sync_key);

        // Mettre à jour le statut en fonction de la présence de la clé
        $data['comptaflow_sync_status'] = !empty($request->comptaflow_sync_key) ? 'active' : 'inactive';

        // Traitement du logo principal
        if ($request->hasFile('logo')) {
            // Supprimer l'ancien logo s'il existe
            if ($entreprise->logo_path && Storage::disk('public')->exists($entreprise->logo_path)) {
                Storage::disk('public')->delete($entreprise->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('logos/entreprises', 'public');
        }

        // Traitement du logo FNE / secondaire
        if ($request->hasFile('logo_fne')) {
            if ($entreprise->logo_fne_path && Storage::disk('public')->exists($entreprise->logo_fne_path)) {
                Storage::disk('public')->delete($entreprise->logo_fne_path);
            }
            $data['logo_fne_path'] = $request->file('logo_fne')->store('logos/entreprises', 'public');
        }

        $ancien = $entreprise->only(array_keys($data));
        $entreprise->update($data);
        $this->journaliser('modification_parametres', 'Entreprise', $entreprise->id, $ancien, $data);

        // ── Liaison a posteriori si la clé a changé et est remplie ──
        $messageSync = '';
        if ($syncKeyChanged) {
            $syncResult = \App\Modules\Admin\Services\ComptabiliteService::synchroniserDepuisComptaflow($entreprise);
            if ($syncResult['success']) {
                $messageSync = ' Liaison COMPTAFLOW établie avec succès ! Plan comptable, codes journaux et tiers synchronisés.';
            } else {
                $entreprise->update(['comptaflow_sync_status' => 'failed']);
                $messageSync = ' ⚠️ Échec de la liaison COMPTAFLOW : ' . $syncResult['message'];
            }
        }

        return back()->with('succes', 'Paramètres de l\'entreprise mis à jour avec succès.' . $messageSync);
    }

    /**
     * Changer de période active.
     */
    public function switchPeriode(Request $request): RedirectResponse
    {
        $request->validate([
            'periode_id' => ['required', 'integer', 'exists:periodes,id'],
        ]);

        $entreprise = Auth::user()->entreprise;
        $periode = \App\Modules\Admin\Modeles\Periode::where('entreprise_id', $entreprise->id)
            ->findOrFail($request->periode_id);

        session([
            'active_periode_id'    => $periode->id,
            'active_periode_nom'   => $periode->nom,
            'active_periode_debut' => $periode->date_debut instanceof \Carbon\Carbon ? $periode->date_debut->toDateString() : (is_string($periode->date_debut) ? $periode->date_debut : \Carbon\Carbon::parse($periode->date_debut)->toDateString()),
            'active_periode_fin'   => $periode->date_fin instanceof \Carbon\Carbon ? $periode->date_fin->toDateString() : (is_string($periode->date_fin) ? $periode->date_fin : \Carbon\Carbon::parse($periode->date_fin)->toDateString()),
        ]);

        return back()->with('succes', "Exercice basculé sur {$periode->nom}.");
    }

    /**
     * Créer manuellement une période.
     */
    public function creerPeriode(Request $request): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;

        $request->validate([
            'date_debut' => ['required', 'date'],
            'date_fin'   => ['required', 'date', 'after_or_equal:date_debut'],
        ], [
            'date_debut.required' => 'La date de début est requise.',
            'date_fin.required'   => 'La date de fin est requise.',
            'date_fin.after_or_equal' => 'La date de fin doit être après ou égale à la date de début.',
        ]);

        $year = date('Y', strtotime($request->date_debut));
        $nom = "Exercice " . $year;

        // Si c'est déjà utilisé, on peut l'appeler Période Année ou Exercice Année
        // Par exemple: Exercice 2026
        // Créer la période
        $period = \App\Modules\Admin\Modeles\Periode::create([
            'entreprise_id' => $entreprise->id,
            'nom'           => $nom,
            'date_debut'    => $request->date_debut,
            'date_fin'      => $request->date_fin,
            'est_active'    => false,
        ]);
        $this->journaliser('creation_exercice', 'Periode', $period->id, null, $period->toArray());
 
        return back()->with('succes', "La période « {$nom} » a été créée avec succès.");
    }

    /**
     * Clôturer un exercice comptable (période).
     */
    public function cloturerPeriode(\App\Modules\Admin\Modeles\Periode $periode): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;
        abort_unless($periode->entreprise_id === $entreprise->id, 403);

        // Si la période à clôturer est la période active en session, on la retire
        if (session('active_periode_id') == $periode->id) {
            session()->forget([
                'active_periode_id',
                'active_periode_nom',
                'active_periode_debut',
                'active_periode_fin',
            ]);
        }

        $ancien = $periode->toArray();
        $periode->update([
            'est_cloture' => true,
            'est_active'  => false,
        ]);

        $this->journaliser('cloture_exercice', 'Periode', $periode->id, $ancien, $periode->toArray());

        return back()->with('succes', "L'exercice « {$periode->nom} » a été clôturé définitivement.");
    }

    /**
     * Simuler une synchronisation bidirectionnelle avec COMPTAFLOW.
     */
    public function simulerSyncComptaflow(Request $request): \Illuminate\Http\JsonResponse
    {
        $entreprise = Auth::user()->entreprise;

        if (empty($entreprise->comptaflow_sync_key)) {
            return response()->json([
                'success' => false,
                'message' => "La clé de synchronisation n'est pas configurée. Veuillez renseigner une clé valide.",
            ]);
        }

        // Simuler la synchronisation
        $entreprise->update([
            'comptaflow_sync_status'  => 'Actif',
            'comptaflow_last_sync_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => "Synchronisation bidirectionnelle réussie avec COMPTAFLOW ! Les écritures comptables et les statuts des factures ont été synchronisés avec succès.",
            'last_sync' => now()->format('d/m/Y \à H:i:s'),
        ]);
    }

    /**
     * Effectue une synchronisation réelle depuis COMPTAFLOW.
     */
    public function synchroniserComptaflow(Request $request): \Illuminate\Http\JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $result = \App\Modules\Admin\Services\ComptabiliteService::synchroniserDepuisComptaflow($entreprise);
        
        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => $result['message'],
                'last_sync' => now()->format('d/m/Y \à H:i:s'),
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['message'],
        ]);
    }
}
