<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\AchatDetail;
use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\Fournisseur;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Modeles\VenteDetail;
use App\Modules\Admin\Services\FneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Conformité des payloads envoyés à la plateforme FNE (DGI).
 *
 * Les cas de référence sont ceux de « CONFIGURATION FNE » :
 *   - exemple de json vente.txt
 *   - BAPA( déclaration des bordereau d'achat).txt
 *   - json avoir.txt
 */
class FnePayloadTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private PointDeVente $pointDeVente;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'selflow.fne_api_url_sandbox'    => 'https://fne-sandbox.test',
            'selflow.fne_api_url_production' => 'https://fne.test',
        ]);

        $this->entreprise = Entreprise::create([
            'nom'                     => 'Orange Riviera Mpouto',
            'ncc'                     => '9606123E',
            'telephone'               => '0102030405',
            'email'                   => 'contact@selflow.test',
            'regime_imposition'       => 'RNI',
            'facture_autres_mentions' => 'Soyez les bienvenus',
            'pied_de_page_facture'    => 'Toujours la pour votre bonheur',
        ]);

        $this->pointDeVente = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom'           => '23',
            'ville'         => 'Abidjan',
            'commune'       => 'Cocody',
            'responsable'   => 'Ali Hassan',
            'statut'        => 'Ouvert',
        ]);

        $this->entreprise->fneCredential()->create([
            'cle_reelle' => 'cle-de-test',
            'statut'     => 'validee',
        ]);
    }

    /**
     * Dernier corps de requête envoyé à l'API FNE.
     */
    private function payloadEnvoye(): array
    {
        $requetes = Http::recorded();
        $this->assertNotEmpty($requetes, 'Aucun appel HTTP n\'a été émis vers la FNE.');

        return json_decode($requetes[0][0]->body(), true);
    }

    private function simulerReponseFne(array $corps = []): void
    {
        Http::fake([
            '*' => Http::response(array_merge([
                'ncc'             => '9606123E',
                'reference'       => '9606123E25000000019',
                'token'           => 'https://fne.test/verification/019465c1',
                'warning'         => false,
                'balance_sticker' => 179,
                'invoice'         => ['id' => 'e2b2d8da-a532-4c08-9182-f5b428ca468d', 'items' => []],
            ], $corps), 200),
        ]);
    }

    private function creerProduit(array $attributs = []): Produit
    {
        return Produit::create(array_merge([
            'entreprise_id' => $this->entreprise->id,
            'reference'     => 'ref009',
            'nom'           => 'sac de riz Dinor 5 x 5',
            'type'          => 'marchandise',
            'prix_achat'    => 15000,
            'prix_vente'    => 20000,
            'taux_tva'      => 18,
            'unite'         => 'pcs',
            'compte_vente'  => '701000',
            'compte_achat'  => '601000',
        ], $attributs));
    }

    // ─── Facture de vente ────────────────────────────────────────────────

    public function test_le_payload_de_vente_reprend_la_structure_de_la_specification_dgi(): void
    {
        $this->simulerReponseFne();

        $produit = $this->creerProduit();
        $produit->taxes()->create(['nom' => 'GRA', 'taux' => 5]);

        $client = Client::create([
            'entreprise_id'    => $this->entreprise->id,
            'nom'              => 'KPMG COTE D\'IVOIRE',
            'ncc'              => '9502363N',
            'telephone'        => '0709080765',
            'email'            => 'info@kpmg.ci',
            'type_facturation' => 'B2B',
        ]);

        $vente = Vente::create([
            'point_de_vente_id' => $this->pointDeVente->id,
            'client_id'         => $client->id,
            'numero_facture'    => 'FV-0001',
            'date_vente'        => now()->toDateString(),
            'mode_paiement'     => 'Mobile Money',
            'montant_ht'        => 540000,
            'montant_tva'       => 97200,
            'montant_ttc'       => 637200,
            'remise'            => 54000,
            'remise_taux'       => 10,
            'etape'             => 'Facture',
        ]);

        VenteDetail::create([
            'vente_id'      => $vente->id,
            'produit_id'    => $produit->id,
            'quantite'      => 30,
            'unite'         => 'pcs',
            'prix_unitaire' => 20000,
            'remise_taux'   => 10,
            'montant_tva'   => 97200,
            'montant_ttc'   => 637200,
        ]);

        $vente->details->first()->taxes()->create(['nom' => 'GRA', 'taux' => 5]);
        $vente->taxesPersonnalisees()->create(['nom' => 'DTD', 'taux' => 5, 'montant' => 31860]);

        FneService::normaliserFacture($vente->fresh());

        $payload = $this->payloadEnvoye();

        $this->assertSame('sale', $payload['invoiceType']);
        $this->assertSame('mobile-money', $payload['paymentMethod']);
        $this->assertSame('B2B', $payload['template']);
        $this->assertSame('9502363N', $payload['clientNcc']);
        $this->assertSame('23', $payload['pointOfSale']);
        $this->assertSame('Orange Riviera Mpouto', $payload['establishment']);
        $this->assertSame('Soyez les bienvenus', $payload['commercialMessage']);
        $this->assertSame('Toujours la pour votre bonheur', $payload['footer']);

        // La DGI attend un pourcentage, jamais un montant en francs
        $this->assertEquals(10, $payload['discount']);
        $this->assertEquals([['name' => 'DTD', 'amount' => 5]], $payload['customTaxes']);

        $article = $payload['items'][0];
        $this->assertSame('ref009', $article['reference']);
        $this->assertSame('sac de riz Dinor 5 x 5', $article['description']);
        $this->assertSame(30, $article['quantity']);
        $this->assertEquals(20000, $article['amount']);
        $this->assertEquals(10, $article['discount']);
        $this->assertSame('pcs', $article['measurementUnit']);
        $this->assertSame(['TVA'], $article['taxes']);
        $this->assertEquals([['name' => 'GRA', 'amount' => 5]], $article['customTaxes']);

        // `id` n'est attendu que par l'endpoint de remboursement
        $this->assertArrayNotHasKey('id', $article);
    }

    public function test_le_rattachement_a_un_recu_provient_de_la_case_cochee_et_non_de_l_absence_de_client(): void
    {
        $this->simulerReponseFne();

        $vente = $this->venteMinimale(['est_rne' => true, 'numero_rne' => '9606123E25000000001']);

        FneService::normaliserFacture($vente);

        $payload = $this->payloadEnvoye();
        $this->assertTrue($payload['isRne']);
        $this->assertSame('9606123E25000000001', $payload['rne']);
    }

    public function test_une_vente_sans_client_n_est_pas_declaree_rattachee_a_un_recu(): void
    {
        $this->simulerReponseFne();

        $vente = $this->venteMinimale();

        // Deuxième argument : reçu de passage côté Selflow, sans effet sur isRne
        FneService::normaliserFacture($vente, true);

        $payload = $this->payloadEnvoye();
        $this->assertFalse($payload['isRne']);
        $this->assertSame('', $payload['rne']);
    }

    public function test_un_client_sans_ncc_bascule_en_b2c(): void
    {
        $this->simulerReponseFne();

        FneService::normaliserFacture($this->venteMinimale());

        $this->assertSame('B2C', $this->payloadEnvoye()['template']);
    }

    public function test_les_mentions_de_la_piece_priment_sur_celles_de_l_entreprise(): void
    {
        $this->simulerReponseFne();

        $vente = $this->venteMinimale([
            'autres_mentions' => 'Mention propre a cette facture',
            'pied_de_page'    => 'Pied de page propre a cette facture',
        ]);

        FneService::normaliserFacture($vente);

        $payload = $this->payloadEnvoye();
        $this->assertSame('Mention propre a cette facture', $payload['commercialMessage']);
        $this->assertSame('Pied de page propre a cette facture', $payload['footer']);
    }

    public function test_les_mentions_sont_tronquees_a_248_caracteres(): void
    {
        $this->simulerReponseFne();

        $vente = $this->venteMinimale([
            'autres_mentions' => str_repeat('z', 248),
            'pied_de_page'    => str_repeat('z', 248),
        ]);

        FneService::normaliserFacture($vente);

        $payload = $this->payloadEnvoye();
        $this->assertSame(248, mb_strlen($payload['commercialMessage']));
        $this->assertSame(248, mb_strlen($payload['footer']));
    }

    public function test_une_vente_sans_remise_ni_taxe_produit_un_payload_neutre(): void
    {
        $this->simulerReponseFne();

        FneService::normaliserFacture($this->venteMinimale());

        $payload = $this->payloadEnvoye();
        $this->assertEquals(0, $payload['discount']);
        $this->assertSame([], $payload['customTaxes']);
        $this->assertEquals(0, $payload['items'][0]['discount']);
        $this->assertSame([], $payload['items'][0]['customTaxes']);
    }

    // ─── Codes TVA ───────────────────────────────────────────────────────

    public static function fournirTauxEtCodesTva(): array
    {
        return [
            'taux normal'                      => [18.0, 'RNI', 'TVA'],
            'taux reduit'                      => [9.0,  'RNI', 'TVAB'],
            'exoneration conventionnelle'      => [0.0,  'RNI', 'TVAC'],
            'exoneration legale (regime TEE)'  => [0.0,  'TEE', 'TVAD'],
            'exoneration legale (regime RNE)'  => [0.0,  'RNE', 'TVAD'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('fournirTauxEtCodesTva')]
    public function test_le_code_tva_est_deduit_du_taux_et_du_regime(float $taux, string $regime, string $attendu): void
    {
        $this->assertSame($attendu, Produit::deduireCodeTva($taux, $regime));
    }

    public function test_le_code_tva_choisi_manuellement_prime_sur_la_deduction(): void
    {
        $produit = $this->creerProduit([
            'taux_tva'        => 0,
            'code_tva'        => 'TVAD',
            'code_tva_manuel' => true,
        ]);

        $this->assertSame('TVAD', $produit->codeTvaFne('RNI'));
    }

    public function test_le_code_tva_repasse_en_deduction_quand_le_choix_manuel_est_decoche(): void
    {
        $produit = $this->creerProduit([
            'taux_tva'        => 0,
            'code_tva'        => 'TVAD',
            'code_tva_manuel' => false,
        ]);

        $this->assertSame('TVAC', $produit->codeTvaFne('RNI'));
    }

    // ─── Bordereau d'achat (BAPA) ────────────────────────────────────────

    public function test_le_payload_bapa_designe_le_fournisseur_comme_tiers(): void
    {
        $this->simulerReponseFne();

        $fournisseur = Fournisseur::create([
            'entreprise_id' => $this->entreprise->id,
            'nom'           => 'COOPERATION DU GRAND OUEST',
            'telephone'     => '0709080765',
            'email'         => 'info@cgo.ci',
        ]);

        $produit = $this->creerProduit(['reference' => 'ref001', 'nom' => 'Cacao Brut premier choix', 'unite' => 'bidon']);

        $achat = Achat::create([
            'point_de_vente_id' => $this->pointDeVente->id,
            'fournisseur_id'    => $fournisseur->id,
            'numero_facture'    => 'BAPA-0001',
            'date_achat'        => now()->toDateString(),
            'mode_paiement'     => 'Mobile Money',
            'montant_ht'        => 3960000,
            'montant_tva'       => 0,
            'montant_ttc'       => 3960000,
            'remise'            => 396000,
            'remise_taux'       => 10,
            'type_facture'      => 'bapa',
            'etape'             => 'Facture',
            'est_rne'           => true,
            'numero_rne'        => '9606123E25000000002',
        ]);

        AchatDetail::create([
            'achat_id'      => $achat->id,
            'produit_id'    => $produit->id,
            'quantite'      => 2000,
            'unite'         => 'bidon',
            'prix_unitaire' => 2200,
            'remise_taux'   => 10,
            'montant_tva'   => 0,
            'montant_ttc'   => 3960000,
        ]);

        FneService::normaliserAchatBapa($achat->fresh());

        $payload = $this->payloadEnvoye();

        $this->assertSame('purchase', $payload['invoiceType']);
        // Sur un bordereau d'achat, le « client » au sens DGI est le fournisseur
        $this->assertSame('COOPERATION DU GRAND OUEST', $payload['clientCompanyName']);
        $this->assertSame('0709080765', $payload['clientPhone']);
        $this->assertSame('info@cgo.ci', $payload['clientEmail']);
        $this->assertSame('Ali Hassan', $payload['clientSellerName']);
        $this->assertArrayNotHasKey('clientNcc', $payload);

        $this->assertTrue($payload['isRne']);
        $this->assertSame('9606123E25000000002', $payload['rne']);
        $this->assertEquals(10, $payload['discount']);
        $this->assertEquals(10, $payload['items'][0]['discount']);
        $this->assertSame('bidon', $payload['items'][0]['measurementUnit']);
    }

    // ─── Facture d'avoir ─────────────────────────────────────────────────

    public function test_le_payload_d_avoir_ne_contient_que_les_articles_a_retourner(): void
    {
        $this->simulerReponseFne(['reference' => 'A9606123E2500000006']);

        $produit = $this->creerProduit();

        $facture = $this->venteMinimale([
            'fne_invoice_id' => 'e2b2d8da-a532-4c08-9182-f5b428ca468d',
        ], $produit);
        $facture->details->first()->update([
            'fne_invoice_item_id' => 'bf9cc241-9b5f-4d26-a570-aa8e682a759e',
        ]);

        $avoir = Vente::create([
            'point_de_vente_id' => $this->pointDeVente->id,
            'numero_facture'    => 'AV-0001',
            'date_vente'        => now()->toDateString(),
            'mode_paiement'     => 'Caisse',
            'montant_ht'        => 20000,
            'montant_tva'       => 3600,
            'montant_ttc'       => 23600,
            'type_facture'      => 'avoir',
            'parent_id'         => $facture->id,
            'etape'             => 'Facture',
        ]);

        VenteDetail::create([
            'vente_id'            => $avoir->id,
            'produit_id'          => $produit->id,
            'quantite'            => 20,
            'unite'               => 'pcs',
            'prix_unitaire'       => 20000,
            'montant_tva'         => 3600,
            'montant_ttc'         => 23600,
            'fne_invoice_item_id' => 'bf9cc241-9b5f-4d26-a570-aa8e682a759e',
        ]);

        FneService::normaliserFacture($avoir->fresh());

        $payload = $this->payloadEnvoye();

        $this->assertSame(['items'], array_keys($payload));
        $this->assertSame([
            ['id' => 'bf9cc241-9b5f-4d26-a570-aa8e682a759e', 'quantity' => 20],
        ], $payload['items']);
    }

    // ─── Nature des pièces : facture vs reçu ─────────────────────────────

    public function test_une_vente_au_comptant_sans_client_reste_une_facture(): void
    {
        // Le registre classait toute vente sans client comme un « Reçu ».
        $vente = $this->venteMinimale(['mode_paiement' => 'Caisse']);

        $this->assertSame(Vente::TYPE_FACTURE, $vente->type_piece);
        $this->assertFalse($vente->estRecu());
        $this->assertSame('Facture', $vente->libelleTypeDocument());
    }

    public function test_un_recu_est_marque_comme_tel(): void
    {
        $recu = $this->venteMinimale(['type_piece' => Vente::TYPE_RECU]);

        $this->assertTrue($recu->estRecu());
        $this->assertSame('Reçu', $recu->libelleTypeDocument());
    }

    public function test_un_avoir_garde_son_libelle_propre(): void
    {
        $avoir = $this->venteMinimale(['type_facture' => 'avoir']);

        $this->assertSame('Facture d\'avoir', $avoir->libelleTypeDocument());
    }

    public function test_les_deux_pieces_converties_se_pointent_mutuellement(): void
    {
        $recu    = $this->venteMinimale(['type_piece' => Vente::TYPE_RECU]);
        $facture = $this->venteMinimale(['piece_liee_id' => $recu->id]);
        $recu->update(['piece_liee_id' => $facture->id]);

        $this->assertTrue($facture->fresh()->pieceLiee->estRecu());
        $this->assertSame($facture->id, $recu->fresh()->pieceLiee->id);
    }

    /**
     * Vente d'une ligne, sans client ni remise : le socle des cas ci-dessus.
     */
    private function venteMinimale(array $attributs = [], ?Produit $produit = null): Vente
    {
        $produit ??= $this->creerProduit(['reference' => 'ref-' . uniqid()]);

        $vente = Vente::create(array_merge([
            'point_de_vente_id' => $this->pointDeVente->id,
            'numero_facture'    => 'FV-' . uniqid(),
            'date_vente'        => now()->toDateString(),
            'mode_paiement'     => 'Caisse',
            'montant_ht'        => 20000,
            'montant_tva'       => 3600,
            'montant_ttc'       => 23600,
            'etape'             => 'Facture',
        ], $attributs));

        VenteDetail::create([
            'vente_id'      => $vente->id,
            'produit_id'    => $produit->id,
            'quantite'      => 1,
            'unite'         => 'pcs',
            'prix_unitaire' => 20000,
            'montant_tva'   => 3600,
            'montant_ttc'   => 23600,
        ]);

        return $vente->fresh();
    }
}
