<?php

namespace Tests\Feature;

use App\Modules\Admin\Controleurs\SuperadminControleur;
use App\Modules\Admin\Modeles\Referentiel\Categorie;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un seul vocabulaire pour le domaine d'activité.
 *
 * Trois listes écrites en dur cohabitaient, et aucune ne correspondait aux
 * douze catégories du référentiel — celles-là mêmes que la première étape de
 * la souscription propose :
 *
 * | Écran | Ce qu'il proposait |
 * |---|---|
 * | `/inscription` | dix valeurs : Commercial, Industriel, Agro-industrie, Finance… |
 * | Paramètres de l'entreprise | douze autres : Agricole, Artisanat, BTP / Construction… |
 * | Superadministrateur | les mêmes douze, plus une table d'icônes |
 * | Souscription, étape 1 | les vraies : Commerce, E-commerce, Production… |
 *
 * Une entreprise cochait donc « Commercial » à l'inscription pour choisir
 * « Commerce » à l'écran suivant, sans qu'aucun des deux ne parle à l'autre.
 * L'écran « secteurs ↔ modules » du superadministrateur rangeait quant à lui
 * sa configuration sous des clés qu'aucune entreprise ne portait : elle ne
 * servait jamais.
 */
class DomainesActiviteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentielSeeder::class);
    }

    public function test_les_domaines_sont_ceux_du_referentiel(): void
    {
        $domaines = Categorie::domaines();

        $this->assertSame(13, count($domaines), 'Les douze catégories, plus « Autre ».');
        $this->assertSame('Commerce', $domaines[0], 'Le classeur est ordonné.');
        $this->assertSame(Categorie::AUTRE, end($domaines));
    }

    public function test_une_sortie_libre_est_toujours_offerte(): void
    {
        // Le référentiel couvre douze domaines ; il ne couvrira jamais tout.
        // Sans « Autre », l'utilisateur coche au hasard et la donnée ment.
        $this->assertContains(Categorie::AUTRE, Categorie::domaines());
    }

    public function test_l_inscription_ne_propose_plus_aucun_domaine(): void
    {
        // Elle en proposait douze, par cases à cocher, pendant que le parcours
        // de configuration posait la même question à sa première étape. Les
        // deux écrans ne se parlaient pas : on pouvait déclarer « Santé » ici
        // et souscrire « Boulangerie » là, et les deux réponses cohabitaient.
        // Une seule question désormais, au parcours.
        $reponse = $this->get(route('inscription'))->assertOk();

        $reponse->assertDontSee('secteurs_activite', false);

        foreach (['Agriculture-Élevage', 'BTP-Travaux', 'ONG-Associations'] as $domaine) {
            $reponse->assertDontSee('value="' . $domaine . '"', false);
        }
    }

    public function test_un_domaine_poste_a_l_inscription_reste_sans_effet(): void
    {
        // La règle qui refusait un domaine hors référentiel est partie avec le
        // champ. Ce qui compte désormais n'est plus qu'une valeur inventée soit
        // refusée, mais qu'aucune valeur postée n'atteigne la colonne.
        $this->post(route('inscription.traitement'), [
            'nom_entreprise'        => 'Boutique du carrefour',
            'regime_imposition'     => 'RSI',
            'nom'                   => 'Kouadio',
            'prenom'                => 'Lewis',
            'email'                 => 'lewis@exemple.ci',
            'password'              => 'un-mot-de-passe',
            'password_confirmation' => 'un-mot-de-passe',
            'conditions'            => '1',
            'secteurs_activite'     => ['Agro-industrie'],
        ])->assertSessionHasNoErrors();

        $entreprise = \App\Modules\Admin\Modeles\Entreprise::where('nom', 'Boutique du carrefour')->firstOrFail();

        $this->assertEmpty($entreprise->secteur_activite ?? []);
    }

    public function test_un_domaine_du_referentiel_est_accepte_a_l_inscription(): void
    {
        $this->post(route('inscription.traitement'), [
            'nom_entreprise'        => 'Quincaillerie du Plateau',
            'regime_imposition'     => 'RSI',
            'nom'                   => 'Kouadio',
            'prenom'                => 'Lewis',
            'email'                 => 'lewis@exemple.ci',
            'password'              => 'un-mot-de-passe',
            'password_confirmation' => 'un-mot-de-passe',
            'conditions'            => '1',
            'secteurs_activite'     => ['Commerce', 'BTP-Travaux'],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('entreprises', ['nom' => 'Quincaillerie du Plateau']);
    }

    public function test_l_ecran_secteurs_modules_se_range_sous_les_memes_noms(): void
    {
        // La configuration se classait sous « Commercial », « Agricole »… que
        // le référentiel ne connaît pas : elle ne rencontrait jamais une
        // entreprise.
        $secteurs = array_keys(SuperadminControleur::tousLesSecteurs());

        $this->assertSame(Categorie::domaines(), $secteurs);
    }
}
