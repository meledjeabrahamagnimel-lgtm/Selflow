<?php

namespace App\Modules\Admin\Services;

use App\Mail\CompteComptaflowOuvert;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * La liaison Comptaflow, délivrée et non saisie.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Ce que ce service remplace
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Un champ de texte libre dans les paramètres de l'entreprise, avec la
 * consigne « obtenir depuis Comptaflow → Configuration → Liaison Selflow ».
 * L'entreprise allait chercher sa clé ailleurs et la collait ici ;
 * `EntrepriseControleur` ouvrait la liaison dès que la valeur changeait.
 *
 * Une entreprise qui obtenait la clé d'une autre la collait dans ses propres
 * paramètres, et **son référentiel puis ses écritures partaient dans les
 * livres de l'autre**. Le secret partagé n'y changeait rien : il est détenu
 * par le serveur, part sur tous les appels, et ne dit pas qui appelle. Rien ne
 * vérifiait que la clé saisie désignait celui qui la saisissait.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * La procédure
 * ─────────────────────────────────────────────────────────────────────────
 *
 * 1. L'entreprise demande un dossier comptable. Un bouton, aucun champ.
 * 2. Le superadministrateur voit la demande et vérifie l'identité fiscale —
 *    NCC, RCCM, régime — avant qu'un livre s'ouvre au nom de quelqu'un.
 * 3. À la validation, Selflow appelle Comptaflow avec le secret **serveur**.
 * 4. Comptaflow crée le dossier, **génère la clé**, la renvoie.
 * 5. Selflow la range chiffrée et déverse le référentiel dans la foulée.
 * 6. L'entreprise lit « Liaison active ». Elle n'a rien manipulé.
 *
 * C'est Comptaflow qui génère, et non Selflow : la clé désigne un dossier
 * chez lui, et c'est lui qui doit la reconnaître. Selflow ne fait que la
 * conserver et la présenter.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Ce que la clé change au modèle d'authentification
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Une fois délivrée, **c'est elle qui authentifie chaque déversement**, en
 * en-tête `X-Company-Key`, et Comptaflow vérifie que la clé et le
 * `comptaflow_company_id` du corps désignent le même dossier. Le secret
 * partagé se rétracte alors sur le seul appel de provisionnement, entre
 * serveurs. Un secret volé ne suffit plus à écrire dans les livres de
 * n'importe qui : il faudrait la clé de ce dossier-là.
 *
 * Tant que Comptaflow n'exige pas encore l'en-tête, les deux voyagent
 * ensemble : l'en-tête est ignoré par un Comptaflow qui ne le lit pas, et la
 * bascule se fait de son côté sans que Selflow change.
 */
class LiaisonComptaflowService
{
    /** Combien de temps attendre Comptaflow, en secondes. */
    private const DELAI = 25;

    // ── Ce que l'entreprise fait ─────────────────────────────────────

    /**
     * L'entreprise demande un dossier comptable.
     *
     * @return array{success: bool, message: string}
     */
    public static function demander(Entreprise $entreprise, ?Utilisateur $auteur = null): array
    {
        if ($entreprise->liaisonComptaflowActive()) {
            return ['success' => false, 'message' => 'La liaison Comptaflow est déjà active.'];
        }

        if ($entreprise->demandeComptaflowEnAttente()) {
            return ['success' => false, 'message' => 'Votre demande est déjà enregistrée ; elle attend la validation.'];
        }

        $entreprise->update([
            'comptaflow_demande_statut' => Entreprise::DEMANDE_EN_ATTENTE,
            'comptaflow_demande_le'     => now(),
            'comptaflow_demande_par'    => $auteur?->id,
            // Une nouvelle demande après un refus efface le motif du
            // précédent : le laisser afficherait un refus qui n'a plus cours.
            'comptaflow_refus_motif'    => null,
        ]);

        return [
            'success' => true,
            'message' => 'Demande enregistrée. Vous serez lié dès qu\'elle sera validée ; vous n\'avez aucune clé à saisir.',
        ];
    }

    // ── Ce que le superadministrateur fait ───────────────────────────

