<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Prendre le contrôle : élévation de privilèges et vol d'accès.
 *
 * C'est l'attaque qui compte le plus. Toutes les autres — lire le stock du
 * voisin, forger une quantité — sont bornées : elles atteignent une donnée. Un
 * compte `superadmin` volé atteint **toutes les entreprises de la
 * plateforme**, leurs chiffres d'affaires, leurs clés FNE, leurs clients.
 *
 * Trois familles de tentatives, et un principe commun : **rien de ce qui
 * décide des droits ne doit venir de la requête.** Le rôle, l'entreprise
 * d'appartenance, les habilitations et le jeton d'API sont décidés par le
 * serveur, jamais recopiés depuis ce que le navigateur envoie.
 *
 * 1. **Se hausser soi-même** — poster `role` là où le formulaire ne le propose
 *    pas, s'attribuer des habilitations, changer d'entreprise.
 * 2. **Fabriquer un complice** — créer ou promouvoir un compte au rôle
 *    `superadmin`, qui n'appartient à aucune entreprise.
 * 3. **Voler un accès existant** — jeton d'API d'autrui, réinitialisation du
 *    mot de passe d'un autre, injection dans l'écran de connexion.
 */
class AttaquesElevationTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private Utilisateur $admin;
    private Utilisateur $caissier;
    private Utilisateur $superadmin;
    private PointDeVente $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create([
            'nom' => 'Quincaillerie du plateau',
            'regime_imposition' => 'RNI', 'adresse' => 'Abidjan',
            'rccm' => 'CI-ABJ-2026-B-1', 'ncc' => '2601234A',
            'gerant_fonction' => 'Gérant', 'secteur_activite' => ['Commerce'],
            'modules_actifs' => ['principal', 'ventes', 'achats', 'stock', 'produits', 'tiers'],
        ]);

        $this->site = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Siège', 'ville' => 'Abidjan', 'commune' => 'Cocody',
        ]);

        $this->admin = $this->creerUtilisateur('admin@exemple.ci', 'admin');
        $this->caissier = $this->creerUtilisateur('caissier@exemple.ci', 'caissier');

        // Le superadmin n'appartient a aucune entreprise : c'est le compte de
        // la plateforme, celui qui voit tout.
        $this->superadmin = Utilisateur::create([
            'nom' => 'Plateforme', 'prenom' => 'Super', 'email' => 'super@selflow.ci',
            'password' => Hash::make('mot-de-passe-plateforme'),
            'role' => 'superadmin', 'entreprise_id' => null,
            'habilitations' => ['tableau_de_bord_superadmin', 'gestion_entreprises',
                                'gestion_admins', 'gestion_fne'],
        ]);
    }

    private function creerUtilisateur(string $email, string $role): Utilisateur
    {
        return Utilisateur::create([
            'nom' => 'X', 'prenom' => 'Y', 'email' => $email,
            'password' => Hash::make('secret-de-test'), 'role' => $role,
            'entreprise_id' => $this->entreprise->id,
            'point_de_vente_id' => $this->site->id,
        ]);
    }

    // ═════════════════ 1. Se hausser soi-même ═════════════════

    public function test_le_formulaire_de_profil_ne_change_pas_le_role(): void
    {
        // L'ecran « Mon profil » ne propose ni role ni habilitations. Rien
        // n'empeche de les ajouter au corps de la requete : c'est la faute
        // d'affectation de masse, la plus banale et la plus grave.
        $this->actingAs($this->caissier)->post(route('admin.mon_profil.enregistrer'), [
            'nom' => 'X', 'prenom' => 'Y',
            'role' => 'superadmin',
            'habilitations' => ['comptabilite_globale', 'superadmin_entreprises'],
            'entreprise_id' => null,
        ]);

        $frais = $this->caissier->fresh();

        $this->assertSame('caissier', $frais->role);
        $this->assertSame($this->entreprise->id, $frais->entreprise_id);
        $this->assertNotContains('superadmin_entreprises', $frais->habilitations ?? []);
    }

    public function test_le_formulaire_de_profil_ne_change_pas_l_entreprise(): void
    {
        // Changer d'entreprise, c'est acceder a toutes ses donnees d'un coup —
        // sans jamais toucher a son propre role.
        $voisine = Entreprise::create(['nom' => 'Coopérative du Bandama']);

        $this->actingAs($this->admin)->post(route('admin.mon_profil.enregistrer'), [
            'nom' => 'X', 'prenom' => 'Y',
            'entreprise_id' => $voisine->id,
        ]);

        $this->assertSame($this->entreprise->id, $this->admin->fresh()->entreprise_id);
    }

    public function test_le_formulaire_de_profil_ne_pose_pas_de_jeton_d_api(): void
    {
        // Se poser un jeton connu revient a s'ouvrir une porte de service que
        // ni la session ni le mot de passe ne protegent.
        $this->actingAs($this->caissier)->post(route('admin.mon_profil.enregistrer'), [
            'nom' => 'X', 'prenom' => 'Y',
            'jeton_api' => 'jeton-choisi-par-l-attaquant',
        ]);

        $this->assertNull($this->caissier->fresh()->jeton_api);
    }

    // ═════════════════ 2. Fabriquer un complice ═════════════════

    public function test_un_admin_ne_peut_pas_creer_de_superadmin(): void
    {
        // `superadmin` ne figure pas dans les roles proposes ; le poster quand
        // meme doit echouer a la validation, pas etre ignore en silence.
        $this->actingAs($this->admin)->post(route('admin.personnel.creer'), [
            'nom' => 'Complice', 'prenom' => 'Le', 'email' => 'complice@exemple.ci',
            'password' => 'secret-de-test', 'password_confirmation' => 'secret-de-test',
            'role' => 'superadmin',
        ])->assertSessionHasErrors('role');

        $this->assertNull(Utilisateur::where('email', 'complice@exemple.ci')->first());
    }

    public function test_un_admin_ne_peut_pas_promouvoir_un_employe_en_superadmin(): void
    {
        $this->actingAs($this->admin)->put(route('admin.personnel.modifier', $this->caissier), [
            'nom' => 'X', 'prenom' => 'Y', 'email' => 'caissier@exemple.ci',
            'role' => 'superadmin',
        ])->assertSessionHasErrors('role');

        $this->assertSame('caissier', $this->caissier->fresh()->role);
    }

    public function test_un_employe_cree_appartient_a_l_entreprise_de_son_createur(): void
    {
        // `entreprise_id` est impose par le serveur : le poster ne deplace pas
        // le nouveau compte chez le voisin.
        $voisine = Entreprise::create(['nom' => 'Coopérative du Bandama']);

        $this->actingAs($this->admin)->post(route('admin.personnel.creer'), [
            'nom' => 'Taupe', 'prenom' => 'La', 'email' => 'taupe@exemple.ci',
            'password' => 'secret-de-test', 'password_confirmation' => 'secret-de-test',
            'role' => 'caissier',
            'entreprise_id' => $voisine->id,
        ]);

        $cree = Utilisateur::where('email', 'taupe@exemple.ci')->first();

        if ($cree) {
            $this->assertSame($this->entreprise->id, $cree->entreprise_id);
        } else {
            $this->assertTrue(true, 'La création a été refusée, ce qui convient aussi.');
        }
    }

    public function test_un_admin_ne_peut_pas_modifier_l_employe_d_une_autre_entreprise(): void
    {
        // **404 et non 403.** Repondre « acces refuse » sur la piece d'un
        // autre et « introuvable » sur une piece inexistante distingue les
        // deux : les identifiants etant sequentiels, compter les 403
        // donnait le volume de toute la plateforme. Ce qui n'est pas a
        // vous n'existe pas pour vous.
        $voisine = Entreprise::create(['nom' => 'Coopérative du Bandama']);
        $etranger = Utilisateur::create([
            'nom' => 'A', 'prenom' => 'B', 'email' => 'voisin@exemple.ci',
            'password' => Hash::make('secret-de-test'), 'role' => 'caissier',
            'entreprise_id' => $voisine->id,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.personnel.modifier', $etranger), [
                'nom' => 'Détourné', 'prenom' => 'B', 'email' => 'voisin@exemple.ci',
                'role' => 'admin',
            ])->assertNotFound();

        $this->assertSame('caissier', $etranger->fresh()->role);
        $this->assertSame('A', $etranger->fresh()->nom);
    }

    // ═════════════════ 3. Atteindre les écrans du superadmin ═════════════════

    /**
     * Les écrans de la plateforme, fermés à tout ce qui n'est pas superadmin.
     *
     * @return array<string, array{0: string}>
     */
    public static function ecransDeLaPlateforme(): array
    {
        return [
            'tableau de bord' => ['superadmin.tableau_de_bord'],
            'entreprises'     => ['superadmin.entreprises'],
            'administrateurs' => ['superadmin.admins.index'],
            'clés FNE'        => ['superadmin.fne.index'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ecransDeLaPlateforme')]
    public function test_un_admin_d_entreprise_n_atteint_pas_les_ecrans_de_la_plateforme(string $route): void
    {
        // Les cles FNE de toutes les entreprises de la plateforme sont sur l'un
        // de ces ecrans : y entrer, c'est pouvoir certifier au nom d'autrui.
        $reponse = $this->actingAs($this->admin)->get(route($route));

        $this->assertContains($reponse->status(), [302, 403],
            "L'écran {$route} doit être refusé à un administrateur d'entreprise.");
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ecransDeLaPlateforme')]
    public function test_un_visiteur_n_atteint_pas_les_ecrans_de_la_plateforme(string $route): void
    {
        $this->get(route($route))->assertRedirect();
    }

    public function test_un_caissier_n_atteint_pas_la_gestion_du_personnel(): void
    {
        // Y entrer permettrait de se promouvoir soi-meme au role d'admin.
        $reponse = $this->actingAs($this->caissier)->get(route('admin.personnel.index'));

        $this->assertContains($reponse->status(), [302, 403]);
    }

    // ═════════════════ 4. Voler un accès existant ═════════════════

    public function test_le_jeton_d_api_ne_ressort_pas_dans_la_liste_du_personnel(): void
    {
        // Un jeton affiche est un jeton vole : il vaut mot de passe, sans
        // expiration ni second facteur.
        $this->caissier->forceFill(['jeton_api' => 'jeton-tres-secret-du-caissier'])->save();

        $this->actingAs($this->admin)
            ->get(route('admin.personnel.index'))
            ->assertDontSee('jeton-tres-secret-du-caissier');
    }

    public function test_le_mot_de_passe_hache_ne_ressort_nulle_part(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.personnel.index'))
            ->assertDontSee($this->caissier->password);
    }

    public function test_un_jeton_d_api_invente_n_ouvre_rien(): void
    {
        $this->getJson('/api/admin/personnel', [
            'Authorization' => 'Bearer jeton-invente-de-toutes-pieces',
        ])->assertUnauthorized();
    }

    public function test_le_jeton_d_api_d_autrui_n_emprunte_pas_son_identite(): void
    {
        // Le jeton vaut identite : il doit designer exactement son porteur, et
        // le porteur doit rester enferme dans son entreprise.
        $voisine = Entreprise::create(['nom' => 'Coopérative du Bandama']);
        $etranger = Utilisateur::create([
            'nom' => 'A', 'prenom' => 'B', 'email' => 'voisin@exemple.ci',
            'password' => Hash::make('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $voisine->id, 'jeton_api' => 'jeton-du-voisin',
        ]);

        $reponse = $this->getJson('/api/admin/personnel', [
            'Authorization' => 'Bearer jeton-du-voisin',
        ]);

        // S'il passe, il ne voit que son entreprise — jamais la notre.
        if ($reponse->status() === 200) {
            $reponse->assertDontSee('caissier@exemple.ci');
        }

        $this->assertSame($voisine->id, $etranger->fresh()->entreprise_id);
    }

    public function test_une_injection_sql_dans_la_connexion_n_ouvre_pas_de_session(): void
    {
        // Le classique : `' OR '1'='1' --`. Eloquent lie ses parametres, mais
        // ce test vaut garde-fou contre une future requete ecrite a la main.
        foreach (["' OR '1'='1", "admin@exemple.ci' --", "' OR 1=1 #"] as $charge) {
            $this->post(route('connexion.traitement'), [
                'email' => $charge, 'password' => 'peu-importe',
            ]);

            $this->assertFalse(auth()->check(),
                "La charge « {$charge} » ne doit ouvrir aucune session.");
        }
    }

    public function test_un_mot_de_passe_faux_n_ouvre_pas_de_session(): void
    {
        $this->post(route('connexion.traitement'), [
            'email' => 'admin@exemple.ci', 'password' => 'pas-le-bon',
        ]);

        $this->assertGuest();
    }

    public function test_le_superadmin_ne_se_prend_pas_pour_un_autre_par_la_session(): void
    {
        // Poser `role` en session ne fait pas de vous un superadmin : le role
        // se lit sur l'utilisateur en base, jamais sur ce que porte la session.
        $reponse = $this->actingAs($this->admin)
            ->withSession(['role' => 'superadmin', 'is_superadmin' => true])
            ->get(route('superadmin.entreprises'));

        $this->assertContains($reponse->status(), [302, 403]);
    }

    public function test_l_adresse_de_secours_ne_fait_pas_un_superadmin(): void
    {
        // `VerifierHabilitationRoute` court-circuite tout controle pour
        // l'adresse `superadmin@gmail.com`. Une identite en dur est une porte
        // dont la cle est publiee : ce test verifie qu'elle ne s'ouvre pas a
        // qui n'a pas deja le role — le middleware `role:superadmin` passe
        // avant, et c'est lui qui tient.
        $imposteur = Utilisateur::create([
            'nom' => 'Faux', 'prenom' => 'Super', 'email' => 'superadmin@gmail.com',
            'password' => Hash::make('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $this->entreprise->id,
        ]);

        $reponse = $this->actingAs($imposteur)->get(route('superadmin.entreprises'));

        $this->assertContains($reponse->status(), [302, 403],
            "L'adresse en dur ne doit ouvrir aucun écran de la plateforme.");
    }

    public function test_le_superadmin_legitime_entre_bien(): void
    {
        // Le pendant indispensable : une porte qui refuse tout le monde n'est
        // pas une porte securisee, c'est une porte cassee.
        $this->actingAs($this->superadmin)
            ->get(route('superadmin.entreprises'))
            ->assertOk();
    }
}
