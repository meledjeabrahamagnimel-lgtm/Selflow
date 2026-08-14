<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Referentiel\Categorie;
use App\Modules\Authentification\Modeles\Utilisateur;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le parcours de configuration d'une entreprise.
 *
 * Il se déplie étape par étape, se quitte et se reprend, et le superadmin n'y
 * intervient jamais. Ces tests fixent l'enchaînement, et surtout ce qu'un
 * formulaire forgé ne doit pas pouvoir obtenir.
 */
class ParcoursSouscriptionTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private Utilisateur $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentielSeeder::class);

        $this->entreprise = Entreprise::create(['nom' => 'Boutique du carrefour']);
        $this->admin = Utilisateur::create([
            'nom' => 'Kouadio', 'prenom' => 'Lewis', 'email' => 'lewis@exemple.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $this->entreprise->id,
        ]);
    }

    private function commerce(): Categorie
    {
        return Categorie::where('nom', 'Commerce')->firstOrFail();
    }

    public function test_le_parcours_s_ouvre_sur_le_choix_du_domaine(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.souscription.index'))
            ->assertOk()
            ->assertSee('Dans quel domaine travaillez-vous')
            ->assertSee('Commerce');
    }

    public function test_chaque_etape_ouvre_la_suivante(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.souscription.enregistrer', 1), ['categorie_id' => $this->commerce()->id])
            ->assertRedirect(route('admin.souscription.index', ['etape' => 2]));

        $this->assertSame(1, $this->entreprise->fresh()->souscription_etape);

        $this->actingAs($this->admin)
            ->get(route('admin.souscription.index', ['etape' => 2]))
            ->assertOk()
            ->assertSee('Quel est votre métier')
            ->assertSee('Boutique de quartier / Alimentation générale');
    }

    public function test_une_etape_non_atteinte_est_refusee(): void
    {
        // Sans ce controle, un formulaire forge sauterait le choix des metiers
        // et souscrirait a des familles qui n'appartiennent a aucun d'eux.
        $this->actingAs($this->admin)
            ->post(route('admin.souscription.enregistrer', 4), ['familles' => ['VIV']])
            ->assertRedirect(route('admin.souscription.index'))
            ->assertSessionHas('erreur');

        $this->assertSame(0, Produit::where('entreprise_id', $this->entreprise->id)->count());
    }

    public function test_le_parcours_complet_remplit_le_catalogue(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('admin.souscription.enregistrer', 1), ['categorie_id' => $this->commerce()->id]);
        $this->post(route('admin.souscription.enregistrer', 2), ['profils' => ['boutique_quartier']]);
        $this->post(route('admin.souscription.enregistrer', 3), ['modules' => ['principal', 'ventes', 'stock']]);
        $this->post(route('admin.souscription.enregistrer', 4), ['familles' => ['VIV', 'BOI']])
            ->assertRedirect(route('admin.souscription.index', ['etape' => 5]));

        // Seuls les deux rayons retenus, avec leurs articles.
        $this->assertSame(2, \App\Modules\Admin\Modeles\Categorie::where('entreprise_id', $this->entreprise->id)->count());
        $this->assertGreaterThan(0, Produit::where('entreprise_id', $this->entreprise->id)->count());

        $entreprise = $this->entreprise->fresh();
        $this->assertTrue($entreprise->moduleEstActif('stock'));
        $this->assertFalse($entreprise->moduleEstActif('b2b'));
    }

    public function test_un_rayon_decoche_n_apporte_ni_article_ni_compte(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('admin.souscription.enregistrer', 1), ['categorie_id' => $this->commerce()->id]);
        $this->post(route('admin.souscription.enregistrer', 2), ['profils' => ['boutique_quartier']]);
        $this->post(route('admin.souscription.enregistrer', 3), ['modules' => ['principal', 'ventes']]);
        $this->post(route('admin.souscription.enregistrer', 4), ['familles' => ['VIV']]);

        $categories = \App\Modules\Admin\Modeles\Categorie::where('entreprise_id', $this->entreprise->id)->get();

        $this->assertSame(1, $categories->count());
        $this->assertSame('Vivres et alimentation', $categories->first()->nom);

        // Aucun article orphelin : un article dont le rayon n'a pas ete retenu
        // n'aurait nulle part ou aller, et resterait invisible dans les listes.
        $this->assertSame(0, Produit::where('entreprise_id', $this->entreprise->id)
            ->whereNull('categorie_id')->count());
    }

    public function test_l_activite_hors_referentiel_est_retenue(): void
    {
        // Le referentiel ne couvre pas tous les metiers : plutot que de forcer
        // l'utilisateur dans une case, on note ce qu'il dit.
        $this->actingAs($this->admin);

        $this->post(route('admin.souscription.enregistrer', 1), ['categorie_id' => $this->commerce()->id]);
        $this->post(route('admin.souscription.enregistrer', 2), ['activite_autre' => 'Atelier de reliure et dorure'])
            ->assertRedirect(route('admin.souscription.index', ['etape' => 3]));

        $this->assertSame('Atelier de reliure et dorure', $this->entreprise->fresh()->activite_autre);
    }

    public function test_l_etape_du_metier_exige_un_choix_ou_une_description(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('admin.souscription.enregistrer', 1), ['categorie_id' => $this->commerce()->id]);

        $this->post(route('admin.souscription.enregistrer', 2), [])
            ->assertSessionHasErrors('profils');
    }

    public function test_un_module_non_autorise_ne_peut_pas_s_activer_par_le_formulaire(): void
    {
        // Les droits appartiennent au superadmin : un module ferme ne s'ouvre
        // pas en l'ajoutant a la requete.
        $this->entreprise->update(['modules_autorises' => ['principal', 'ventes', 'produits', 'tiers']]);

        $this->actingAs($this->admin);
        $this->post(route('admin.souscription.enregistrer', 1), ['categorie_id' => $this->commerce()->id]);
        $this->post(route('admin.souscription.enregistrer', 2), ['profils' => ['boutique_quartier']]);
        $this->post(route('admin.souscription.enregistrer', 3), ['modules' => ['principal', 'ventes', 'b2b', 'fne']]);
        $this->post(route('admin.souscription.enregistrer', 4), ['familles' => ['VIV']]);

        $entreprise = $this->entreprise->fresh();
        $this->assertFalse($entreprise->moduleEstActif('b2b'));
        $this->assertFalse($entreprise->moduleEstActif('fne'));
        $this->assertTrue($entreprise->moduleEstActif('ventes'));
    }

    public function test_les_prix_saisis_sont_enregistres_et_le_parcours_se_termine(): void
    {
        $this->actingAs($this->admin);
        $this->post(route('admin.souscription.enregistrer', 1), ['categorie_id' => $this->commerce()->id]);
        $this->post(route('admin.souscription.enregistrer', 2), ['profils' => ['boutique_quartier']]);
        $this->post(route('admin.souscription.enregistrer', 3), ['modules' => ['principal', 'ventes', 'stock']]);
        $this->post(route('admin.souscription.enregistrer', 4), ['familles' => ['VIV']]);

        $riz = Produit::where('entreprise_id', $this->entreprise->id)->firstOrFail();

        $this->post(route('admin.souscription.enregistrer', 5), [
            'articles' => [[
                'id' => $riz->id, 'nom' => 'Riz parfumé 25 kg',
                'prix_achat' => 15000, 'prix_vente' => 17500,
            ]],
        ])->assertRedirect(route('admin.tableau_de_bord'));

        $riz->refresh();
        $this->assertSame('Riz parfumé 25 kg', $riz->nom);
        $this->assertEquals(17500, $riz->prix_vente);
        $this->assertNotNull($this->entreprise->fresh()->souscription_terminee_le);
    }

    public function test_on_ne_peut_pas_fixer_le_prix_d_un_produit_d_une_autre_entreprise(): void
    {
        // La vraie surface d'attaque n'est pas l'URL, c'est le corps de la
        // requete : il suffirait d'envoyer l'identifiant du produit du voisin.
        $voisine = Entreprise::create(['nom' => 'Quincaillerie du plateau']);
        $etranger = Produit::create([
            'entreprise_id' => $voisine->id, 'reference' => 'VOISIN-001',
            'nom' => 'Ciment', 'type' => 'marchandise', 'prix_achat' => 5000, 'prix_vente' => 6000,
        ]);

        $this->actingAs($this->admin);
        $this->post(route('admin.souscription.enregistrer', 1), ['categorie_id' => $this->commerce()->id]);
        $this->post(route('admin.souscription.enregistrer', 2), ['profils' => ['boutique_quartier']]);
        $this->post(route('admin.souscription.enregistrer', 3), ['modules' => ['principal', 'ventes']]);
        $this->post(route('admin.souscription.enregistrer', 4), ['familles' => ['VIV']]);

        $this->post(route('admin.souscription.enregistrer', 5), [
            'articles' => [['id' => $etranger->id, 'nom' => 'Détourné', 'prix_vente' => 1]],
        ])->assertSessionHasErrors('articles.0.id');

        $this->assertSame('Ciment', $etranger->fresh()->nom);
    }

    /**
     * Amener l'entreprise jusqu'à l'étape des prix, catalogue rempli.
     */
    private function allerJusquAuxPrix(array $modules = ['principal', 'ventes', 'stock']): void
    {
        $this->post(route('admin.souscription.enregistrer', 1), ['categorie_id' => $this->commerce()->id]);
        $this->post(route('admin.souscription.enregistrer', 2), ['profils' => ['boutique_quartier']]);
        $this->post(route('admin.souscription.enregistrer', 3), ['modules' => $modules]);
        $this->post(route('admin.souscription.enregistrer', 4), ['familles' => ['VIV']]);
    }

    private function unSite(): PointDeVente
    {
        return PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Magasin central', 'ville' => 'Abidjan', 'commune' => 'Cocody',
        ]);
    }

    public function test_le_stock_d_ouverture_est_pose_sur_le_site(): void
    {
        $site = $this->unSite();

        $this->actingAs($this->admin);
        $this->allerJusquAuxPrix();

        $riz = Produit::where('entreprise_id', $this->entreprise->id)->firstOrFail();

        $this->post(route('admin.souscription.enregistrer', 5), [
            'articles' => [['id' => $riz->id, 'prix_vente' => 17500, 'stock_initial' => 42]],
        ])->assertRedirect(route('admin.tableau_de_bord'));

        $this->assertDatabaseHas('stocks', [
            'produit_id' => $riz->id,
            'point_de_vente_id' => $site->id,
            'quantite_disponible' => 42,
        ]);
    }

    public function test_un_service_ne_recoit_aucune_fiche_de_stock(): void
    {
        // Une prestation ne s'epuise pas : lui creer une fiche la ferait
        // figurer en permanence dans « Alertes stock », sous un seuil de 5.
        $site = $this->unSite();

        $this->actingAs($this->admin);
        $this->allerJusquAuxPrix();

        $mission = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'PRESTA-001',
            'nom' => 'Livraison à domicile', 'type' => 'service',
            'prix_achat' => 0, 'prix_vente' => 2000,
        ]);

        $this->post(route('admin.souscription.enregistrer', 5), [
            'articles' => [['id' => $mission->id, 'stock_initial' => 99]],
        ]);

        $this->assertDatabaseMissing('stocks', ['produit_id' => $mission->id]);
    }

    public function test_sans_site_le_stock_d_ouverture_ne_s_affiche_pas(): void
    {
        // Le champ n'aurait nulle part ou ecrire : mieux vaut ne pas le montrer
        // que d'avaler la saisie en silence.
        $this->actingAs($this->admin);
        $this->allerJusquAuxPrix();

        $this->get(route('admin.souscription.index', ['etape' => 5]))
            ->assertOk()
            ->assertDontSee('Stock de départ');
    }

    public function test_le_stock_d_ouverture_ne_s_affiche_pas_sans_le_module(): void
    {
        $this->unSite();

        $this->actingAs($this->admin);
        $this->allerJusquAuxPrix(['principal', 'ventes']);

        $this->get(route('admin.souscription.index', ['etape' => 5]))
            ->assertOk()
            ->assertSee('Prix de vente')
            ->assertDontSee('Stock de départ');
    }

    public function test_le_stock_d_ouverture_s_affiche_avec_un_site_et_le_module(): void
    {
        $this->unSite();

        $this->actingAs($this->admin);
        $this->allerJusquAuxPrix();

        $this->get(route('admin.souscription.index', ['etape' => 5]))
            ->assertOk()
            ->assertSee('Stock de départ')
            ->assertSee('Magasin central');
    }

    public function test_le_parcours_est_ferme_aux_visiteurs(): void
    {
        $this->get(route('admin.souscription.index'))->assertRedirect();
    }

    // ══════════════ Le référentiel absent ══════════════

    public function test_sans_referentiel_la_page_dit_ce_qui_manque(): void
    {
        // `ReferentielSeeder` n'était appelé par aucun autre semeur : une
        // installation neuve arrivait sur « Dans quel domaine travaillez-vous ? »
        // avec la question, le bouton, et **rien entre les deux**. Le formulaire
        // exige pourtant un domaine — continuer était impossible, et rien ne
        // disait pourquoi.
        Categorie::query()->delete();

        $reponse = $this->actingAs($this->admin)->get(route('admin.souscription.index'));

        $reponse->assertOk();
        $reponse->assertSee('catalogue des domaines d\'activité n\'est pas chargé', false);
        $reponse->assertSee('ReferentielSeeder', false);
    }

    public function test_sans_referentiel_le_bouton_continuer_disparait(): void
    {
        // Un bouton qui ramène sur la même page n'aide personne.
        Categorie::query()->delete();

        $this->actingAs($this->admin)
            ->get(route('admin.souscription.index'))
            ->assertDontSee('Continuer <i class="fas fa-arrow-right"></i>', false);
    }

    public function test_le_semeur_par_defaut_charge_le_referentiel(): void
    {
        // La cause première : `db:seed` ne chargeait pas le référentiel, donc
        // une base fraîchement installée ne pouvait pas franchir l'étape 1.
        // Le semeur complet ne se rejoue pas ici — il repose les données que ce
        // test a déjà créées — mais son enchaînement, lui, se lit.
        $source = file_get_contents(database_path('seeders/DatabaseSeeder.php'));

        $this->assertMatchesRegularExpression(
            '/\$this->call\(\s*ReferentielSeeder::class\s*\)/',
            $source,
            'Le semeur par défaut doit charger le référentiel : sans lui, une '
            . 'installation neuve ne peut pas franchir la première étape de la souscription.'
        );
    }
}
