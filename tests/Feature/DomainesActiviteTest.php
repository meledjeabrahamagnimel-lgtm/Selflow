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

    public function test_la_page_d_inscription_propose_le_referentiel(): void
    {
        $reponse = $this->get(route('inscription'))->assertOk();

        foreach (['Commerce', 'E-commerce', 'Agriculture-Élevage', 'BTP-Travaux', 'ONG-Associations'] as $domaine) {
            $reponse->assertSee($domaine, false);
        }
    }

    public function test_la_page_d_inscription_ne_propose_plus_l_ancienne_liste(): void
    {
        $reponse = $this->get(route('inscription'))->assertOk();

        // « Agro-industrie » et « Finance » n'ont jamais existé ailleurs que
        // dans cette vue.
        foreach (['Agro-industrie', 'Finance', 'Industriel'] as $ancien) {
            $reponse->assertDontSee($ancien, false);
        }
    }

    public function test_un_domaine_hors_referentiel_est_refuse_a_l_inscription(): void
    {
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
        ])->assertSessionHasErrors('secteurs_activite.0');

        $this->assertDatabaseMissing('entreprises', ['nom' => 'Boutique du carrefour']);
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
