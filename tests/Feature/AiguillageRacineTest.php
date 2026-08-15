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
 * aucune route ne les acceptait — `role:admin` compare à l'identique. Ces
 * deux comptes n'avaient donc aucun espace de travail, ce que la boucle
 * cachait.
 *
 * **Ce sont des accès délégués**, tranché par le propriétaire du projet : un
 * administrateur qui veut confier son travail ouvre un accès à *son* espace,
 * et choisit ce que la personne y voit. Ils rejoignent donc le tableau de
 * bord d'administration, et ce sont les habilitations qui décident ensuite.
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

    // ══════════════ Le visiteur : la vitrine, pas le formulaire ══════════════

    public function test_le_visiteur_arrive_sur_la_vitrine(): void
    {
        // La présentation existait, à `/presentation`, mais **rien n'y
        // menait** : il fallait connaître l'adresse pour la lire, ce qui est
        // le contraire d'une vitrine. La racine y conduit désormais.
        $this->get('/')
            ->assertOk()
            ->assertSee('DC-Knowing', false);
    }

    public function test_la_vitrine_d_accueil_mene_a_la_connexion(): void
    {
        // Elle est devenue la porte d'entrée : sans ce lien, un visiteur venu
        // travailler n'a nulle part où aller.
        $this->get('/')
            ->assertOk()
            ->assertSee(route('connexion'), false);
    }

    // ══════════════ Les accès délégués ══════════════

    /**
     * @return array<string, array{0: string}>
     */
    public static function rolesDelegues(): array
    {
        return [
            'administrateur secondaire' => ['admin_secondaire'],
            'responsable de point de vente' => ['responsable_pdv'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rolesDelegues')]
    public function test_un_acces_delegue_rejoint_l_espace_d_administration(string $role): void
    {
        $this->actingAs($this->compte($role))
            ->get('/')
            ->assertRedirect(route('admin.tableau_de_bord'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rolesDelegues')]
    public function test_un_acces_delegue_franchit_le_controle_de_role(string $role): void
    {
        // `role:admin` comparait à l'identique et refusait ces comptes avant
        // même que les habilitations soient consultées. Il les accepte
        // désormais ; c'est l'habilitation qui décide ensuite.
        $compte = $this->compte($role);
        $compte->update(['habilitations' => ['tableau_de_bord_personnel']]);

        $this->actingAs($compte)
            ->get(route('admin.tableau_de_bord'))
            ->assertOk();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rolesDelegues')]
    public function test_un_acces_delegue_sans_habilitation_ne_voit_rien(string $role): void
    {
        // Le contrôle ferme par défaut : ouvrir l'espace n'ouvre pas les
        // écrans. Tant que l'administrateur n'a rien coché, il n'y a rien à
        // voir — et c'est ce qui fait la différence entre déléguer une tâche
        // et céder l'entreprise.
        $this->actingAs($this->compte($role))
            ->get(route('admin.tableau_de_bord'))
            ->assertForbidden();
    }

    public function test_un_acces_delegue_ne_distribue_pas_les_droits(string $role = 'admin_secondaire'): void
    {
        // **`admin_secondaire` recevait `true` pour toute habilitation**, au
        // même titre que le propriétaire. Le compte créé « pour aider aux
        // ventes » atteignait donc la comptabilité, les paramètres fiscaux, et
        // l'écran qui distribue les droits — d'où il pouvait s'en accorder
        // d'autres, ou en retirer au propriétaire. Déléguer revenait à céder
        // l'entreprise entière.
        $compte = $this->compte($role);
        $compte->update(['habilitations' => ['tableau_de_bord_personnel']]);

        $this->assertFalse($compte->aHabilitation('gestion_habilitations'));
        $this->assertFalse($compte->aHabilitation('gestion_comptabilite'));
        $this->assertTrue($compte->aHabilitation('tableau_de_bord_personnel'));

        $this->actingAs($compte)
            ->get(route('admin.personnel.index', ['tab' => 'habilitations']))
            ->assertForbidden();
    }

    public function test_le_proprietaire_garde_toutes_les_habilitations(): void
    {
        // Le resserrement ne doit pas atteindre celui qui distribue les droits.
        $admin = $this->compte('admin');

        $this->assertTrue($admin->aHabilitation('gestion_habilitations'));
        $this->assertTrue($admin->aHabilitation('gestion_comptabilite'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rolesDelegues')]
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
