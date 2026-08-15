<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\Fournisseur;
use App\Modules\Admin\Modeles\PlanComptable;
use App\Modules\Admin\Services\NumerotationTiersService;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le numéro de tiers, et ce qu'il ne faut pas confondre avec lui.
 *
 * | Notion | Colonne | Exemple |
 * |---|---|---|
 * | Compte général de rattachement | `compte_comptable` | `411000` |
 * | Numéro de tiers | `numero_tiers` | `411001`, `411KONE` |
 *
 * Le compte général est celui du plan comptable, et son intitulé — « Clients »
 * — ne change jamais. Le numéro de tiers désigne un client précis.
 *
 * **La numérotation automatique démarrait à `411000`** : le premier client de
 * chaque entreprise portait, comme numéro de tiers, le numéro du compte
 * collectif. Les deux notions se rejoignaient en base, et le relevé de ce
 * client remontait le solde de tous les autres.
 */
class NumerotationTiersTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private Utilisateur $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create([
            'nom' => 'Quincaillerie du Bandama',
            'modules_actifs' => ['principal', 'tiers', 'comptabilite'],
        ]);

        foreach ([['411000', 'Clients'], ['401000', 'Fournisseurs']] as [$numero, $libelle]) {
            PlanComptable::create([
                'entreprise_id' => null, 'numero' => $numero, 'libelle' => $libelle,
            ]);
        }

        $this->admin = Utilisateur::create([
            'nom' => 'Yao', 'prenom' => 'Adjoua', 'email' => 'adjoua-tiers@exemple.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $this->entreprise->id,
        ]);

        $this->actingAs($this->admin);
    }

    /**
     * Changer la convention, et faire oublier l'ancienne au compte connecté.
     *
     * `actingAs` garde une même instance d'utilisateur pour toute la durée du
     * test : sa relation `entreprise` reste celle chargée au premier appel. En
     * production chaque requête repart d'une lecture neuve ; ici il faut le
     * dire.
     */
    private function convention(string $convention): void
    {
        $this->entreprise->update(['numerotation_tiers' => $convention]);
        $this->admin->unsetRelation('entreprise');
    }

    private function creerClient(array $champs = [])
    {
        return $this->post(route('admin.clients.creer'), array_merge([
            'nom'                => 'Kouassi Koné',
            'type_facturation'   => 'B2C',
            'compte_comptable'   => '411000',
            'auto_numero_tiers'  => '1',
        ], $champs));
    }

    // ══════════════ Le premier client ne porte plus le collectif ══════════════

    public function test_le_premier_client_ne_porte_pas_le_numero_du_compte_collectif(): void
    {
        // C'était le défaut : la séquence démarrait à 411000, et le relevé de
        // ce client remontait le solde de tous les autres.
        $this->creerClient();

        $client = Client::where('entreprise_id', $this->entreprise->id)->firstOrFail();

        $this->assertSame('411001', $client->numero_tiers);
        $this->assertSame('411000', $client->compte_comptable);
        $this->assertNotSame($client->compte_comptable, $client->numero_tiers);
    }

    public function test_le_premier_fournisseur_non_plus(): void
    {
        $this->post(route('admin.fournisseurs.creer'), [
            'nom' => 'CDCI Distribution', 'type_facturation' => 'B2C',
            'compte_comptable' => '401000', 'auto_numero_tiers' => '1',
        ]);

        $fournisseur = Fournisseur::where('entreprise_id', $this->entreprise->id)->firstOrFail();

        $this->assertSame('401001', $fournisseur->numero_tiers);
        $this->assertNotSame($fournisseur->compte_comptable, $fournisseur->numero_tiers);
    }

    public function test_la_sequence_avance(): void
    {
        $this->creerClient(['nom' => 'Premier client']);
        $this->creerClient(['nom' => 'Second client']);

        $this->assertSame(
            ['411001', '411002'],
            Client::where('entreprise_id', $this->entreprise->id)
                ->orderBy('numero_tiers')->pluck('numero_tiers')->all()
        );
    }

    // ══════════════ La convention par le nom ══════════════

    public function test_la_convention_par_le_nom_tire_le_radical_du_nom(): void
    {
        $this->convention(NumerotationTiersService::NOM);

        $this->creerClient(['nom' => 'Kouassi Koné']);

        $this->assertSame(
            '411KOUASSIK',
            Client::where('entreprise_id', $this->entreprise->id)->value('numero_tiers')
        );
    }

    public function test_deux_homonymes_recoivent_des_numeros_distincts(): void
    {
        $this->convention(NumerotationTiersService::NOM);

        $this->creerClient(['nom' => 'Koné']);
        $this->creerClient(['nom' => 'Koné']);

        $numeros = Client::where('entreprise_id', $this->entreprise->id)
            ->orderBy('id')->pluck('numero_tiers')->all();

        $this->assertSame(['411KONE', '411KONE2'], $numeros);
    }

    public function test_un_nom_sans_lettre_retombe_sur_la_sequence(): void
    {
        // « 123 » ne laisse aucun radical : sans ce repli, le numéro se
        // réduirait à sa racine — c'est-à-dire au compte collectif.
        $this->convention(NumerotationTiersService::NOM);

        $this->creerClient(['nom' => '123']);

        $numero = Client::where('entreprise_id', $this->entreprise->id)->value('numero_tiers');

        $this->assertSame('411001', $numero);
        $this->assertNotSame('411', $numero);
    }

    public function test_les_deux_conventions_cohabitent(): void
    {
        // Une entreprise qui change d'avis en cours de route ne doit pas voir
        // sa séquence repartir de 001 et heurter un numéro déjà pris.
        $this->creerClient(['nom' => 'Premier client']);      // 411001

        $this->convention(NumerotationTiersService::NOM);
        $this->creerClient(['nom' => 'Koné']);                // 411KONE

        $this->convention(NumerotationTiersService::SEQUENCE);
        $this->creerClient(['nom' => 'Troisième client']);    // 411002

        $this->assertSame(
            ['411001', '411KONE', '411002'],
            Client::where('entreprise_id', $this->entreprise->id)->orderBy('id')->pluck('numero_tiers')->all()
        );
    }

    // ══════════════ Ce que la saisie manuelle refuse ══════════════

    public function test_le_numero_saisi_ne_peut_pas_etre_le_compte_collectif(): void
    {
        $this->creerClient(['auto_numero_tiers' => '0', 'numero_tiers' => '411000'])
            ->assertSessionHasErrors('numero_tiers');

        $this->assertSame(0, Client::where('entreprise_id', $this->entreprise->id)->count());
    }

    public function test_le_numero_saisi_suit_la_racine_du_compte_de_rattachement(): void
    {
        // Un tiers 401… rattaché au collectif clients ferait partir l'écriture
        // sur le mauvais compte, et le grand livre deviendrait faux sans
        // qu'aucun contrôle ne s'en émeuve.
        $this->creerClient(['auto_numero_tiers' => '0', 'numero_tiers' => '401001'])
            ->assertSessionHasErrors('numero_tiers');
    }

    public function test_un_numero_avec_des_lettres_est_accepte(): void
    {
        // L'ancienne expression `^411[0-9]*$` refusait 411KONE, qui est
        // pourtant une convention répandue.
        $this->creerClient(['auto_numero_tiers' => '0', 'numero_tiers' => '411KONE'])
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('411KONE', Client::where('entreprise_id', $this->entreprise->id)->value('numero_tiers'));
    }

    // ══════════════ Le service, isolément ══════════════

    public function test_la_coherence_se_juge_sur_la_racine(): void
    {
        $this->assertTrue(NumerotationTiersService::estCoherent('411001', '411000'));
        $this->assertTrue(NumerotationTiersService::estCoherent('411KONE', '411000'));
        $this->assertFalse(NumerotationTiersService::estCoherent('401001', '411000'));
        $this->assertFalse(NumerotationTiersService::estCoherent(null, '411000'));
    }

    public function test_le_service_reconnait_le_compte_collectif(): void
    {
        $this->assertTrue(NumerotationTiersService::estLeCompteCollectif('411000', '411000'));
        $this->assertFalse(NumerotationTiersService::estLeCompteCollectif('411001', '411000'));
    }

    // ══════════════ Le client de passage ══════════════

    public function test_le_client_divers_porte_son_propre_numero_et_son_rattachement(): void
    {
        // Une vente sans client nommé laissait `compte_tiers` vide : tout se
        // rangeait sur le collectif 411000, et le grand livre ne distinguait
        // plus les ventes de comptoir des créances d'un client identifié.
        $divers = Client::divers($this->entreprise);

        $this->assertSame('411DIVERS', $divers->numero_tiers);
        $this->assertSame('411000', $divers->compte_comptable);
        $this->assertSame('Client divers', $divers->nom);
    }

    public function test_le_fournisseur_divers_est_son_pendant(): void
    {
        $divers = Fournisseur::divers($this->entreprise);

        $this->assertSame('401DIVERS', $divers->numero_tiers);
        $this->assertSame('401000', $divers->compte_comptable);
    }

    public function test_une_seule_fiche_divers_par_entreprise(): void
    {
        // Une fiche par ticket de caisse ferait gonfler le plan de tiers d'une
        // ligne par vente de comptoir.
        Client::divers($this->entreprise);
        Client::divers($this->entreprise);
        Client::divers($this->entreprise);

        $this->assertSame(1, Client::where('entreprise_id', $this->entreprise->id)
            ->where('numero_tiers', Client::NUMERO_DIVERS)->count());
    }

    public function test_chaque_entreprise_a_son_propre_client_divers(): void
    {
        $autre = Entreprise::create(['nom' => 'Quincaillerie rivale']);

        $ici     = Client::divers($this->entreprise);
        $ailleurs = Client::divers($autre);

        $this->assertNotSame($ici->id, $ailleurs->id);
        $this->assertSame($this->entreprise->id, $ici->entreprise_id);
        $this->assertSame($autre->id, $ailleurs->entreprise_id);
    }

    public function test_le_client_divers_ne_gene_pas_la_sequence(): void
    {
        // Son numéro n'est pas numérique : il ne doit pas être lu comme un
        // rang, ni faire sauter la séquence.
        Client::divers($this->entreprise);

        $this->creerClient(['nom' => 'Kouassi Koné']);

        $this->assertSame(
            '411001',
            Client::where('entreprise_id', $this->entreprise->id)
                ->where('numero_tiers', '!=', Client::NUMERO_DIVERS)->value('numero_tiers')
        );
    }

    // ══════════════ Cloisonnement ══════════════

    public function test_deux_entreprises_peuvent_porter_le_meme_numero_de_tiers(): void
    {
        // Le plan de tiers est propre à chaque entreprise : la séquence de
        // l'une ne doit pas dépendre de ce que l'autre a créé.
        $autre = Entreprise::create(['nom' => 'Quincaillerie rivale']);
        Client::create([
            'entreprise_id' => $autre->id, 'nom' => 'Client de la rivale',
            'compte_comptable' => '411000', 'numero_tiers' => '411001',
        ]);

        $this->creerClient();

        $this->assertSame(
            '411001',
            Client::where('entreprise_id', $this->entreprise->id)->value('numero_tiers')
        );
    }
}
