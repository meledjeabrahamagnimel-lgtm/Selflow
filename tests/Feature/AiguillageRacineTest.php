<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * L'aiguillage de la racine, et la boucle qu'il produisait.
 *
 * `/` renvoyait vers `connexion` tout utilisateur connecté dont le rôle
 * n'était pas `superadmin`, `admin` ou `caissier`. Or `connexion` est
 * réservée aux visiteurs : elle renvoie aussitôt un utilisateur connecté
 * vers `/`, qui le renvoie là-bas. **Le navigateur s'arrêtait au bout de
 * vingt allers-retours sur `ERR_TOO_MANY_REDIRECTS`**, et le compte était
 * inutilisable sans qu'aucun message ne dise pourquoi.
 *
 * Le modèle porte pourtant cinq rôles : `admin_secondaire` et
 * `responsable_pdv` existent, avec leurs méthodes sur `Utilisateur`, mais
 * aucune route ne les accepte — `role:admin` et `role:admin,caissier`
 * comparent à l'identique. Ces deux comptes n'ont donc **aucun espace de
 * travail**, ce que la boucle cachait et qu'un message dit désormais.
 */
class AiguillageRacineTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private PointDeVente $pdv;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create([
            'nom'               => 'Quincaillerie du Plateau',
            'regime_imposition' => 'RNI',
            'adresse'           => 'Plateau, Abidjan',
            'rccm'              => 'CI-ABJ-2026-B-00123',
            'ncc'               => '2601239A',
            'gerant_fonction'   => 'Gérant',
            'secteur_activite'  => ['Commerce'],
            'modules_actifs'    => ['principal', 'ventes', 'achats', 'stock', 'produits', 'tiers'],
        ]);

        $this->pdv = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Magasin central', 'ville' => 'Abidjan', 'commune' => 'Plateau',
        ]);
    }

    private function compte(string $role): Utilisateur
    {
        return Utilisateur::create([
            'nom' => 'Kouassi', 'prenom' => 'Yao',
            'email' => $role . '@exemple.ci',
            'password' => bcrypt('secret-de-test'), 'role' => $role,
            'entreprise_id' => $role === 'superadmin' ? null : $this->entreprise->id,
            'point_de_vente_id' => $role === 'superadmin' ? null : $this->pdv->id,
            'habilitations' => $role === 'superadmin'
                ? \App\Modules\Authentification\Regles\Habilitations::PLATEFORME
                : [],
        ]);
    }

    // ══════════════ Les rôles qui ont un espace ══════════════

    public function test_l_admin_est_conduit_a_son_tableau_de_bord(): void
    {
        $this->actingAs($this->compte('admin'))
            ->get('/')
            ->assertRedirect(route('admin.tableau_de_bord'));
    }

    public function test_le_caissier_est_conduit_a_son_tableau_de_bord(): void
    {
        $this->actingAs($this->compte('caissier'))
            ->get('/')
            ->assertRedirect(route('caissier.tableau_de_bord'));
    }

    public function test_le_superadmin_est_conduit_a_son_tableau_de_bord(): void
    {
        $this->actingAs($this->compte('superadmin'))
            ->get('/')
            ->assertRedirect(route('superadmin.tableau_de_bord'));
    }

    public function test_le_visiteur_est_conduit_a_la_connexion(): void
    {
        $this->get('/')->assertRedirect(route('connexion'));
    }

    // ══════════════ Les rôles sans espace : un message, pas une boucle ══════════════

    /**
     * @return array<string, array{0: string}>
     */
    public static function rolesSansEspace(): array
    {
        return [
            'administrateur secondaire' => ['admin_secondaire'],
            'responsable de point de vente' => ['responsable_pdv'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rolesSansEspace')]
    public function test_un_role_sans_espace_recoit_un_message_et_non_une_boucle(string $role): void
    {
        // Le point du test : la réponse ne doit **pas** être une redirection
        // vers `connexion`, qui rejetterait l'utilisateur ici même.
        $reponse = $this->actingAs($this->compte($role))->get('/');

        $reponse->assertForbidden();
        $reponse->assertSee($role, false);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rolesSansEspace')]
    public function test_la_racine_ne_renvoie_jamais_un_connecte_vers_la_connexion(string $role): void
    {
        // La boucle tenait à cette seule redirection : `connexion` est
        // réservée aux visiteurs et repousse un connecté vers `/`.
        $reponse = $this->actingAs($this->compte($role))->get('/');

        $this->assertNotSame(
            route('connexion'),
            $reponse->headers->get('Location'),
            'La racine renvoie un utilisateur connecté vers la page de connexion : '
            . 'celle-ci le repoussera ici, et le navigateur bouclera.'
        );
    }

    public function test_la_page_de_connexion_ne_renvoie_pas_a_la_racine_pour_un_visiteur(): void
    {
        // L'autre moitié de la boucle : pour un visiteur, la connexion
        // s'affiche — elle ne redirige pas.
        $this->get(route('connexion'))->assertOk();
    }
}