    /**
     * Valider la demande : Comptaflow ouvre le dossier et délivre la clé.
     *
     * Rien n'est marqué actif si le provisionnement échoue. La demande reste
     * en attente, et se rejoue : une liaison à moitié ouverte enverrait des
     * écritures qui n'arriveraient nulle part, et le tableau de bord les
     * dirait pourtant déversées.
     *
     * @return array{success: bool, message: string}
     */
    public static function valider(Entreprise $entreprise): array
    {
        if ($entreprise->liaisonComptaflowActive()) {
            return ['success' => false, 'message' => 'Cette entreprise est déjà liée à Comptaflow.'];
        }

        if (!config('selflow.comptaflow_api_secret')) {
            return ['success' => false, 'message' => "Le secret serveur n'est pas configuré (EXTERNAL_SYNC_SECRET)."];
        }

        try {
            $reponse = Http::timeout(self::DELAI)
                ->post(self::url('/api/external/companies/provision'), self::dossier($entreprise));
        } catch (\Throwable $e) {
            Log::error('Provisionnement Comptaflow : ' . $e->getMessage());

            return ['success' => false, 'message' => 'Impossible de joindre Comptaflow : ' . $e->getMessage()];
        }

        // 404 (Not Found — introuvable) et 405 (Method Not Allowed — méthode
        // non autorisée) veulent dire la même chose ici : le Comptaflow d'en
        // face n'a pas encore ce point d'entrée. Le dire tel quel évite de
        // chercher une clé perdue là où il n'y a qu'un déploiement en retard.
        if (in_array($reponse->status(), [404, 405], true)) {
            return [
                'success' => false,
                'message' => "Comptaflow n'expose pas encore le provisionnement des dossiers "
                    . '(/api/external/companies/provision). La demande reste en attente.',
            ];
        }

        $cle = $reponse->json('sync_key');

        if (!$reponse->successful() || !$reponse->json('success') || !$cle) {
            return [
                'success' => false,
                'message' => $reponse->json('message')
                    ?? 'Comptaflow a refusé le provisionnement (code ' . $reponse->status() . ').',
            ];
        }

        // Écriture directe, et non par `update()` : la clé n'est pas dans
        // `$fillable`, précisément pour qu'aucune requête ne puisse l'y
        // glisser. Ce service est le seul endroit qui la pose.
        $entreprise->comptaflow_sync_key = $cle;

        $entreprise->fill([
            'comptaflow_company_id'     => $reponse->json('company_id'),
            'comptaflow_cle_indice'     => substr((string) $cle, -4),
            'comptaflow_sync_status'    => 'active',
            'comptaflow_demande_statut' => Entreprise::DEMANDE_VALIDEE,
            'comptaflow_liee_le'        => now(),
            'comptaflow_revoquee_le'    => null,
            'comptaflow_refus_motif'    => null,
        ])->save();

        // Le référentiel part dans la foulée : sans plan comptable, sans
        // journaux et sans tiers, la première écriture déversée retomberait
        // chez Comptaflow sur des comptes qu'il ne connaît pas.
        $deversement = DeversementReferentielService::deverser($entreprise->fresh());

        self::prevenirLeTitulaire($entreprise->fresh());

        return [
            'success' => true,
            'message' => 'Liaison ouverte. ' . ($deversement['success']
                ? $deversement['message']
                : 'Le référentiel n\'a pas suivi : ' . $deversement['message']),
        ];
    }

    /**
     * Refuser la demande, avec un motif que l'entreprise lit.
     *
     * @return array{success: bool, message: string}
     */
    public static function refuser(Entreprise $entreprise, string $motif): array
    {
        $entreprise->update([
            'comptaflow_demande_statut' => Entreprise::DEMANDE_REFUSEE,
            'comptaflow_refus_motif'    => $motif,
        ]);

        return ['success' => true, 'message' => 'Demande refusée.'];
    }

    /**
     * Délier : la clé est révoquée des deux côtés.
     *
     * L'ancienne version effaçait la clé chez Selflow et n'en disait rien à
     * Comptaflow, où elle continuait d'ouvrir le dossier. Une clé oubliée
     * chez l'autre est une clé qui marche.
     *
     * @return array{success: bool, message: string}
     */
    public static function revoquer(Entreprise $entreprise): array
    {
        $prevenu = false;
        $note = '';

        if (filled($entreprise->comptaflow_sync_key)) {
            try {
                $reponse = Http::timeout(self::DELAI)
                    ->withHeaders(self::enTete($entreprise))
                    ->post(self::url('/api/external/companies/revoke'), [
                        'secret'                => config('selflow.comptaflow_api_secret'),
                        'selflow_company_id'    => $entreprise->id,
                        'comptaflow_company_id' => $entreprise->comptaflow_company_id,
                    ]);

                $prevenu = $reponse->successful() && $reponse->json('success');
            } catch (\Throwable $e) {
                Log::warning('Révocation Comptaflow : ' . $e->getMessage());
            }

            // On efface quand même : garder la clé chez nous ne la rendrait
            // pas plus révoquée là-bas, et laisserait la liaison ouverte ici.
            $note = $prevenu
                ? ' La clé est révoquée chez Comptaflow.'
                : " Comptaflow n'a pas confirmé la révocation : faites-la révoquer sur place.";
        }

        // Comme à la pose : la clé n'est pas `$fillable`, elle s'écrit ici.
        $entreprise->comptaflow_sync_key = null;

        $entreprise->fill([
            'comptaflow_cle_indice'     => null,
            'comptaflow_company_id'     => null,
            'comptaflow_sync_status'    => 'inactive',
            'comptaflow_demande_statut' => null,
            'comptaflow_liee_le'        => null,
            'comptaflow_revoquee_le'    => now(),
            'comptaflow_last_sync_at'   => null,
        ])->save();

        return ['success' => true, 'message' => 'Liaison supprimée.' . $note];
    }

