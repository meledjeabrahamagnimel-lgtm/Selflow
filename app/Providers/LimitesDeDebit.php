<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Combien de fois par minute chaque porte peut être poussée.
 *
 * ## Ce qui n'était borné nulle part
 *
 * La connexion comptait ses échecs — cinq essais par couple adresse/adresse IP,
 * et c'était juste. **Tout le reste était libre**, et quatre portes le
 * méritaient particulièrement :
 *
 * - **les cinquante et une routes d'API.** Le groupe `api` n'existait pas :
 *   `Route::middleware('api')` ne posait donc aucune limite, et l'authen-
 *   tification par jeton — une chaîne dans une colonne — pouvait être
 *   éprouvée sans compter ;
 * - **la réinitialisation de mot de passe.** Demander un lien envoie un
 *   courriel : sans borne, on inonde la boîte d'un utilisateur, on épuise le
 *   quota du serveur d'envoi, et l'on apprend au passage quelles adresses
 *   existent ;
 * - **l'import.** Un fichier de cinq mégaoctets, lu ligne à ligne, qui écrit
 *   des articles, du stock et des comptes. Le répéter suffit à occuper le
 *   serveur ;
 * - **la normalisation par lot.** Elle appelle la plateforme de la DGI. La
 *   marteler expose l'entreprise à voir **sa propre clé** ralentie ou coupée
 *   par la plateforme — la conséquence est chez elle, pas chez nous.
 *
 * ## Le principe des clés
 *
 * Une limite se compte par **acteur**, non par route : l'utilisateur
 * authentifié quand il y en a un, l'adresse IP sinon. Compter par route
 * laisserait un même acteur épuiser trente portes voisines l'une après l'autre.
 */
class LimitesDeDebit extends ServiceProvider
{
    public function boot(): void
    {
        // Les routes d'API, appelées par l'application mobile. Soixante appels
        // par minute couvrent largement un écran qui rafraîchit ses listes ;
        // au-delà, c'est une boucle ou un balayage.
        RateLimiter::for('api', fn (Request $r) => Limit::perMinute(60)->by($this->acteur($r)));

        // Envoyer un courriel de réinitialisation. Trois par quart d'heure : le
        // temps de vérifier sa boîte, et pas de quoi l'inonder.
        RateLimiter::for('mot-de-passe', fn (Request $r) => [
            Limit::perMinutes(15, 3)->by($this->acteur($r)),
            // Une seconde borne, plus large, par adresse IP : elle arrête le
            // balayage d'adresses électroniques, que la première ne voit pas
            // puisque chaque adresse essayée a sa propre clé.
            Limit::perMinutes(15, 10)->by($r->ip()),
        ]);

        // L'import : un fichier lourd, lu ligne à ligne, qui écrit en base.
        RateLimiter::for('import', fn (Request $r) => Limit::perMinute(6)->by($this->acteur($r)));

        // La normalisation par lot appelle la plateforme de la DGI : la
        // marteler expose l'entreprise à voir sa propre clé ralentie.
        RateLimiter::for('plateforme', fn (Request $r) => Limit::perMinute(10)->by($this->acteur($r)));

        // **Les routes de synchronisation externe, qui n'ont pas
        // d'authentification.** Un secret partagé les protège, comparé en
        // temps constant — mais un secret se devine, et `list-companies` rend
        // **toutes les entreprises de la plateforme avec leur
        // administrateur**. C'est la porte la plus précieuse de l'application,
        // et la seule que personne n'ouvre en étant connecté : elle mérite la
        // limite la plus stricte.
        RateLimiter::for('externe', fn (Request $r) => [
            Limit::perMinute(20)->by($r->ip()),
            Limit::perHour(100)->by($r->ip()),
        ]);

        // Les écrans ordinaires. Large — il ne s'agit pas de gêner un
        // utilisateur qui travaille vite — mais borné : une boucle dans une
        // page ne doit pas pouvoir occuper le serveur à elle seule.
        RateLimiter::for('web', fn (Request $r) => Limit::perMinute(300)->by($this->acteur($r)));
    }

    /**
     * Qui pousse la porte : l'utilisateur authentifié, l'adresse IP sinon.
     *
     * Compter par route plutôt que par acteur laisserait un même acteur épuiser
     * trente portes voisines l'une après l'autre sans jamais franchir une
     * limite.
     */
    private function acteur(Request $requete): string
    {
        return $requete->user()?->getAuthIdentifier()
            ? 'u:' . $requete->user()->getAuthIdentifier()
            : 'ip:' . $requete->ip();
    }
}
