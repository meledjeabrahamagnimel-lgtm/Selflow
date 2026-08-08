<?php

namespace Tests\Feature;

use App\Exceptions\Panne;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Tests\TestCase;

/**
 * Ce qui mérite la page de panne, et ce qui ne la mérite pas.
 *
 * Le gestionnaire d'exceptions renvoyait « 500 — panne détectée » pour toute
 * exception, en production. Une adresse mal tapée, un accès refusé, une session
 * expirée — et surtout un formulaire mal rempli, dont la saisie était perdue —
 * affichaient tous une panne, avec un courriel d'alerte à la clé.
 */
class PanneTest extends TestCase
{
    public function test_une_erreur_serveur_est_une_panne(): void
    {
        $this->assertTrue(Panne::estUne(new \RuntimeException('base de données injoignable')));
        $this->assertTrue(Panne::estUne(new \ErrorException('appel à une méthode absente')));
        $this->assertTrue(Panne::estUne(new ServiceUnavailableHttpException()));
    }

    public function test_une_page_introuvable_n_est_pas_une_panne(): void
    {
        // Un robot qui cherche /wp-admin ne doit ni voir une panne, ni declencher
        // un courriel d'alerte.
        $this->assertFalse(Panne::estUne(new NotFoundHttpException()));
        $this->assertFalse(Panne::estUne(new ModelNotFoundException()));
    }

    public function test_un_acces_refuse_n_est_pas_une_panne(): void
    {
        $this->assertFalse(Panne::estUne(new AccessDeniedHttpException()));
        $this->assertFalse(Panne::estUne(new AuthorizationException()));
    }

    public function test_une_session_expiree_n_est_pas_une_panne(): void
    {
        // Sinon l'utilisateur voit une panne au lieu de la page de connexion,
        // et n'a aucun moyen de se reconnecter.
        $this->assertFalse(Panne::estUne(new AuthenticationException()));
        $this->assertFalse(Panne::estUne(new TokenMismatchException()));
    }

    public function test_un_formulaire_mal_rempli_n_est_pas_une_panne(): void
    {
        // Le cas le plus couteux : la page de panne remplacait le retour au
        // formulaire, et la saisie de l'utilisateur etait perdue.
        $validateur = Validator::make([], ['nom' => 'required']);

        $this->assertFalse(Panne::estUne(new ValidationException($validateur)));
    }
}