    /**
     * Prévenir celui au nom de qui le dossier vient d'être ouvert.
     *
     * Un compte s'ouvrait chez une autre application au nom du client **sans
     * qu'il en soit informé**. Le superadministrateur le voyait, l'écran des
     * paramètres le disait à qui allait le lire ; le titulaire, lui, ne savait
     * rien.
     *
     * L'envoi est enveloppé, et volontairement : une messagerie indisponible
     * ne doit pas faire échouer une liaison qui, elle, a réussi. Le contraire
     * laisserait la demande en attente alors que le dossier existe chez
     * Comptaflow — et le rejeu ouvrirait un second dossier.
     */
    private static function prevenirLeTitulaire(Entreprise $entreprise): void
    {
        $destinataire = $entreprise->utilisateurs()
            ->where('role', 'admin')
            ->orderBy('created_at')
            ->first();

        if (!$destinataire || blank($destinataire->email)) {
            Log::warning('Liaison Comptaflow ouverte sans destinataire à prévenir', [
                'entreprise_id' => $entreprise->id,
            ]);

            return;
        }

        try {
            Mail::to($destinataire->email)->send(new CompteComptaflowOuvert(
                $entreprise,
                $destinataire,
                rtrim((string) config('selflow.comptaflow_app_url'), '/')
            ));
        } catch (\Throwable $e) {
            Log::error('Avis d\'ouverture Comptaflow non envoyé : ' . $e->getMessage(), [
                'entreprise_id' => $entreprise->id,
            ]);
        }
    }

    // ── Ce que les appels sortants utilisent ─────────────────────────

    /**
     * L'en-tête qui authentifie l'entreprise auprès de Comptaflow.
     *
     * @return array<string, string>
     */
    public static function enTete(Entreprise $entreprise): array
    {
        $cle = $entreprise->comptaflow_sync_key;

        return filled($cle) ? ['X-Company-Key' => (string) $cle] : [];
    }

    /**
     * Ce que Comptaflow a besoin de savoir pour ouvrir un dossier.
     *
     * ── Les accès, décidés par le propriétaire ──────────────────────
     *
     * **Le compte Comptaflow est le compte Selflow** : même adresse, même mot
     * de passe. L'utilisateur n'en apprend pas un second, et le jour où il
     * change celui de Selflow, il n'a pas deux endroits où penser.
     *
     * Ce qui voyage n'est pourtant **jamais le mot de passe** : c'est son
     * empreinte `bcrypt`, telle qu'elle est en base. Comptaflow la range comme
     * elle arrive, et le même mot de passe y ouvre le compte — sans que
     * personne, ni le superadministrateur ni le réseau ni le journal
     * d'application, ne l'ait jamais lu.
     *
     * C'est ce qui distingue cette version de celle d'avant : la route
     * `register-enterprise` demandait au superadministrateur de **choisir** le
     * mot de passe d'un compte qui n'est pas le sien, et le transportait en
     * clair dans le corps de la requête.
     *
     * @return array<string, mixed>
     */
    private static function dossier(Entreprise $entreprise): array
    {
        $admin = $entreprise->utilisateurs()
            ->where('role', 'admin')
            ->orderBy('created_at')
            ->first();

        return [
            'secret'             => config('selflow.comptaflow_api_secret'),
            'selflow_company_id' => $entreprise->id,
            'entreprise' => [
                'nom'               => $entreprise->nom,
                'forme_juridique'   => $entreprise->forme_juridique,
                'ncc'               => $entreprise->ncc,
                'rccm'              => $entreprise->rccm,
                'regime_imposition' => $entreprise->regime_imposition,
                'adresse'           => $entreprise->adresse,
                'telephone'         => $entreprise->telephone,
                'email'             => $entreprise->email ?? $admin?->email,
                'admin_nom'         => trim(($admin?->nom ?? '') . ' ' . ($admin?->prenom ?? '')) ?: null,

                // L'identifiant de connexion Selflow, et lui seul : c'est le
                // même compte des deux côtés.
                'admin_email'       => $admin?->email,
                'admin_prenom'      => $admin?->prenom,

                // L'empreinte `bcrypt`, jamais le mot de passe. Comptaflow la
                // range telle quelle : le même mot de passe y ouvre le compte,
                // sans que personne ne l'ait lu.
                'admin_password_hash' => $admin?->password,
            ],
            // La convention de numérotation des tiers doit être la même des
            // deux côtés : la passerelle retrouve un tiers par son numéro
            // exact, et deux conventions différentes feraient retomber chaque
            // écriture sur son compte collectif.
            'numerotation_tiers' => $entreprise->numerotation_tiers ?? NumerotationTiersService::NUMERIQUE,
            'longueur_tiers'     => NumerotationTiersService::LONGUEUR,
        ];
    }

    private static function url(string $chemin): string
    {
        return rtrim(config('selflow.comptaflow_api_url', 'http://127.0.0.1:8000'), '/') . $chemin;
    }
}
