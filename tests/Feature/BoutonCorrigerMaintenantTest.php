<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\FneCredential;
use App\Modules\Admin\Modeles\FneRejet;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\PortailFneFiche;
use App\Modules\Admin\Modeles\PortailFneImport;
use App\Modules\Admin\Modeles\PortailFnePointFacturation;
use App\Modules\Admin\Modeles\Vente;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Le bouton « Lancer la correction » du pop-up, éprouvé sur ses cinq issues.
 *
 * `RejetFneControleur::corrigerMaintenant()` enchaîne trois gestes en un clic :
 * ranger ce que le scraper a déposé, rapprocher le rejet du relevé, appliquer
 * la correction. Aucune épreuve ne le couvrait — seul `appliquer`, le geste
 * unitaire de l'écran des rejets, l'était.
 *
 * | Situation | Ce que le bouton doit rendre |
 * |---|---|
 * | un seul nom déclaré au portail | `succes` : renommé, la pièce repart |
 * | deux noms déclarés | `avertissement` : rien n'est touché |
 * | aucun relevé rangé | `avertissement` : le relevé n'est pas arrivé |
 * | rejet réseau | `erreur` : la DGI n'a rien refusé |
 * | rejet d'une autre entreprise | 404 |
 *
 * La deuxième ligne est celle observée en production le 31/08/2026 : le portail
 * de l'entreprise déclare « FACTURATION SIEGE » **et** « FACTURATION TEST 2 »,
 * le garde-fou d'ambiguïté rend la main, et le pop-up — qui propose le bouton
 * sans vérifier cette condition, là où l'écran des rejets le masque — laisse
 * croire à une panne.
 */
class BoutonCorrigerMaintenantTest extends TestCase
{
    use RefreshDatabase;

    private const LOGIN = '1864699A';

    private Entreprise $mienne;
    private PointDeVente $monMagasin;
    private Utilisateur $moi;
    private Client $client;
    private string $dossier;

    /** Ce que la plateforme répondra au renvoi. */
    private $reponseDeLaDgi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mienne = $this->uneEntreprise('DC-KNOWING CGA', 'CI-ABJ-2026-B-01111', self::LOGIN);

        FneCredential::create([
            'entreprise_id' => $this->mienne->id,
            'cle_test'      => 'fne_test_cle',
            'statut'        => 'test',
        ]);

        $this->monMagasin = PointDeVente::create([
            'entreprise_id' => $this->mienne->id,
            'nom'           => 'FACTURATION SIEGES', // le S de trop, comme en production
            'ville'         => 'Abidjan',
            'commune'       => 'Cocody',
        ]);

        $this->client = Client::create(['entreprise_id' => $this->mienne->id, 'nom' => 'Client X']);

        $this->moi = Utilisateur::create([
            'nom' => 'Yao', 'prenom' => 'Adjoua', 'email' => 'adjoua@exemple.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $this->mienne->id,
            'point_de_vente_id' => $this->monMagasin->id,
        ]);

        $this->actingAs($this->moi)
            ->withSession(['point_de_vente_actif_id' => $this->monMagasin->id]);

        // Le bouton appelle `portail-fne:importer` : il lui faut un dossier à
        // lui, sans quoi l'épreuve range les relevés réels du poste.
        $this->dossier = storage_path('framework/testing/corriger-' . uniqid());
        mkdir($this->dossier, 0777, true);
        config(['selflow.portail_fne.dossier_import' => $this->dossier]);

        $this->reponseDeLaDgi = fn () => Http::response([
            'reference' => '9606123456789', 'token' => 'tok',
            'invoice_id' => 'b1f0a7c2-1111-4c3a-9d21-9f0c7a5e4d10',
            'document_url' => 'x', 'balance_sticker' => 4990,
        ], 200);

