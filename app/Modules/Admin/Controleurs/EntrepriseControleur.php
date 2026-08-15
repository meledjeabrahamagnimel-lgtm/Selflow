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
use App\Modules\Admin\Regles\Appartenance;

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

        // Statut FNE uniquement — jamais la clé elle-même (voir SuperadminFneControleur
        // pour la gestion des clés, réservée au superadmin).
        $fneCredential = $entreprise->fneCredential;
        $fneStatut = [
            'statut'       => $fneCredential?->statut ?? 'non_configure',
            'label'        => $fneCredential?->statutLabel() ?? 'Non connecté',
            'derniere_verification' => $fneCredential?->derniere_verification_at,
            'derniere_verification_resultat' => $fneCredential?->derniere_verification_resultat,
        ];

        return view('admin::entreprise.parametres', compact('entreprise', 'periodes', 'fneStatut'));
    }

    /**
     * Enregistrer les modifications des paramètres de l'entreprise.
     */
    public function enregistrerParametres(Request $request): RedirectResponse
    {
        $entreprise = Auth::user()->entreprise;

        // Normaliser le NCC : suppression des espaces et mise en majuscule
        $request->merge(['ncc' => $request->has('ncc') ? strtoupper(preg_replace('/\s+/', '', $request->input('ncc'))) : null]);

        // Mentions transmises à la FNE : un copier-coller depuis un document
        // apporte des retours à la ligne et des espaces multiples qui font
        // dépasser la limite pour rien. On les ramène à une seule ligne, ce que
        // la plateforme attend de toute façon.
        foreach (['pied_de_page_facture', 'facture_autres_mentions'] as $champ) {
            if ($request->filled($champ)) {
                $request->merge([
                    $champ => trim(preg_replace('/\s+/u', ' ', $request->input($champ))),
                ]);
            }
        }

        $request->validate([
            'nom'                    => ['required', 'string', 'max:150'],
            'gerant_nom'             => ['nullable', 'string', 'max:100'],
            'gerant_prenom'          => ['nullable', 'string', 'max:150'],
            'gerant_fonction'        => ['nullable', 'string', 'max:150'],
            'adresse'                => ['nullable', 'string', 'max:255'],
            'telephone'              => ['nullable', 'string', 'max:30'],
            'email'                  => ['nullable', 'email', 'max:150'],
            'ref_bancaire'           => ['nullable', 'string', 'max:1000'],
            'logo'                   => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'logo_fne'               => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'comptaflow_sync_key'    => ['nullable', 'string', 'max:255'],
            // Nouveaux champs d'inscription complète dégrisés
            'rccm'                   => ['nullable', 'string', 'max:100'],
            'ncc'                    => ['nullable', 'string', 'size:8', 'regex:/^[A-Z0-9]{7}[A-Z]$/'],
            'regime_imposition'      => ['nullable', 'string', 'in:TEE,RNE,RSI,RNI'],
            'centre_impots'          => ['nullable', 'string', 'max:100'],
            'compte_contribuable'    => ['nullable', 'string', 'max:100'],
            'secteurs_activite'      => ['nullable', 'array'],
            'secteurs_activite.*'    => ['nullable', 'string', 'max:60'],
            // Champs DGI
            'idu'                    => ['nullable', 'string', 'max:50'],
            'reference_cadastrale'   => ['nullable', 'string', 'max:100'],
            'proprietaire_local'     => ['nullable', 'string', 'max:150'],
            'commune'                => ['nullable', 'string', 'max:100'],
            'quartier'               => ['nullable', 'string', 'max:100'],
            'sticker_solde_alerte'   => ['nullable', 'integer', 'min:1', 'max:9999'],
            'possede_compte_fne'     => ['nullable', 'in:0,1'],
            // 248 caractères : longueur maximale acceptée par la FNE pour les
            // champs `footer` et `commercialMessage`. Le texte est ramené à une
            // seule ligne juste avant (voir normaliserMentions) : un copier-
            // coller depuis un document apporte sinon des retours à la ligne et
            // des espaces multiples qui font dépasser la limite pour rien.
            'pied_de_page_facture'   => ['nullable', 'string', 'max:248'],
            'facture_autres_mentions'=> ['nullable', 'string', 'max:248'],
            // La forme des numeros de tiers. Une valeur inconnue laisserait le
            // service sans instruction devant la prochaine fiche creee.
            'numerotation_tiers'     => ['nullable', 'string', 'in:' . implode(',', array_keys(\App\Modules\Admin\Services\NumerotationTiersService::CONVENTIONS))],
        ], [
            'pied_de_page_facture.max'    => 'Le pied de page ne peut pas dépasser 248 caractères.',
            'facture_autres_mentions.max' => 'Les autres mentions ne peuvent pas dépasser 248 caractères.',
            'ncc.size'                    => 'Le NCC doit comporter exactement 8 caractères.',
            'ncc.regex'                   => 'Le NCC doit être composé de 7 chiffres ou lettres suivis d\'une lettre.',
        ]);

        $data = $request->only([
            'nom', 'gerant_nom', 'gerant_prenom', 'gerant_fonction',
            'adresse', 'telephone', 'email', 'ref_bancaire', 'comptaflow_sync_key',
            'rccm', 'ncc', 'regime_imposition', 'centre_impots', 'compte_contribuable',
            // Champs DGI
            'idu', 'reference_cadastrale', 'proprietaire_local', 'commune', 'quartier',
            'sticker_solde_alerte', 'pied_de_page_facture', 'facture_autres_mentions',
        ]);

        // Secteurs d'activité
        $data['secteur_activite'] = $request->secteurs_activite ?? [];


        // Checkboxes (non transmises si non cochées)
        $data['timbre_quittance'] = $request->boolean('timbre_quittance');

        // Trois etats : la question peut n'avoir pas encore ete posee.
        if ($request->filled('possede_compte_fne')) {
            $data['possede_compte_fne'] = $request->input('possede_compte_fne') === '1';
        }
        $data['bapa']             = $request->boolean('bapa');

        // Quand certifier : des l'emission, ou a la main apres verification.
        $data['normalisation_auto_factures'] = $request->boolean('normalisation_auto_factures');
        $data['normalisation_auto_recus']    = $request->boolean('normalisation_auto_recus');

        // La convention de numerotation des tiers. Absente du formulaire, elle
        // ne change pas : les fiches deja creees gardent leur numero, et rien
        // ne justifie de basculer la suivante sans qu'on l'ait demande.
        if ($request->filled('numerotation_tiers')) {
            $data['numerotation_tiers'] = $request->input('numerotation_tiers');
        }

        $syncKeyChanged =$request->filled('comptaflow_sync_key') && ($request->comptaflow_sync_key !== $entreprise->comptaflow_sync_key);

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
            'periode_id' => ['required', 'integer', Appartenance::a('periodes', 'id')],
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
        abort_unless($periode->entreprise_id === $entreprise->id, 404);

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

    /**
     * Vérifie la joignabilité de l'API FNE avec la clé active de l'entreprise
     * (test ou réelle selon le statut). Ne révèle JAMAIS la clé — la lecture
     * de la clé pour l'appel se fait côté serveur uniquement.
     *
     * NB : ceci est un contrôle de JOIGNABILITÉ réseau conservateur (le
     * serveur DGI répond-il ?), pas une validation complète d'authentification
     * — on évite volontairement de soumettre une facture de test réelle ici
     * pour ne jamais risquer d'émettre un document fiscal par erreur en
     * environnement de production. La validation complète de la clé se fait
     * naturellement via la normalisation d'une vraie facture de test.
     */
    public function testerConnexionFne(): \Illuminate\Http\JsonResponse
    {
        $entreprise = Auth::user()->entreprise;
        $cred = $entreprise->fneCredential;

        if (!$cred || !$cred->estConfiguree()) {
            return response()->json(['success' => false, 'message' => "Aucune clé FNE n'est configurée pour votre entreprise. Contactez votre administrateur Selflow."]);
        }

        $urlSandbox = config('selflow.fne_api_url_sandbox', 'https://fne-sandbox.dgi.gouv.ci');
        $urlProd    = config('selflow.fne_api_url_production', 'https://fne.dgi.gouv.ci');
        $url = $cred->statut === 'validee' ? $urlProd : $urlSandbox;

        $resultat = 'echec';
        $message = "❌ Impossible de joindre le serveur DGI ({$url}).";

        try {
            $reponse = Http::timeout(6)->get($url);
            // Peu importe le code retourné (401/403 inclus) : si on obtient une
            // réponse HTTP, le serveur est joignable — c'est tout ce qu'on teste ici.
            $resultat = 'succes';
            $message = "✅ Serveur DGI joignable (" . ($cred->statut === 'validee' ? 'production' : 'sandbox') . ").";
        } catch (\Throwable $e) {
            Log::warning("[FNE] Test de connexion échoué pour l'entreprise #{$entreprise->id} : " . $e->getMessage());
        }

        $cred->update([
            'derniere_verification_resultat' => $resultat,
            'derniere_verification_at' => now(),
        ]);

        return response()->json(['success' => $resultat === 'succes', 'message' => $message]);
    }

    /**
     * Enregistrer rapidement le nom de l'entreprise (onboarding Google).
     */
    public function enregistrerNomOnboarding(Request $request): RedirectResponse
    {
        $request->validate([
            'nom_entreprise' => ['required', 'string', 'max:150'],
        ], [
            'nom_entreprise.required' => 'Le nom de votre entreprise est obligatoire.',
        ]);

        $user = Auth::user();
        if ($user && $user->entreprise) {
            $user->entreprise->update([
                'nom' => trim($request->nom_entreprise)
            ]);
            
            // Log de l'action
            $this->journaliser('onboarding_entreprise_nom', 'Entreprise', $user->entreprise->id, null, [
                'nouveau_nom'   => trim($request->nom_entreprise)
            ]);


            return redirect()->back()->with('succes', 'Le nom de votre entreprise a été enregistré avec succès !');
        }

        return redirect()->back()->withErrors(['nom_entreprise' => 'Action impossible.']);
    }
}

