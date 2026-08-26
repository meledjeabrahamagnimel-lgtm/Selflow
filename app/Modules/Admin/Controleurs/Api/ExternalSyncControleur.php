<?php

namespace App\Modules\Admin\Controleurs\Api;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Modules\Admin\Services\TrousseauEntrepriseService;

/**
 * ExternalSyncControleur
 * Endpoint API dédié à la liaison COMPTAFLOW ↔ Selflow.
 * Crée une entreprise + un utilisateur admin depuis une requête externe.
 */
class ExternalSyncControleur
{
    /**
     * Crée une entreprise Selflow depuis une requête externe (ex : COMPTAFLOW).
     * POST /api/external/register-enterprise
     */
    public function enregistrerEntreprise(Request $request): JsonResponse
    {
        // ── Vérification du secret partagé ──
        $providedSecret = $request->input('secret') ?? $request->header('X-Sync-Secret');

        if (!self::secretValide($providedSecret)) {
            Log::warning('ExternalSync Selflow: secret invalide', ['ip' => $request->ip()]);
            return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 401);
        }

        // ── Validation ──
        $validator = Validator::make($request->all(), [
            'nom'                 => 'required|string|max:150',
            'forme_juridique'     => 'nullable|string|max:50',
            'email'               => 'required|email|max:150',
            'telephone'           => 'nullable|string|max:30',
            'adresse'             => 'nullable|string|max:255',
            'ncc'                 => 'nullable|string|max:50',
            'rccm'                => 'nullable|string|max:100',
            'compte_contribuable' => 'nullable|string|max:100',
            'regime_imposition'   => 'nullable|string|max:80',
            'gerant_nom'          => 'nullable|string|max:100',
            'gerant_prenom'       => 'nullable|string|max:150',
            // Le sens inverse de la liaison : une entreprise qui a Comptaflow
            // et veut Selflow retrouve **les mêmes accès** — même adresse, même
            // mot de passe. Comptaflow envoie l'empreinte de son mot de passe,
            // jamais le mot de passe : l'un des deux suffit, et l'empreinte est
            // préférable.
            'admin_password'      => 'required_without:admin_password_hash|nullable|string|min:8',
            'admin_password_hash' => 'required_without:admin_password|nullable|string|max:255',
            'comptaflow_company_id' => 'nullable|integer',
            'comptaflow_sync_key'   => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        // ── Vérifier unicité ──
        if (Entreprise::where('nom', $request->nom)->exists()) {
            return response()->json(['success' => false, 'message' => 'Une entreprise avec ce nom existe déjà.'], 409);
        }
        if (Utilisateur::where('email', $request->email)->exists()) {
            return response()->json(['success' => false, 'message' => 'Un compte avec cet email existe déjà.'], 409);
        }

        DB::beginTransaction();
        try {
            // 1. Créer l'entreprise
            $entreprise = Entreprise::create([
                'nom'                    => $request->nom,
                'forme_juridique'        => $request->forme_juridique ?? 'SARL',
                'gerant_nom'             => $request->gerant_nom,
                'gerant_prenom'          => $request->gerant_prenom,
                'email'                  => $request->email,
                'telephone'              => $request->telephone,
                'adresse'                => $request->adresse,
                'ncc'                    => $request->ncc,
                'rccm'                   => $request->rccm,
                'compte_contribuable'    => $request->compte_contribuable,
                'regime_imposition'      => $request->regime_imposition,
                'quota_points_de_vente'  => 5,
                'plan_abonnement'        => 'Pro',
                // Comptaflow ne connaît pas le domaine d'activité : le poser au
                // hasard le figerait sur une valeur fausse que personne ne
                // penserait à corriger. « Autre » dit ce qu'il en est, et
                // l'entreprise choisit son vrai domaine à la souscription.
                'secteur_activite'       => [\App\Modules\Admin\Modeles\Referentiel\Categorie::AUTRE],
                'modules_actifs'         => ['principal', 'ventes', 'achats', 'stock', 'tiers', 'produits', 'rapports', 'b2b', 'fne'],
                'comptaflow_company_id'  => $request->comptaflow_company_id,
                'comptaflow_sync_status' => 'active',
                'comptaflow_last_sync_at' => now(),
                'comptaflow_liee_le'     => now(),
                'comptaflow_cle_indice'  => $request->comptaflow_sync_key
                    ? substr((string) $request->comptaflow_sync_key, -4)
                    : null,
            ]);

            // La clé n'est pas `$fillable` : elle ne doit jamais entrer par un
            // tableau de requête. Ici, l'appel vient de Comptaflow lui-même,
            // authentifié par le secret serveur, et c'est lui qui l'a générée.
            // On la pose donc explicitement, hors de l'affectation en masse.
            if ($request->filled('comptaflow_sync_key')) {
                $entreprise->comptaflow_sync_key = $request->comptaflow_sync_key;
                $entreprise->save();
            }

            // Sans plan comptable ni journal, la premiere vente s'impute sur des
            // comptes inventes a la volee. L'entreprise recoit donc de quoi
            // travailler des le premier jour ; ce qui ne lui sert pas, elle
            // l'archivera.
            TrousseauEntrepriseService::doter($entreprise);

            // 2. Créer l'utilisateur admin
            $utilisateur = Utilisateur::create([
                'nom'           => $request->gerant_nom ?? 'Admin',
                'prenom'        => $request->gerant_prenom ?? '',
                'email'         => $request->email,
                // L'empreinte arrive telle quelle quand Comptaflow l'envoie :
                // la re-hacher rendrait le compte inaccessible avec le mot de
                // passe que l'utilisateur connaît déjà.
                'password'      => $request->filled('admin_password_hash')
                    ? $request->admin_password_hash
                    : Hash::make($request->admin_password),
                'role'          => 'admin',
                'entreprise_id' => $entreprise->id,
                'statut'        => 'actif',
            ]);

            // 3. Créer le point de vente Siège par défaut
            PointDeVente::create([
                'entreprise_id' => $entreprise->id,
                'nom'           => 'Siège',
                'ville'         => $request->adresse ? explode(',', $request->adresse)[0] : 'Abidjan',
                'commune'       => 'Plateau',
                'responsable'   => ($request->gerant_nom ?? 'Admin') . ' ' . ($request->gerant_prenom ?? ''),
                'telephone'     => $request->telephone,
                'statut'        => 'Ouvert',
            ]);

            DB::commit();

            Log::info('ExternalSync Selflow: entreprise créée depuis COMPTAFLOW', [
                'entreprise_id'   => $entreprise->id,
                'entreprise_nom'  => $entreprise->nom,
            ]);

            return response()->json([
                'success'      => true,
                'company_id'   => $entreprise->id,
                'message'      => 'Entreprise et administrateur créés avec succès dans Selflow.',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ExternalSync Selflow: erreur création entreprise', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Erreur interne : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retourne les informations d'une entreprise Selflow (pour affichage dans COMPTAFLOW hub).
     * POST /api/external/company-info
     */
    public function companyInfo(Request $request): JsonResponse
    {
        $providedSecret = $request->input('secret') ?? $request->header('X-Sync-Secret');

        if (!self::secretValide($providedSecret)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'selflow_company_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Données invalides.', 'errors' => $validator->errors()], 422);
        }

        $entreprise = Entreprise::find($request->selflow_company_id);
        if (!$entreprise) {
            return response()->json(['success' => false, 'message' => 'Entreprise introuvable.'], 404);
        }

        // Récupérer l'admin principal
        $admin = Utilisateur::where('entreprise_id', $entreprise->id)
            ->where('role', 'admin')
            ->orderBy('created_at')
            ->first();

        return response()->json([
            'success' => true,
            'company' => [
                'id'                => $entreprise->id,
                'uuid' => $entreprise->uuid,
                'nom'               => $entreprise->nom,
                'rccm'              => $entreprise->rccm ?? null,
                'ncc'               => $entreprise->ncc ?? null,
                'email'             => $entreprise->email ?? null,
                'telephone'         => $entreprise->telephone ?? null,
                'adresse'           => $entreprise->adresse ?? null,
                'regime_imposition' => $entreprise->regime_imposition ?? null,
                'created_at'        => $entreprise->created_at ? $entreprise->created_at->format('d/m/Y') : null,
                'admin_nom'         => $admin ? ($admin->nom . ' ' . $admin->prenom) : null,
                'admin_email'       => $admin ? $admin->email : null,
                'comptaflow_status' => $entreprise->comptaflow_sync_status ?? 'inactive',
            ],
        ]);
    }

    /**
     * Liste toutes les entreprises Selflow (pour le module Liaison SuperAdmin de COMPTAFLOW).
     * POST /api/external/list-companies
     */
    public function listCompanies(Request $request): JsonResponse
    {
        $providedSecret = $request->input('secret') ?? $request->header('X-Sync-Secret');

        if (!self::secretValide($providedSecret)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 401);
        }

        $entreprises = Entreprise::with(['utilisateurs' => function ($q) {
            $q->where('role', 'admin')->orderBy('created_at')->limit(1);
        }])->get()->map(function ($e) {
            $admin = $e->utilisateurs->first();
            return [
                'id'                   => $e->id,
                'uuid' => $e->uuid,
                'nom'                  => $e->nom,
                'email'                => $e->email,
                'telephone'            => $e->telephone,
                'adresse'              => $e->adresse,
                'rccm'                 => $e->rccm,
                'ncc'                  => $e->ncc,
                'regime_imposition'    => $e->regime_imposition,
                'gerant_nom'           => $e->gerant_nom,
                'gerant_prenom'        => $e->gerant_prenom,
                'compte_contribuable'  => $e->compte_contribuable,
                'created_at'           => $e->created_at ? $e->created_at->format('d/m/Y') : null,
                'admin_email'          => $admin ? $admin->email : null,
                'is_linked'            => !empty($e->comptaflow_company_id),
                'comptaflow_status'    => $e->comptaflow_sync_status ?? 'inactive',
            ];
        });

        return response()->json([
            'success'     => true,
            'companies'   => $entreprises,
        ]);
    }

    /**
     * Retourne les détails complets d'un tiers (Client/Fournisseur) pour COMPTAFLOW.
     * POST /api/external/tier-info
     */
    public function tierInfo(Request $request): JsonResponse
    {
        $providedSecret = $request->input('secret') ?? $request->header('X-Sync-Secret');

        if (!self::secretValide($providedSecret)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 401);
        }

        $entrepriseId   = $request->input('selflow_company_id');
        $numeroOriginal = $request->input('numero_original');
        $numeroTiers    = $request->input('numero_de_tiers');
        $intitule       = trim($request->input('intitule', ''));
        $type           = strtolower($request->input('type', ''));

        $tierData = null;
        $isFournisseurPref = str_contains($type, 'fourn') || str_starts_with(strtolower($numeroTiers), '40');

        // 1. Recherche Fournisseur
        $fournisseur = null;
        if ($isFournisseurPref || !$type) {
            if ($numeroOriginal) {
                $fournisseur = \App\Modules\Admin\Modeles\Fournisseur::where('entreprise_id', $entrepriseId)->find($numeroOriginal);
            }
            if (!$fournisseur && $numeroTiers) {
                $fournisseur = \App\Modules\Admin\Modeles\Fournisseur::where('entreprise_id', $entrepriseId)
                    ->where(function($q) use ($numeroTiers) {
                        $q->where('numero_tiers', $numeroTiers)->orWhere('numero_original', $numeroTiers);
                    })->first();
            }
            if (!$fournisseur && $intitule) {
                $fournisseur = \App\Modules\Admin\Modeles\Fournisseur::where('entreprise_id', $entrepriseId)
                    ->where('nom', 'LIKE', "%{$intitule}%")
                    ->first();
            }
        }

        // 2. Recherche Client (si pas trouvé en Fournisseur)
        $client = null;
        if (!$fournisseur) {
            if ($numeroOriginal) {
                $client = \App\Modules\Admin\Modeles\Client::where('entreprise_id', $entrepriseId)->find($numeroOriginal);
            }
            if (!$client && $numeroTiers) {
                $client = \App\Modules\Admin\Modeles\Client::where('entreprise_id', $entrepriseId)
                    ->where(function($q) use ($numeroTiers) {
                        $q->where('numero_tiers', $numeroTiers)->orWhere('numero_original', $numeroTiers);
                    })->first();
            }
            if (!$client && $intitule) {
                $client = \App\Modules\Admin\Modeles\Client::where('entreprise_id', $entrepriseId)
                    ->where('nom', 'LIKE', "%{$intitule}%")
                    ->first();
            }
        }

        if ($fournisseur) {
            $achatsCount = \App\Modules\Admin\Modeles\Achat::whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entrepriseId))
                ->where('fournisseur_id', $fournisseur->id)->count();

            $tierData = [
                'type'                => 'Fournisseur',
                'nom'                 => $fournisseur->nom,
                'ncc'                 => $fournisseur->ncc,
                'rccm'                => $fournisseur->rccm,
                'compte_comptable'    => $fournisseur->compte_comptable,
                'compte_general'      => $fournisseur->compte_comptable,
                'numero_original'     => $fournisseur->numero_original ?? $fournisseur->compte_comptable,
                'numero_tiers'        => $fournisseur->numero_tiers,
                'compte_contribuable' => $fournisseur->ncc,
                'regime'              => $fournisseur->regime_imposition,
                'telephone'           => $fournisseur->telephone,
                'email'               => $fournisseur->email,
                'adresse'             => $fournisseur->adresse,
                'secteur_activite'    => $fournisseur->secteur,
                'nombre_achats'       => $achatsCount,
                'created_at'          => $fournisseur->created_at ? $fournisseur->created_at->format('d/m/Y') : null,
            ];
        } elseif ($client) {
            $ventesCount = \App\Modules\Admin\Modeles\Vente::whereHas('pointDeVente', fn($q) => $q->where('entreprise_id', $entrepriseId))
                ->where('client_id', $client->id)->count();

            $tierData = [
                'type'                => 'Client',
                'nom'                 => $client->nom,
                'ncc'                 => $client->ncc,
                'rccm'                => $client->rccm,
                'compte_comptable'    => $client->compte_comptable,
                'compte_general'      => $client->compte_comptable,
                'numero_original'     => $client->numero_original ?? $client->compte_comptable,
                'numero_tiers'        => $client->numero_tiers,
                'compte_contribuable' => $client->ncc,
                'regime'              => $client->regime_imposition,
                'telephone'           => $client->telephone,
                'email'               => $client->email,
                'adresse'             => $client->adresse,
                'nombre_achats'       => $ventesCount,
                'created_at'          => $client->created_at ? $client->created_at->format('d/m/Y') : null,
            ];
        }

        return response()->json([
            'success' => !empty($tierData),
            'tier'    => $tierData,
        ]);
    }

    /**
     * Le secret partage est-il celui attendu ?
     *
     * Deux points fermes ici. La valeur de repli inscrite dans le code
     * — `selflow-comptaflow-secret-2026` — faisait que, si la variable
     * d'environnement n'etait pas renseignee, le secret de production etait
     * celui du depot, public. Un secret absent refuse desormais tout appel.
     *
     * Et la comparaison se faisait avec `!==`, qui s'arrete au premier
     * caractere different : le temps de reponse laissait deviner le secret
     * caractere par caractere. `hash_equals` compare en temps constant.
     */
    private static function secretValide(?string $fourni): bool
    {
        $attendu = config('selflow.comptaflow_api_secret');

        if (empty($attendu) || empty($fourni)) {
            return false;
        }

        return hash_equals((string) $attendu, (string) $fourni);
    }
}
