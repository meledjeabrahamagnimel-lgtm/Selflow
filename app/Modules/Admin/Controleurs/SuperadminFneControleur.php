<?php

namespace App\Modules\Admin\Controleurs;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\FneCredential;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Gestion superadmin des clés API FNE (Facture Normalisée Électronique / DGI).
 *
 * Règle de sécurité (voir /PLAN/FNE-gestion-des-cles.md) : toute action de
 * consultation en clair, modification ou suppression d'une clé exige la
 * re-saisie du mot de passe du superadmin CONNECTÉ dans la même requête
 * (pas de "déverrouillage" de session qui resterait actif pour d'autres
 * actions). L'ajout initial d'une clé (elle n'existe pas encore) n'exige
 * pas de mot de passe — il n'y a rien de sensible à protéger avant qu'elle
 * n'existe en base.
 */
class SuperadminFneControleur extends Controller
{
    /**
     * Tableau de bord FNE : liste des entreprises avec leur statut
     * (jamais les clés elles-mêmes tant qu'elles ne sont pas explicitement révélées).
     */
    public function index()
    {
        $entreprises = Entreprise::with('fneCredential')
            ->orderBy('nom')
            ->get()
            ->map(function ($e) {
                $cred = $e->fneCredential;
                return [
                    'entreprise'   => $e,
                    'credential'   => $cred,
                    'statut'       => $cred?->statut ?? 'non_configure',
                    'statut_label' => $cred?->statutLabel() ?? 'Non connecté',
                    'cle_test_masquee'   => $cred ? FneCredential::masquer($cred->cle_test) : '—',
                    'cle_reelle_masquee' => $cred ? FneCredential::masquer($cred->cle_reelle) : '—',
                ];
            });

        return view('admin::superadmin.fne.index', compact('entreprises'));
    }

    /**
     * Ajouter/mettre à jour la clé de TEST d'une entreprise.
     * Mot de passe requis (comme pour toute action voir/modifier/supprimer
     * sur une clé) — y compris pour la clé de test, par cohérence.
     */
    public function ajouterCleTest(Request $request, Entreprise $entreprise): RedirectResponse
    {
        $request->validate([
            'cle_test'     => ['required', 'string', 'min:8', 'max:500'],
            'ncc_associe'  => ['nullable', 'string', 'max:30'],
            'mot_de_passe' => ['required', 'string'],
        ]);

        if (!Hash::check($request->mot_de_passe, Auth::user()->password)) {
            return back()->with('error', '❌ Mot de passe incorrect. Action annulée.');
        }

        $cred = FneCredential::firstOrNew(['entreprise_id' => $entreprise->id]);
        $cred->cle_test = $request->cle_test;
        $cred->cle_test_ajoutee_at = now();
        $cred->cle_test_ajoutee_par = Auth::id();
        $cred->ncc_associe = $request->ncc_associe ?: $cred->ncc_associe;
        if ($cred->statut === 'non_configure' || empty($cred->statut)) {
            $cred->statut = 'test';
        }
        $cred->save();

        Log::info("[FNE] Clé de test ajoutée/modifiée pour l'entreprise #{$entreprise->id} par l'utilisateur #" . Auth::id());

        return back()->with('success', "✅ Clé de test FNE enregistrée pour « {$entreprise->nom} ».");
    }

    /**
     * Ajouter/mettre à jour la clé RÉELLE (production) d'une entreprise.
     * Mot de passe requis : bascule l'entreprise en émission de factures
     * fiscalement opposables — action à haut risque si mal utilisée.
     */
    public function ajouterCleReelle(Request $request, Entreprise $entreprise): RedirectResponse
    {
        $request->validate([
            'cle_reelle'    => ['required', 'string', 'min:8', 'max:500'],
            'mot_de_passe'  => ['required', 'string'],
        ]);

        if (!Hash::check($request->mot_de_passe, Auth::user()->password)) {
            return back()->with('error', '❌ Mot de passe incorrect. Action annulée.');
        }

        $cred = FneCredential::firstOrNew(['entreprise_id' => $entreprise->id]);
        $cred->cle_reelle = $request->cle_reelle;
        $cred->cle_reelle_ajoutee_at = now();
        $cred->cle_reelle_ajoutee_par = Auth::id();
        $cred->statut = 'validee';

        // PURGE DES DONNÉES DE TEST DE L'ENTREPRISE (passage en production)
        $pdvIds = \App\Modules\Admin\Modeles\PointDeVente::where('entreprise_id', $entreprise->id)->pluck('id')->toArray();

        \Illuminate\Support\Facades\DB::transaction(function() use ($pdvIds, $entreprise, $cred) {
            // Réinitialiser le solde stickers pour la production
            $entreprise->update(['fne_sticker_balance' => 0]);

            if (!empty($pdvIds)) {
                // 1. Découpler les relations parentes pour éviter les restrictions de clés étrangères
                \Illuminate\Support\Facades\DB::table('ventes')->whereIn('point_de_vente_id', $pdvIds)->update(['parent_id' => null, 'bon_livraison_id' => null]);
                \Illuminate\Support\Facades\DB::table('achats')->whereIn('point_de_vente_id', $pdvIds)->update(['parent_id' => null, 'b2b_negotiation_id' => null]);
                \Illuminate\Support\Facades\DB::table('bons_livraison')->whereIn('point_de_vente_id', $pdvIds)->update(['facture_vente_id' => null]);

                // 2. Supprimer les lignes et bons de livraison
                \Illuminate\Support\Facades\DB::table('bon_livraison_details')->whereIn('bon_livraison_id', function($q) use ($pdvIds) {
                    $q->select('id')->from('bons_livraison')->whereIn('point_de_vente_id', $pdvIds);
                })->delete();
                \Illuminate\Support\Facades\DB::table('bons_livraison')->whereIn('point_de_vente_id', $pdvIds)->delete();

                // 3. Supprimer les lignes de vente et d'achat
                \Illuminate\Support\Facades\DB::table('vente_details')->whereIn('vente_id', function($q) use ($pdvIds) {
                    $q->select('id')->from('ventes')->whereIn('point_de_vente_id', $pdvIds);
                })->delete();
                \Illuminate\Support\Facades\DB::table('achat_details')->whereIn('achat_id', function($q) use ($pdvIds) {
                    $q->select('id')->from('achats')->whereIn('point_de_vente_id', $pdvIds);
                })->delete();

                // 4. Supprimer les ventes, achats et transactions de stickers fictives
                \Illuminate\Support\Facades\DB::table('ventes')->whereIn('point_de_vente_id', $pdvIds)->delete();
                \Illuminate\Support\Facades\DB::table('achats')->whereIn('point_de_vente_id', $pdvIds)->delete();
                \Illuminate\Support\Facades\DB::table('sticker_transactions')->where('entreprise_id', $entreprise->id)->delete();

                // 5. Supprimer les mouvements de stock, ordres de production, transferts, fiches techniques, stocks et produits
                \Illuminate\Support\Facades\DB::table('mouvements_stock')->whereIn('point_de_vente_id', $pdvIds)->delete();
                \Illuminate\Support\Facades\DB::table('transferts_stock')
                    ->whereIn('point_de_vente_source_id', $pdvIds)
                    ->orWhereIn('point_de_vente_destination_id', $pdvIds)
                    ->delete();
                \Illuminate\Support\Facades\DB::table('ordres_production')->whereIn('point_de_vente_id', $pdvIds)->delete();
                
                // Supprimer les détails puis les fiches techniques de l'entreprise
                \Illuminate\Support\Facades\DB::table('fiche_technique_details')->whereIn('fiche_technique_id', function($q) use ($entreprise) {
                    $q->select('id')->from('fiches_techniques')->where('entreprise_id', $entreprise->id);
                })->delete();
                \Illuminate\Support\Facades\DB::table('fiches_techniques')->where('entreprise_id', $entreprise->id)->delete();
                
                // Supprimer le stock physique et enfin les produits de test de l'entreprise
                \Illuminate\Support\Facades\DB::table('stocks')->whereIn('point_de_vente_id', $pdvIds)->delete();
                \Illuminate\Support\Facades\DB::table('produits')->where('entreprise_id', $entreprise->id)->delete();

                // 6. Supprimer le journal de trésorerie (caisse/banque)
                \Illuminate\Support\Facades\DB::table('tresorerie_journal')->whereIn('point_de_vente_id', $pdvIds)->delete();

                // 7. Supprimer les écritures comptables associées
                \Illuminate\Support\Facades\DB::table('ecritures_comptables')->whereIn('point_de_vente_id', $pdvIds)->delete();

                // 8. Supprimer les négociations B2B (RFQ/Commandes)
                \Illuminate\Support\Facades\DB::table('b2b_negotiations')
                    ->where('entreprise_client_id', $entreprise->id)
                    ->orWhere('entreprise_fournisseur_id', $entreprise->id)
                    ->delete();
            }

            $cred->save();
        });

        Log::warning("[FNE] Clé RÉELLE (production) activée et base de données purgée pour l'entreprise #{$entreprise->id} par l'utilisateur #" . Auth::id());

        return back()->with('success', "✅ Clé réelle FNE activée pour « {$entreprise->nom} ». La base de données de test a été purgée avec succès, l'entreprise est prête pour la production.");
    }

    /**
     * Révéler une clé en clair (test ou réelle). Mot de passe requis.
     * Réponse JSON éphémère : la clé n'est jamais injectée dans le HTML de
     * la page (pas de "afficher/masquer" côté client sur une valeur déjà
     * présente dans le DOM) — elle n'existe dans le navigateur qu'après
     * cette confirmation explicite, et seulement en mémoire JS.
     */
    public function voirCle(Request $request, Entreprise $entreprise): JsonResponse
    {
        $request->validate([
            'mot_de_passe' => ['required', 'string'],
            'type'         => ['required', 'in:test,reelle'],
        ]);

        if (!Hash::check($request->mot_de_passe, Auth::user()->password)) {
            return response()->json(['success' => false, 'message' => 'Mot de passe incorrect.'], 403);
        }

        $cred = $entreprise->fneCredential;
        if (!$cred) {
            return response()->json(['success' => false, 'message' => 'Aucune clé enregistrée.'], 404);
        }

        $cle = $request->type === 'test' ? $cred->cle_test : $cred->cle_reelle;
        if (empty($cle)) {
            return response()->json(['success' => false, 'message' => 'Cette clé n\'est pas renseignée.'], 404);
        }

        Log::warning("[FNE] Clé {$request->type} de l'entreprise #{$entreprise->id} consultée en clair par l'utilisateur #" . Auth::id());

        return response()->json(['success' => true, 'cle' => $cle]);
    }

    /**
     * Supprimer une clé (test ou réelle). Mot de passe requis.
     */
    public function supprimerCle(Request $request, Entreprise $entreprise): RedirectResponse
    {
        $request->validate([
            'mot_de_passe' => ['required', 'string'],
            'type'         => ['required', 'in:test,reelle'],
        ]);

        if (!Hash::check($request->mot_de_passe, Auth::user()->password)) {
            return back()->with('error', '❌ Mot de passe incorrect. Action annulée.');
        }

        $cred = $entreprise->fneCredential;
        if (!$cred) {
            return back()->with('error', 'Aucune clé enregistrée pour cette entreprise.');
        }

        if ($request->type === 'test') {
            $cred->cle_test = null;
            $cred->cle_test_ajoutee_at = null;
            $cred->cle_test_ajoutee_par = null;
        } else {
            $cred->cle_reelle = null;
            $cred->cle_reelle_ajoutee_at = null;
            $cred->cle_reelle_ajoutee_par = null;
        }

        // Recalcul du statut après suppression
        if (empty($cred->cle_reelle) && empty($cred->cle_test)) {
            $cred->statut = 'non_configure';
        } elseif (empty($cred->cle_reelle)) {
            $cred->statut = 'test';
        }

        $cred->save();

        Log::warning("[FNE] Clé {$request->type} de l'entreprise #{$entreprise->id} supprimée par l'utilisateur #" . Auth::id());

        return back()->with('success', "🗑️ Clé {$request->type} supprimée pour « {$entreprise->nom} ».");
    }

    /**
     * Mettre à jour les notes libres superadmin (suivi de la demande DGI, etc.)
     * Pas de mot de passe requis : ce ne sont pas des données sensibles.
     */
    public function mettreAJourNotes(Request $request, Entreprise $entreprise): RedirectResponse
    {
        $request->validate(['notes_superadmin' => ['nullable', 'string', 'max:2000']]);

        $cred = FneCredential::firstOrNew(['entreprise_id' => $entreprise->id]);
        $cred->notes_superadmin = $request->notes_superadmin;
        $cred->save();

        return back()->with('success', 'Notes mises à jour.');
    }
}
