<?php

/**
 * COMPTAFLOW — contrôleur à créer.
 *
 * Emplacement suggéré : app/Http/Controllers/Api/ExternalCompanyController.php
 *
 * Trois points d'entrée :
 *
 *  - `provision` : Selflow demande l'ouverture d'un dossier, Comptaflow le
 *    crée et **génère la clé** ;
 *  - `revoke`    : Selflow délie, la clé cesse d'ouvrir quoi que ce soit ;
 *  - `verify`    : Selflow vérifie qu'une liaison répond encore.
 *
 * `provision` est le seul appel authentifié par le **secret serveur** seul :
 * il n'y a pas encore de clé à présenter. Les deux autres portent le secret
 * **et** l'en-tête `X-Company-Key`.
 *
 * // À VÉRIFIER : le modèle `Company`, ses colonnes obligatoires, et la façon
 * // dont Comptaflow crée un utilisateur administrateur pour un dossier.
 * // Les deux endroits concernés portent un `// À VÉRIFIER`.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ExternalCompanyController extends Controller
{
    /**
     * Ouvrir un dossier comptable pour une entreprise Selflow.
     *
     * POST /api/external/companies/provision
     *
     * Idempotent : rappelé pour une entreprise déjà provisionnée, il renvoie
     * la même clé plutôt que d'ouvrir un second dossier. Sans cela, une
     * validation cliquée deux fois — ou rejouée après un délai réseau —
     * créerait deux livres pour la même entreprise, et les écritures se
     * partageraient entre eux.
     */
    public function provision(Request $request): JsonResponse
    {
        if (!$this->secretValide($request)) {
            Log::warning('Provisionnement Selflow : secret invalide', ['ip' => $request->ip()]);

            return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 401);
        }

        $validator = Validator::make($request->all(), [
            'selflow_company_id'          => ['required', 'integer', 'min:1'],
            'entreprise.nom'              => ['required', 'string', 'max:150'],
            'entreprise.ncc'              => ['nullable', 'string', 'max:50'],
            'entreprise.rccm'             => ['nullable', 'string', 'max:100'],
            'entreprise.regime_imposition' => ['nullable', 'string', 'max:80'],
            'entreprise.forme_juridique'  => ['nullable', 'string', 'max:60'],
            'entreprise.adresse'          => ['nullable', 'string', 'max:255'],
            'entreprise.telephone'        => ['nullable', 'string', 'max:30'],
            'entreprise.email'            => ['nullable', 'email', 'max:150'],
            'entreprise.admin_nom'        => ['nullable', 'string', 'max:150'],
            'entreprise.admin_email'      => ['nullable', 'email', 'max:150'],
            'numerotation_tiers'          => ['nullable', 'string', 'max:20'],
            'longueur_tiers'              => ['nullable', 'integer', 'min:3', 'max:12'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $identite = $request->input('entreprise');

        // Déjà provisionnée : on rend la même clé. Voir le commentaire de
        // méthode — deux dossiers pour une entreprise partageraient ses
        // écritures entre deux livres.
        $existante = Company::where('selflow_company_id', $request->selflow_company_id)->first();

        if ($existante && $existante->selflow_sync_key && !$existante->selflow_sync_key_revoked_at) {
            return response()->json([
                'success'    => true,
                'company_id' => $existante->id,
                'sync_key'   => $existante->selflow_sync_key,
                'message'    => 'Dossier déjà ouvert ; la clé existante est renvoyée.',
            ]);
        }

        DB::beginTransaction();

        try {
            $entreprise = $existante ?? new Company();

            // À VÉRIFIER : les colonnes réelles de `companies`, et celles qui
            // sont `NOT NULL` sans valeur par défaut.
            $entreprise->fill([
                'company_name'        => $identite['nom'],
                'juridique_form'      => $identite['forme_juridique'] ?? null,
                'ncc'                 => $identite['ncc'] ?? null,
                'compte_contribuable' => $identite['ncc'] ?? null,
                'rccm'                => $identite['rccm'] ?? null,
                'regime'              => $identite['regime_imposition'] ?? null,
                'adresse'             => $identite['adresse'] ?? null,
                'phone_number'        => $identite['telephone'] ?? null,
                'email_adresse'       => $identite['email'] ?? null,

                // La convention de numérotation des tiers doit être la même
                // des deux côtés : la passerelle retrouve un tiers par son
                // numéro exact, et deux longueurs différentes font retomber
                // chaque écriture sur son compte collectif. Selflow numérote
                // sur 6 caractères, préfixe compris.
                'tier_digits'         => (int) $request->input('longueur_tiers', 6),

                'selflow_company_id'  => $request->selflow_company_id,
                'selflow_linked_at'   => now(),
            ]);

            // La clé est générée **ici**, et nulle part ailleurs. Selflow en
            // inventait une (`Str::random(40)`) et la déclarait clé de
            // liaison : Comptaflow devait l'accepter sur parole.
            //
            // 40 caractères tirés de l'aléatoire cryptographique de Laravel.
            // Le préfixe n'est pas décoratif : il permet de reconnaître une
            // clé de liaison dans un journal ou un presse-papiers, donc de
            // savoir qu'on tient un secret.
            $cle = 'cptf_live_' . Str::random(40);

            $entreprise->selflow_sync_key = $cle;
            $entreprise->selflow_sync_key_revoked_at = null;
            $entreprise->save();

            // À VÉRIFIER : la création du compte administrateur du dossier.
            //
            // Selflow ne transmet **pas** de mot de passe, et c'est délibéré :
            // l'ancienne route `register-enterprise` faisait choisir par le
            // superadministrateur Selflow le mot de passe du compte d'un
            // client, et le transportait en clair. Comptaflow crée le compte
            // sans mot de passe utilisable et envoie son propre lien
            // d'activation à `admin_email`.
            //
            // $this->ouvrirLeCompteAdministrateur($entreprise, $identite);

            DB::commit();

            Log::info('Dossier Comptaflow ouvert depuis Selflow', [
                'company_id'         => $entreprise->id,
                'selflow_company_id' => $request->selflow_company_id,
            ]);

            return response()->json([
                'success'    => true,
                'company_id' => $entreprise->id,
                'sync_key'   => $cle,
                'message'    => 'Dossier ouvert.',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Provisionnement Selflow : ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Erreur interne : ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Révoquer la clé d'un dossier.
     *
     * POST /api/external/companies/revoke
     *
     * La clé est **datée révoquée, pas effacée** : un appel refusé doit
     * pouvoir dire « clé révoquée le 12/03 » plutôt que « clé inconnue ».
     * Le dossier comptable, lui, ne bouge pas — délier n'est pas supprimer,
     * et les écritures déjà reçues restent la comptabilité de l'entreprise.
     */
    public function revoke(Request $request): JsonResponse
    {
        if (!$this->secretValide($request)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 401);
        }

        // Le middleware `cle.entreprise` a déjà résolu le dossier depuis
        // l'en-tête, et vérifié qu'il correspond au corps.
        $entreprise = $request->attributes->get('entreprise_liee');

        if (!$entreprise) {
            return response()->json(['success' => false, 'message' => 'Aucune liaison à révoquer.'], 404);
        }

        $entreprise->selflow_sync_key_revoked_at = now();
        $entreprise->save();

        Log::info('Clé de liaison Selflow révoquée', ['company_id' => $entreprise->id]);

        return response()->json(['success' => true, 'message' => 'Clé révoquée.']);
    }

    /**
     * La liaison répond-elle encore ?
     *
     * POST /api/external/companies/verify
     *
     * Le bouton « vérifier la liaison » de Selflow se contentait d'écrire la
     * date du jour et d'annoncer « Liaison active » sans interroger personne.
     * Il interroge maintenant ce point d'entrée.
     */
    public function verify(Request $request): JsonResponse
    {
        if (!$this->secretValide($request)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 401);
        }

        $entreprise = $request->attributes->get('entreprise_liee');

        if (!$entreprise) {
            return response()->json(['success' => false, 'message' => 'Liaison inconnue.'], 404);
        }

        return response()->json([
            'success'      => true,
            'company_id'   => $entreprise->id,
            'company_name' => $entreprise->company_name,
            'linked_at'    => $entreprise->selflow_linked_at,
            'last_deposit' => $entreprise->selflow_last_deposit_at,
        ]);
    }

    /**
     * Le secret serveur est-il celui attendu ?
     *
     * `hash_equals` compare en temps constant. Un `!==` s'arrête au premier
     * caractère différent : le temps de réponse laisse alors deviner le secret
     * caractère par caractère. Le défaut existait des deux côtés ; il est
     * corrigé côté Selflow depuis le lot 5.
     *
     * Et un secret absent refuse tout : une valeur de repli écrite dans le
     * code est un secret public dès que le dépôt l'est.
     */
    private function secretValide(Request $request): bool
    {
        $fourni  = $request->input('secret') ?? $request->header('X-Sync-Secret');
        $attendu = config('services.selflow.sync_secret') ?? env('EXTERNAL_SYNC_SECRET');

        if (blank($attendu) || blank($fourni)) {
            return false;
        }

        return hash_equals((string) $attendu, (string) $fourni);
    }
}