        Http::fake(fn () => ($this->reponseDeLaDgi)());
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dossier . '/*') ?: [] as $fichier) {
            unlink($fichier);
        }
        @rmdir($this->dossier);

        parent::tearDown();
    }

    public function test_un_seul_nom_declare_le_bouton_renomme_et_renvoie_la_piece(): void
    {
        $vente = $this->uneVente('FA-0042');
        $rejet = FneRejet::consigner($vente, $this->refus());

        $this->unReleve(['FACTURATION SIEGE']);

        $this->post(route('admin.fne.rejets.corriger_maintenant', $rejet))
            ->assertRedirect()
            ->assertSessionHas('succes');

        // Le point de vente porte le nom déclaré au portail...
        $this->assertSame('FACTURATION SIEGE', $this->monMagasin->refresh()->nom);

        // ...et la pièce est repartie dans la foulée, pas en file.
        $this->assertTrue((bool) $vente->refresh()->normalise);
    }

    public function test_deux_noms_declares_le_bouton_ne_corrige_rien_et_le_dit(): void
    {
        $vente = $this->uneVente('FA-0042');
        $rejet = FneRejet::consigner($vente, $this->refus());

        // Le cas de production : le portail déclare deux points de facturation.
        $this->unReleve(['FACTURATION SIEGE', 'FACTURATION TEST 2']);

        $reponse = $this->post(route('admin.fne.rejets.corriger_maintenant', $rejet));

        $reponse->assertRedirect()->assertSessionHas('avertissement');
        $this->assertStringContainsString(
            'aucune correction automatique applicable',
            session('avertissement')
        );

        // Rien n'a bougé : ni le nom, ni la pièce.
        $this->assertSame('FACTURATION SIEGES', $this->monMagasin->refresh()->nom);
        $this->assertFalse((bool) $vente->refresh()->normalise);

        // Le rapprochement, lui, a bien eu lieu : le rejet porte son constat.
        $rejet->refresh();
        $this->assertSame(FneRejet::STATUT_DIAGNOSTIQUE, $rejet->statut);
        $this->assertSame(
            ['FACTURATION SIEGE', 'FACTURATION TEST 2'],
            $rejet->diagnostic['champs'][0]['portail']
        );
    }

    public function test_sans_releve_le_bouton_dit_que_le_portail_n_a_pas_repondu(): void
    {
        $rejet = FneRejet::consigner($this->uneVente('FA-0042'), $this->refus());

        $this->post(route('admin.fne.rejets.corriger_maintenant', $rejet))
            ->assertRedirect()
            ->assertSessionHas('avertissement');

        $this->assertStringContainsString("n'est pas encore arrivé", session('avertissement'));
        $this->assertSame('FACTURATION SIEGES', $this->monMagasin->refresh()->nom);
    }

    public function test_un_rejet_reseau_n_a_rien_a_corriger(): void
    {
        $rejet = FneRejet::consigner($this->uneVente('FA-0042'), [
            'success' => false,
            'message' => 'La plateforme FNE est injoignable (connexion impossible).',
            'errors'  => [],
        ]);
        $rejet->update(['cause' => FneRejet::CAUSE_RESEAU]);

        $this->unReleve(['FACTURATION SIEGE']);

        $this->post(route('admin.fne.rejets.corriger_maintenant', $rejet))
            ->assertRedirect()
            ->assertSessionHas('erreur');

        $this->assertSame('FACTURATION SIEGES', $this->monMagasin->refresh()->nom);
    }

    public function test_le_rejet_d_une_autre_entreprise_n_existe_pas(): void
    {
        $sienne = $this->uneEntreprise('Quincaillerie rivale', 'CI-ABJ-2026-B-02222', '9999999Z');

        $sonMagasin = PointDeVente::create([
            'entreprise_id' => $sienne->id,
            'nom' => 'Magasin rival', 'ville' => 'Abidjan', 'commune' => 'Yopougon',
        ]);

        $saVente = Vente::create([
            'point_de_vente_id' => $sonMagasin->id,
            'numero_facture'    => 'FA-RIVALE-001',
            'date_vente'        => now()->toDateString(),
            'mode_paiement'     => 'Espèces',
            'montant_ht'        => 100000, 'montant_tva' => 18000, 'montant_ttc' => 118000,
            'etape'             => 'Facture',
        ]);

        $sonRejet = FneRejet::consigner($saVente, $this->refus());

        $this->post(route('admin.fne.rejets.corriger_maintenant', $sonRejet))->assertNotFound();
    }

    // ──────────────────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function refus(): array
    {
        return [
            'success' => false,
            'message' => 'La normalisation DGI a échoué (HTTP 400) : Invalid point of sale.',
            'errors'  => ['api_error' => json_encode([
                'errors' => ['pointOfSale' => ['invalid' => 'Point of sale is invalid']],
            ])],
        ];
    }

    private function uneEntreprise(string $nom, string $rccm, string $ncc): Entreprise
    {
        return Entreprise::create([
            'nom' => $nom, 'regime_imposition' => 'RNI', 'adresse' => 'Abidjan',
            'rccm' => $rccm, 'ncc' => $ncc, 'gerant_fonction' => 'Gérant',
            'secteur_activite' => ['Commerce'],
            'modules_actifs' => ['principal', 'ventes', 'achats', 'comptabilite', 'points_de_vente'],
        ]);
    }

    private function uneVente(string $numero): Vente
    {
        return Vente::create([
            'point_de_vente_id' => $this->monMagasin->id,
            'client_id'         => $this->client->id,
            'numero_facture'    => $numero,
            'date_vente'        => now()->toDateString(),
            'mode_paiement'     => 'Espèces',
            'montant_ht'        => 100000, 'montant_tva' => 18000, 'montant_ttc' => 118000,
            'etape'             => 'Facture',
        ]);
    }

    /** @param  array<int, string>  $noms */
    private function unReleve(array $noms): PortailFneImport
    {
        $import = PortailFneImport::create([
            'entreprise_id'     => $this->mienne->id,
            'login'             => self::LOGIN,
            'date_scraping'     => '2026-08-31',
            'type'              => PortailFneImport::TYPE_FICHE,
            'fichier_nom'       => self::LOGIN . '_20260831.json',
            'fichier_empreinte' => hash('sha256', uniqid('', true)),
            'statut'            => PortailFneImport::STATUT_IMPORTE,
        ]);

        PortailFneFiche::create([
            'import_id'            => $import->id,
            'entreprise_id'        => $this->mienne->id,
            'login'                => self::LOGIN,
            'date_scraping'        => '2026-08-31',
            'timbre_quittance'     => true,
            'sticker_solde_alerte' => 5000,
        ]);

        foreach ($noms as $nom) {
            PortailFnePointFacturation::create([
                'import_id'        => $import->id,
                'entreprise_id'    => $this->mienne->id,
                'login'            => self::LOGIN,
                'date_scraping'    => '2026-08-31',
                'nom'              => $nom,
                'outil'            => 'Application FNE',
                'statut'           => '1',
                'etablissement_id' => '42200613-f402-40a8-bd4d-a778bb5b96f0',
            ]);
        }

        return $import;
    }
}
