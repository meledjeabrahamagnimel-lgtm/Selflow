<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Authentification\Modeles\Utilisateur;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Écran de consultation du référentiel.
 *
 * Rien dans l'application ne permettait de vérifier ce qui avait été chargé
 * depuis le classeur : il fallait ouvrir la base. Cet écran sert à contrôler
 * une nouvelle version avant de la déployer — d'où l'importance qu'il montre
 * les comptes, et qu'il reste fermé à qui n'est pas superadmin.
 */
class SuperadminReferentielTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentielSeeder::class);
    }

    private function utilisateur(string $role): Utilisateur
    {
        $entreprise = Entreprise::create(['nom' => 'DC-Knowing']);

        return Utilisateur::create([
            'nom'           => 'Kouadio',
            'prenom'        => 'Lewis',
            'email'         => $role . '@exemple.ci',
            'password'      => bcrypt('secret-de-test'),
            'role'          => $role,
            'entreprise_id' => $entreprise->id,
        ]);
    }

    public function test_l_ecran_liste_les_profils_avec_leurs_comptes(): void
    {
        $reponse = $this->actingAs($this->utilisateur('superadmin'))
            ->get(route('superadmin.referentiel.index'));

        $reponse->assertOk();
        $reponse->assertSee('Boutique de quartier / Alimentation générale');
        $reponse->assertSee('MARCHANDISE');
        $reponse->assertSee('701000');   // le compte de vente des marchandises
        $reponse->assertSee('Report à nouveau');
    }

    public function test_le_detail_d_un_profil_montre_ses_familles_et_ses_articles(): void
    {
        $reponse = $this->actingAs($this->utilisateur('superadmin'))
            ->get(route('superadmin.referentiel.profil', 'boutique_quartier'));

        $reponse->assertOk();
        $reponse->assertSee('Vivres et alimentation');
        $reponse->assertSee('Riz sac 25 kg');
        $reponse->assertSee('311000');   // le compte de stock, subdivise par famille
    }

    public function test_le_filtre_par_categorie_restreint_la_liste(): void
    {
        $utilisateur = $this->utilisateur('superadmin');

        $reponse = $this->actingAs($utilisateur)
            ->get(route('superadmin.referentiel.index', ['recherche' => 'boulangerie']));

        $reponse->assertOk();
        $reponse->assertSee('Boulangerie');
        $reponse->assertDontSee('Quincaillerie');
    }

    public function test_un_profil_inconnu_renvoie_une_page_absente(): void
    {
        $this->actingAs($this->utilisateur('superadmin'))
            ->get(route('superadmin.referentiel.profil', 'profil_invente'))
            ->assertNotFound();
    }

    public function test_l_ecran_est_ferme_a_qui_n_est_pas_superadmin(): void
    {
        // Le referentiel n'est pas confidentiel, mais l'ecran vit derriere le
        // role : un admin d'entreprise n'a rien a y faire.
        $this->actingAs($this->utilisateur('admin'))
            ->get(route('superadmin.referentiel.index'))
            ->assertForbidden();
    }

    public function test_l_ecran_est_ferme_aux_visiteurs(): void
    {
        $this->get(route('superadmin.referentiel.index'))->assertRedirect();
    }
}
