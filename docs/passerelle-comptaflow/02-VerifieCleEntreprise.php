<?php

/**
 * COMPTAFLOW — middleware à créer.
 *
 * Emplacement suggéré : app/Http/Middleware/VerifieCleEntreprise.php
 * Alias suggéré       : 'cle.entreprise'
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Ce qu'il fait
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Il lit l'en-tête `X-Company-Key`, retrouve le dossier qu'elle désigne, et
 * le pose sur la requête (`$request->attributes->get('entreprise_liee')`).
 * Les contrôleurs n'ont plus à croire le corps de la requête sur parole.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Trois choses délibérées
 * ─────────────────────────────────────────────────────────────────────────
 *
 * **La comparaison ne se fait pas par `where('selflow_sync_key', $cle)` seul
 * si vous avez retenu le hachage** — voyez la migration. Avec la clé en clair,
 * la recherche par index est exacte et ne fuit rien : c'est une égalité en
 * base, pas une comparaison caractère par caractère en PHP.
 *
 * **401 (Unauthorized — non authentifié) et 403 (Forbidden — accès interdit)
 * ne disent pas la même chose, et il faut les distinguer.** Une clé absente ou
 * inconnue, c'est 401 : l'appelant n'est pas authentifié. Une clé valide qui
 * désigne un autre dossier que celui annoncé dans le corps, c'est 403 :
 * l'appelant est connu, mais il écrit chez quelqu'un d'autre. En les
 * confondant, le journal ne permet plus de distinguer un déploiement mal
 * configuré d'une tentative d'écriture croisée — et c'est précisément la
 * seconde qu'on veut voir arriver.
 *
 * **Une clé révoquée est refusée en la nommant révoquée**, et non « inconnue » :
 * sinon, une entreprise déliée par erreur passe une journée à chercher une
 * panne de réseau.
 *
 * // À VÉRIFIER : le modèle. `Company` est supposé ici.
 */

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class VerifieCleEntreprise
{
    public function handle(Request $request, Closure $next): Response
    {
        $cle = $request->header('X-Company-Key');

        if (blank($cle)) {
            // Pendant la transition, Selflow envoie encore le secret partagé
            // dans le corps : on laisse passer les appels sans en-tête tant
            // que les deux applications ne sont pas déployées ensemble.
            //
            // ⚠️ CETTE TOLÉRANCE EST TOUT L'INTÉRÊT DU LOT. Retirez-la — et
            // remplacez ce `return` par la réponse 401 ci-dessous — dès que
            // les deux côtés sont en place. Tant qu'elle est là, le secret
            // partagé suffit toujours à écrire dans n'importe quel dossier.
            return $next($request);

            // return response()->json([
            //     'success' => false,
            //     'message' => 'Clé de liaison absente (en-tête X-Company-Key).',
            // ], 401);
        }

        $entreprise = Company::where('selflow_sync_key', $cle)->first();

        if (!$entreprise) {
            Log::warning('Passerelle Selflow : clé de liaison inconnue', ['ip' => $request->ip()]);

            return response()->json([
                'success' => false,
                'message' => 'Clé de liaison inconnue.',
            ], 401);
        }

        if ($entreprise->selflow_sync_key_revoked_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Clé de liaison révoquée le '
                    . $entreprise->selflow_sync_key_revoked_at->format('d/m/Y') . '.',
            ], 401);
        }

        // Le corps annonce une entreprise ; la clé en désigne une. Les deux
        // doivent coïncider — c'est cette vérification, et elle seule, qui
        // rend la clé utile. Sans elle, n'importe quel porteur du secret
        // écrivait dans les livres de n'importe qui.
        foreach (['comptaflow_company_id' => $entreprise->id,
                  'selflow_company_id'    => $entreprise->selflow_company_id] as $champ => $attendu) {
            $annonce = $request->input($champ);

            if (filled($annonce) && filled($attendu) && (int) $annonce !== (int) $attendu) {
                Log::warning('Passerelle Selflow : écriture croisée refusée', [
                    'cle_dossier'   => $entreprise->id,
                    'corps_annonce' => [$champ => $annonce],
                    'ip'            => $request->ip(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'La clé de liaison ne désigne pas l\'entreprise annoncée.',
                ], 403);
            }
        }

        $request->attributes->set('entreprise_liee', $entreprise);

        return $next($request);
    }
}
