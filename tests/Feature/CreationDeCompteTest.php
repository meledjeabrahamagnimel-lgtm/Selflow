<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\FneCredential;
use App\Modules\Admin\Regles\EtapesCreation;
use App\Modules\Authentification\Modeles\Utilisateur;
use App\Modules\Authentification\Regles\Habilitations;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Créer une entreprise, des deux côtés.
 *
 * Deux écrans le font : `/inscription`, où l'entreprise s'inscrit elle-même,
 * et celui du superadministrateur, où on l'inscrit pour elle. Ils ne
 * demandaient ni les mêmes choses, ni dans le même ordre — et l'un des deux ne
 * marchait pas du tout.
 *
 * ## Le défaut principal
 *
 * **L'écran du superadministrateur créait une entreprise et personne pour s'y
 * connecter.** Aucun `Utilisateur` n'était enregistré, et le formulaire ne
 * demandait pas même un mot de passe. Toute entreprise créée par cette voie
 * était donc inutilisable, sans qu'aucune erreur ne le signale : il fallait
 * s'en apercevoir, puis lui fabriquer un compte à la main.
 *
 * ## Les étapes
 *
 * Le formulaire d'inscription faisait vingt champs d'un seul tenant. Il se
 * parcourt maintenant pas à pas, et **deux étapes seulement bloquent la
 * création** : l'entreprise a besoin d'un nom, et il lui faut un responsable
 * qui puisse se connecter. La situation fiscale et le domaine se complètent
 * aussi bien une fois dans l'application — et le garde `inscription.complete`
 * retient ventes et achats tant qu'ils manquent.
 */
class CreationDeCompteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentielSeeder::class);
    }

    private function superadmin(): Utilisateur
    {
        return Utilisateur::create([
            'nom' => 'Meledje', 'prenom' => 'Abraham', 'email' => 'super@exemple.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'superadmin',
            'habilitations' => Habilitations::PLATEFORME,
        ]);
    }

    /** Le strict nécessaire pour créer une entreprise depuis le superadmin. */
    private function creationSuperadmin(array $extra = []): array
    {
        return array_merge([
            'nom'                          => 'Quincaillerie du Plateau',
            'email'                        => 'gerant@quincaillerie.ci',
            'gerant_password'              => 'un-mot-de-passe',
            'gerant_password_confirmation' => 'un-mot-de-passe',
            'quota_points_de_vente'        => 5,
            'plan_abonnement'              => 'Starter',
        ], $extra);
    }

    /** Le strict nécessaire pour s'inscrire soi-même. */
    private function inscription(array $extra = []): array
    {
        return array_merge([
            'nom_entreprise'        => 'Boutique du carrefour',
            'nom'                   => 'Kouadio',
            'prenom'                => 'Lewis',
            'email'                 => 'lewis@exemple.ci',
            'password'              => 'un-mot-de-passe',
            'password_confirmation' => 'un-mot-de-passe',
            'conditions'            => '1',
        ], $extra);
    }

    // ── Le responsable, sans qui rien n'est atteignable ──────────────

    public function test_le_superadmin_cree_le_compte_du_responsable(): void
    {
        // Le defaut principal : l'entreprise naissait sans personne pour s'y
        // connecter, et rien ne le signalait.
        $this->actingAs($this->superadmin())
            ->post(route('superadmin.entreprises.creer.enregistrer'), $this->creationSuperadmin([
                'gerant_nom' => 'Konan', 'gerant_prenom' => 'Yao',
            ]))
            ->assertSessionHasNoErrors();

        $entreprise = Entreprise::where('nom', 'Quincaillerie du Plateau')->firstOrFail();
        $responsable = Utilisateur::where('entreprise_id', $entreprise->id)->first();

        $this->assertNotNull($responsable, "L'entreprise doit naître avec un responsable.");
        $this->assertSame('admin', $responsable->role);
        $this->assertSame('gerant@quincaillerie.ci', $responsable->email);
    }

    public function test_le_responsable_cree_par_un_tiers_doit_changer_son_mot_de_passe(): void
    {
        // Le mot de passe vient du superadministrateur, non de son
        // proprietaire : il ne doit pas rester en l'etat.
        $this->actingAs($this->superadmin())
            ->post(route('superadmin.entreprises.creer.enregistrer'), $this->creationSuperadmin());

        $responsable = Utilisateur::where('email', 'gerant@quincaillerie.ci')->firstOrFail();

        $this->assertTrue((bool) $responsable->doit_changer_password);
    }

    public function test_le_responsable_peut_reellement_se_connecter(): void
    {
        // L'epreuve qui compte : le mot de passe pose doit ouvrir la porte.
        $this->actingAs($this->superadmin())
            ->post(route('superadmin.entreprises.creer.enregistrer'), $this->creationSuperadmin());

        auth()->logout();

        $this->post(route('connexion.traitement'), [
            'email'    => 'gerant@quincaillerie.ci',
            'password' => 'un-mot-de-passe',
        ]);

        $this->assertAuthenticated();
    }

    public function test_sans_mot_de_passe_aucune_entreprise_n_est_creee(): void
    {
        $donnees = $this->creationSuperadmin();
        unset($donnees['gerant_password'], $donnees['gerant_password_confirmation']);

        $this->actingAs($this->superadmin())
            ->post(route('superadmin.entreprises.creer.enregistrer'), $donnees)
            ->assertSessionHasErrors('gerant_password');

        $this->assertDatabaseMissing('entreprises', ['nom' => 'Quincaillerie du Plateau']);
    }

    // ── Ce qui peut attendre ─────────────────────────────────────────

    public function test_le_domaine_ne_bloque_plus_la_creation_par_le_superadmin(): void
    {
        // Il etait obligatoire ici, et facultatif a l'inscription : deux ecrans
        // qui creent la meme chose ne peuvent pas exiger des choses
        // differentes.
        $this->actingAs($this->superadmin())
            ->post(route('superadmin.entreprises.creer.enregistrer'), $this->creationSuperadmin())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('entreprises', ['nom' => 'Quincaillerie du Plateau']);
    }

    public function test_une_inscription_sans_les_etapes_facultatives_aboutit(): void
    {
        $this->post(route('inscription.traitement'), $this->inscription())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('entreprises', ['nom' => 'Boutique du carrefour']);
        $this->assertAuthenticated();
    }

    public function test_une_entreprise_incomplete_ne_facture_pas_encore(): void
    {
        // Facultatif ne veut pas dire oublie : le garde retient ventes et
        // achats tant que la situation fiscale manque.
        $this->post(route('inscription.traitement'), $this->inscription());

        $entreprise = Entreprise::where('nom', 'Boutique du carrefour')->firstOrFail();

        $this->assertFalse($entreprise->estInscriptionComplete());
    }

    public function test_les_deux_ecrans_suivent_les_memes_etapes(): void
    {
        // La definition vit en un seul endroit : ajouter un champ, c'est le
        // poser une fois.
        $etapes = EtapesCreation::toutes();

        $this->assertCount(4, $etapes);
        $this->assertCount(2, EtapesCreation::obligatoires());
        $this->assertSame(['entreprise', 'responsable'],
            array_column(EtapesCreation::obligatoires(), 'cle'));
        $this->assertSame(['fne', 'domaine'],
            array_column(EtapesCreation::facultatives(), 'cle'));
    }

    public function test_la_page_d_inscription_se_parcourt_en_quatre_etapes(): void
    {
        $corps = $this->get(route('inscription'))->assertOk()->getContent();

        foreach ([1, 2, 3, 4] as $rang) {
            $this->assertStringContainsString('data-pas="' . $rang . '"', $corps);
        }

        $this->assertStringContainsString('btn-suivant', $corps);
    }

    // ── L'accès à l'espace FNE ───────────────────────────────────────

    public function test_l_acces_fne_fourni_a_l_inscription_est_retenu(): void
    {
        $this->post(route('inscription.traitement'), $this->inscription([
            'possede_compte_fne' => '1',
            'fne_ncc'            => '2603210A',
            'fne_mot_de_passe'   => 'le-mot-de-passe-dgi',
        ]))->assertSessionHasNoErrors();

        $entreprise = Entreprise::where('nom', 'Boutique du carrefour')->firstOrFail();
        $identifiants = FneCredential::where('entreprise_id', $entreprise->id)->firstOrFail();

        $this->assertSame('2603210A', $identifiants->ncc_associe);
        $this->assertSame('le-mot-de-passe-dgi', $identifiants->acces_mot_de_passe);
        $this->assertNotNull($identifiants->acces_fourni_at);
        $this->assertTrue((bool) $entreprise->possede_compte_fne);
    }

    public function test_le_mot_de_passe_fne_est_chiffre_au_repos(): void
    {
        // C'est un acces a un service de l'Etat, pas un reglage : il est
        // traite comme les cles d'API, inexploitable sans APP_KEY.
        $this->post(route('inscription.traitement'), $this->inscription([
            'possede_compte_fne' => '1',
            'fne_ncc'            => '2603210A',
            'fne_mot_de_passe'   => 'le-mot-de-passe-dgi',
        ]));

        $brut = DB::table('fne_credentials')->value('acces_mot_de_passe');

        $this->assertNotSame('le-mot-de-passe-dgi', $brut);
        $this->assertStringNotContainsString('le-mot-de-passe-dgi', (string) $brut);
    }

    public function test_le_mot_de_passe_fne_n_est_rendu_par_aucun_ecran(): void
    {
        $this->post(route('inscription.traitement'), $this->inscription([
            'possede_compte_fne' => '1',
            'fne_ncc'            => '2603210A',
            'fne_mot_de_passe'   => 'le-mot-de-passe-dgi',
        ]));

        auth()->logout();

        $this->actingAs($this->superadmin())
            ->get(route('superadmin.fne.index'))
            ->assertOk()
            ->assertDontSee('le-mot-de-passe-dgi');
    }

    public function test_une_entreprise_sans_compte_fne_ne_fournit_aucun_acces(): void
    {
        $this->post(route('inscription.traitement'), $this->inscription([
            'possede_compte_fne' => '0',
            'rccm'               => 'CI-ABJ-2026-B-00321',
            'ncc'                => '2603210A',
        ]))->assertSessionHasNoErrors();

        $entreprise = Entreprise::where('nom', 'Boutique du carrefour')->firstOrFail();

        $this->assertFalse((bool) $entreprise->possede_compte_fne);
        $this->assertSame('2603210A', $entreprise->ncc);
        $this->assertDatabaseCount('fne_credentials', 0);
    }

    public function test_un_ncc_mal_forme_est_refuse(): void
    {
        // Sept caractères alphanumériques suivis d'une lettre. `26032100` a la
        // bonne longueur mais finit par un chiffre : c'est le cas qu'une simple
        // vérification de taille laisserait passer.
        $this->post(route('inscription.traitement'), $this->inscription([
            'possede_compte_fne' => '1',
            'fne_ncc'            => '26032100',
        ]))->assertSessionHasErrors('fne_ncc');

        $this->assertDatabaseMissing('entreprises', ['nom' => 'Boutique du carrefour']);
    }
}
