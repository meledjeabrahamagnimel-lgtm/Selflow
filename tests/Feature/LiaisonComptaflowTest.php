<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Services\DeversementReferentielService;
use App\Modules\Admin\Services\LiaisonComptaflowService;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * La clé de liaison Comptaflow, délivrée et non saisie.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Ce qui était en place
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Un champ de texte libre dans les paramètres de l'entreprise, avec la
 * consigne « obtenir depuis Comptaflow → Configuration → Liaison Selflow ».
 * `EntrepriseControleur` ouvrait la liaison dès que la valeur changeait.
 *
 * **Simulation d'attaque, et c'est le cœur de ce lot.** Une entreprise obtient
 * la clé d'une autre — comptable partagé entre deux clients, capture d'écran,
 * ancien salarié. Elle la colle dans ses propres paramètres. La liaison
 * s'ouvre, et son référentiel puis toutes ses écritures partent **dans les
 * livres de l'autre**. Le secret partagé n'y change rien : il est détenu par
 * le serveur, part sur tous les appels, et ne dit pas qui appelle. Rien, côté
 * Selflow, ne vérifiait que la clé saisie désignait celui qui la saisissait.
 *
 * Trois autres écrans mentaient au passage :
 *
 *  - le bouton « Lancer la synchronisation test » annonçait « Synchronisation
 *    bidirectionnelle réussie ! Les écritures comptables et les statuts des
 *    factures ont été synchronisés » **sans qu'aucun appel ne parte** — et
 *    écrivait `comptaflow_sync_status = 'Actif'`, valeur qu'aucun autre code
 *    ne reconnaît : le déversement, qui attend `active`, s'arrêtait net après
 *    cette « réussite » ;
 *  - « Délier » effaçait la clé chez Selflow sans rien dire à Comptaflow, où
 *    elle continuait d'ouvrir le dossier ;
 *  - « Vérifier la liaison » écrivait la date du jour et annonçait « Liaison
 *    active » sans interroger personne.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Ce qui la remplace
 * ─────────────────────────────────────────────────────────────────────────
 *
 * L'entreprise demande, le superadministrateur valide, Comptaflow génère la
 * clé et la renvoie, Selflow la range chiffrée. Personne ne la tape, aucun
 * écran ne l'affiche entière, et c'est elle — en en-tête `X-Company-Key` —
 * qui dit à Comptaflow quelle entreprise appelle.
 */
class LiaisonComptaflowTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private Utilisateur $admin;
    private Utilisateur $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['selflow.comptaflow_api_secret' => 'secret-serveur-de-test']);
        config(['selflow.comptaflow_api_url' => 'http://comptaflow.test']);

        $this->entreprise = Entreprise::create([
            'nom' => 'DC-Knowing CGA', 'regime_imposition' => 'RNI',
            'adresse' => 'Cocody, Abidjan', 'rccm' => 'CI-ABJ-2026-B-00042',
            'ncc' => '2601234A', 'gerant_fonction' => 'Gérant',
            'secteur_activite' => ['Commerce'],
            'modules_actifs' => ['principal', 'ventes', 'comptabilite', 'tiers'],
        ]);

        $site = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Siège', 'ville' => 'Abidjan', 'commune' => 'Cocody',
        ]);

        $this->admin = Utilisateur::create([
            'nom' => 'Kouadio', 'prenom' => 'Lewis', 'email' => 'lewis-liaison@dc-knowing.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $this->entreprise->id, 'point_de_vente_id' => $site->id,
        ]);

        $this->superadmin = Utilisateur::create([
            'nom' => 'Selflow', 'prenom' => 'Support', 'email' => 'support-liaison@selflow.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'superadmin',
            'habilitations' => \App\Modules\Authentification\Regles\Habilitations::PLATEFORME,
        ]);
    }

    /** Le minimum que le formulaire des paramètres exige. */
    private function enregistrerParametres(array $extra = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->put(route('admin.entreprise.parametres.enregistrer'), array_merge([
            'nom'             => $this->entreprise->nom,
            'gerant_fonction' => 'Gérant',
            'adresse'         => 'Cocody, Abidjan',
            'rccm'            => 'CI-ABJ-2026-B-00042',
            'ncc'             => '2601234A',
        ], $extra));
    }

    /** Ce que Comptaflow répond quand tout va bien. */
    private function comptaflowRepond(string $cle = 'cptf_live_9f3b7d2a4c81'): void
    {
        Http::fake([
            '*/api/external/companies/provision' => Http::response([
                'success' => true, 'company_id' => 42, 'sync_key' => $cle,
            ]),
            '*/api/external/referentiel/deverser' => Http::response(['success' => true]),
            '*/api/external/companies/revoke' => Http::response(['success' => true]),
            '*' => Http::response(['success' => true]),
        ]);
    }

    // ── La clé ne se saisit plus ─────────────────────────────────────

    public function test_le_formulaire_des_parametres_n_accepte_plus_de_cle(): void
    {
        Http::fake();

        $this->enregistrerParametres(['comptaflow_sync_key' => 'cle_collee_a_la_main'])
            ->assertSessionHasNoErrors();

        $this->assertNull($this->entreprise->fresh()->comptaflow_sync_key,
            'Le formulaire des paramètres a de nouveau écrit la clé de liaison.');
        Http::assertNothingSent();
    }

    public function test_coller_la_cle_d_une_autre_entreprise_ne_lie_a_rien(): void
    {
        // Simulation d'attaque. La victime est liée ; l'assaillant connaît sa
        // clé et la colle dans ses propres paramètres. Avant ce lot, son
        // référentiel et ses écritures partaient dans les livres de la victime.
        $victime = Entreprise::create([
            'nom' => 'Victime SARL', 'regime_imposition' => 'RSI',
            'comptaflow_company_id' => 7, 'comptaflow_sync_status' => 'active',
        ]);
        $victime->comptaflow_sync_key = 'cptf_live_de_la_victime';
        $victime->save();

        Http::fake();

        $this->enregistrerParametres(['comptaflow_sync_key' => 'cptf_live_de_la_victime'])
            ->assertSessionHasNoErrors();

        $assaillant = $this->entreprise->fresh();

        $this->assertNull($assaillant->comptaflow_sync_key);
        $this->assertNull($assaillant->comptaflow_company_id);
        $this->assertFalse($assaillant->liaisonComptaflowActive());
        Http::assertNothingSent();
    }

    public function test_enregistrer_les_parametres_ne_coupe_pas_une_liaison_active(): void
    {
        // Le statut se déduisait de la présence du champ dans la requête :
        // enregistrer les paramètres sans y toucher faisait passer une liaison
        // active à `inactive`, et les écritures cessaient de partir sans que
        // rien ne le dise.
        $this->entreprise->comptaflow_sync_key = 'cptf_live_abcd';
        $this->entreprise->comptaflow_sync_status = 'active';
        $this->entreprise->comptaflow_company_id = 42;
        $this->entreprise->save();

        Http::fake();
        $this->enregistrerParametres(['telephone' => '0102030405'])->assertSessionHasNoErrors();

        $this->assertTrue($this->entreprise->fresh()->liaisonComptaflowActive());
    }

    // ── La demande ───────────────────────────────────────────────────

    public function test_l_entreprise_demande_sans_rien_saisir(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.entreprise.comptaflow.demander'))
            ->assertSessionHasNoErrors();

        $fraiche = $this->entreprise->fresh();

        $this->assertTrue($fraiche->demandeComptaflowEnAttente());
        $this->assertNotNull($fraiche->comptaflow_demande_le);
        $this->assertSame($this->admin->id, $fraiche->comptaflow_demande_par);
        $this->assertNull($fraiche->comptaflow_sync_key);
    }

    public function test_une_seconde_demande_ne_se_superpose_pas_a_la_premiere(): void
    {
        $this->actingAs($this->admin)->post(route('admin.entreprise.comptaflow.demander'));
        $premiere = $this->entreprise->fresh()->comptaflow_demande_le;

        $this->actingAs($this->admin)->post(route('admin.entreprise.comptaflow.demander'))
            ->assertSessionHasErrors('comptaflow');

        $this->assertEquals($premiere, $this->entreprise->fresh()->comptaflow_demande_le);
    }

    public function test_l_ecran_des_parametres_ne_propose_plus_de_champ_de_cle(): void
    {
        $corps = $this->actingAs($this->admin)
            ->get(route('admin.entreprise.parametres'))->assertOk()->getContent();

        $this->assertStringNotContainsString('name="comptaflow_sync_key"', $corps);
        $this->assertStringContainsString('Demander la liaison Comptaflow', $corps);
    }

    // ── La validation ────────────────────────────────────────────────

    public function test_la_validation_fait_delivrer_la_cle_par_comptaflow(): void
    {
        LiaisonComptaflowService::demander($this->entreprise, $this->admin);
        $this->comptaflowRepond();

        $this->actingAs($this->superadmin)
            ->post(route('superadmin.liaisons.valider', $this->entreprise));

        $fraiche = $this->entreprise->fresh();

        $this->assertTrue($fraiche->liaisonComptaflowActive());
        $this->assertSame('cptf_live_9f3b7d2a4c81', $fraiche->comptaflow_sync_key);
        $this->assertSame(42, (int) $fraiche->comptaflow_company_id);
        $this->assertSame(Entreprise::DEMANDE_VALIDEE, $fraiche->comptaflow_demande_statut);
        $this->assertNotNull($fraiche->comptaflow_liee_le);

        // Le provisionnement est le seul appel qui porte le secret serveur.
        Http::assertSent(fn ($requete) => str_contains($requete->url(), '/companies/provision')
            && $requete['secret'] === 'secret-serveur-de-test'
            && $requete['entreprise']['ncc'] === '2601234A');
    }

    public function test_le_referentiel_part_dans_la_foulee(): void
    {
        LiaisonComptaflowService::demander($this->entreprise, $this->admin);
        $this->comptaflowRepond();

        LiaisonComptaflowService::valider($this->entreprise);

        // Sans plan comptable, sans journaux et sans tiers, la première
        // écriture déversée retomberait chez Comptaflow sur des comptes qu'il
        // ne connaît pas.
        Http::assertSent(fn ($requete) => str_contains($requete->url(), '/referentiel/deverser'));
    }

    public function test_un_provisionnement_en_echec_ne_laisse_pas_de_liaison_a_moitie_ouverte(): void
    {
        LiaisonComptaflowService::demander($this->entreprise, $this->admin);

        Http::fake(['*' => Http::response(['success' => false, 'message' => 'Dossier refusé'], 500)]);

        $resultat = LiaisonComptaflowService::valider($this->entreprise);
        $fraiche = $this->entreprise->fresh();

        $this->assertFalse($resultat['success']);
        $this->assertFalse($fraiche->liaisonComptaflowActive());
        $this->assertNull($fraiche->comptaflow_sync_key);
        // La demande reste en attente : elle se rejoue.
        $this->assertTrue($fraiche->demandeComptaflowEnAttente());
    }

    public function test_un_comptaflow_sans_le_point_d_entree_le_dit(): void
    {
        LiaisonComptaflowService::demander($this->entreprise, $this->admin);

        Http::fake(['*' => Http::response(['message' => 'Not Found'], 404)]);

        $resultat = LiaisonComptaflowService::valider($this->entreprise);

        // 404 (Not Found — introuvable) veut dire ici « déploiement en
        // retard », pas « clé perdue ». Le message doit envoyer au bon endroit.
        $this->assertFalse($resultat['success']);
        $this->assertStringContainsString('companies/provision', $resultat['message']);
        $this->assertTrue($this->entreprise->fresh()->demandeComptaflowEnAttente());
    }

    public function test_une_entreprise_deja_liee_n_est_pas_provisionnee_deux_fois(): void
    {
        $this->entreprise->comptaflow_sync_key = 'cptf_live_abcd';
        $this->entreprise->comptaflow_sync_status = 'active';
        $this->entreprise->comptaflow_company_id = 42;
        $this->entreprise->save();

        Http::fake();
        $resultat = LiaisonComptaflowService::valider($this->entreprise->fresh());

        $this->assertFalse($resultat['success']);
        Http::assertNothingSent();
    }

    // ── Le refus ─────────────────────────────────────────────────────

    public function test_le_refus_porte_un_motif_que_l_entreprise_lit(): void
    {
        LiaisonComptaflowService::demander($this->entreprise, $this->admin);

        $this->actingAs($this->superadmin)->post(
            route('superadmin.liaisons.refuser', $this->entreprise),
            ['motif' => 'Le NCC déclaré ne correspond à aucune entreprise connue de la DGI.']
        )->assertSessionHasNoErrors();

        $this->assertSame(Entreprise::DEMANDE_REFUSEE, $this->entreprise->fresh()->comptaflow_demande_statut);

        $this->actingAs($this->admin)->get(route('admin.entreprise.parametres'))
            ->assertSee('ne correspond à aucune entreprise connue', false);
    }

    public function test_un_refus_muet_est_refuse(): void
    {
        LiaisonComptaflowService::demander($this->entreprise, $this->admin);

        $this->actingAs($this->superadmin)
            ->post(route('superadmin.liaisons.refuser', $this->entreprise), ['motif' => ''])
            ->assertSessionHasErrors('motif');
    }

    public function test_redemander_apres_un_refus_efface_le_motif_precedent(): void
    {
        LiaisonComptaflowService::demander($this->entreprise, $this->admin);
        LiaisonComptaflowService::refuser($this->entreprise, 'RCCM manquant');

        LiaisonComptaflowService::demander($this->entreprise->fresh(), $this->admin);

        $this->assertNull($this->entreprise->fresh()->comptaflow_refus_motif);
    }

    // ── La révocation ────────────────────────────────────────────────

    public function test_delier_revoque_la_cle_chez_comptaflow(): void
    {
        $this->entreprise->comptaflow_sync_key = 'cptf_live_abcd';
        $this->entreprise->comptaflow_sync_status = 'active';
        $this->entreprise->comptaflow_company_id = 42;
        $this->entreprise->save();

        $this->comptaflowRepond();

        $this->actingAs($this->superadmin)
            ->delete(route('superadmin.liaisons.delierEntreprise', $this->entreprise));

        $fraiche = $this->entreprise->fresh();

        $this->assertNull($fraiche->comptaflow_sync_key);
        $this->assertNull($fraiche->comptaflow_company_id);
        $this->assertNotNull($fraiche->comptaflow_revoquee_le);

        // Une clé oubliée chez l'autre est une clé qui marche : la révocation
        // doit être dite à Comptaflow, en portant la clé qu'on révoque.
        Http::assertSent(fn ($requete) => str_contains($requete->url(), '/companies/revoke')
            && $requete->hasHeader('X-Company-Key', 'cptf_live_abcd'));
    }

    // ── Ce que la clé authentifie ────────────────────────────────────

    public function test_le_deversement_porte_la_cle_en_entete(): void
    {
        $this->entreprise->comptaflow_sync_key = 'cptf_live_abcd';
        $this->entreprise->comptaflow_sync_status = 'active';
        $this->entreprise->comptaflow_company_id = 42;
        $this->entreprise->save();

        $this->comptaflowRepond();

        DeversementReferentielService::deverser($this->entreprise->fresh());

        // Le secret partagé est le même pour toutes les entreprises : il ne
        // dit pas laquelle appelle. La clé du dossier, si.
        Http::assertSent(fn ($requete) => str_contains($requete->url(), '/referentiel/deverser')
            && $requete->hasHeader('X-Company-Key', 'cptf_live_abcd'));
    }

    public function test_une_liaison_deja_ouverte_n_est_pas_rouverte_a_chaque_deversement(): void
    {
        $this->entreprise->comptaflow_sync_key = 'cptf_live_abcd';
        $this->entreprise->comptaflow_sync_status = 'active';
        $this->entreprise->comptaflow_company_id = 42;
        $this->entreprise->save();

        $this->comptaflowRepond();

        DeversementReferentielService::deverser($this->entreprise->fresh());

        Http::assertNotSent(fn ($requete) => str_contains($requete->url(), '/link-company'));
    }

    // ── Ce que la base et les écrans laissent voir ───────────────────

    public function test_la_cle_est_chiffree_en_base(): void
    {
        $this->entreprise->comptaflow_sync_key = 'cptf_live_9f3b7d2a4c81';
        $this->entreprise->save();

        $brut = DB::table('entreprises')->where('id', $this->entreprise->id)->value('comptaflow_sync_key');

        // Une sauvegarde égarée, ou un accès en lecture à la table, livrait
        // toutes les clés en clair — donc l'écriture dans les livres de
        // chaque entreprise.
        $this->assertNotSame('cptf_live_9f3b7d2a4c81', $brut);
        $this->assertSame('cptf_live_9f3b7d2a4c81', Crypt::decryptString($brut));
        $this->assertSame('cptf_live_9f3b7d2a4c81', $this->entreprise->fresh()->comptaflow_sync_key);
    }

    public function test_aucun_ecran_n_affiche_la_cle_entiere(): void
    {
        LiaisonComptaflowService::demander($this->entreprise, $this->admin);
        $this->comptaflowRepond();
        LiaisonComptaflowService::valider($this->entreprise);

        $parametres = $this->actingAs($this->admin)
            ->get(route('admin.entreprise.parametres'))->assertOk()->getContent();
        $liaisons = $this->actingAs($this->superadmin)
            ->get(route('superadmin.liaisons.index'))->assertOk()->getContent();

        foreach ([$parametres, $liaisons] as $corps) {
            $this->assertStringNotContainsString('cptf_live_9f3b7d2a4c81', $corps);
        }

        // Quatre caractères : de quoi reconnaître la clé, pas de quoi s'en
        // servir.
        $this->assertStringContainsString('••••4c81', $liaisons);
    }

    // ── Qui a le droit ───────────────────────────────────────────────

    public function test_un_administrateur_d_entreprise_ne_valide_pas_sa_propre_demande(): void
    {
        LiaisonComptaflowService::demander($this->entreprise, $this->admin);
        Http::fake();

        $this->actingAs($this->admin)
            ->post(route('superadmin.liaisons.valider', $this->entreprise))
            ->assertForbidden();

        $this->assertFalse($this->entreprise->fresh()->liaisonComptaflowActive());
        Http::assertNothingSent();
    }

    public function test_un_administrateur_ne_delie_pas_une_entreprise(): void
    {
        Http::fake();

        $this->actingAs($this->admin)
            ->delete(route('superadmin.liaisons.delierEntreprise', $this->entreprise))
            ->assertForbidden();

        Http::assertNothingSent();
    }
}
