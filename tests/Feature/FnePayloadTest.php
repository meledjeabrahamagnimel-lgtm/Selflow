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
            'exoneration legale (regime TCE)'  => [0.0,  'TCE', 'TVAD'],
            'exoneration legale (regime RME)'  => [0.0,  'RME', 'TVAD'],
            // « RNE » figurait ici, alors que la DGI ne le cite dans aucune de
            // ses deux listes : le sigle designe le recu normalise, pas un
            // regime. Une exoneration y est donc conventionnelle.
            'RNE n\'ouvre pas l\'exoneration legale' => [0.0, 'RNE', 'TVAC'],
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

    // ─── Conformite au referentiel d'interfacage ─────────────────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('regimesEtCodesExoneration')]
    public function test_les_regimes_d_exoneration_legale_suivent_ceux_de_la_dgi(string $regime, string $attendu): void
    {
        // La facture certifiee libelle le code D ainsi, mot pour mot :
        // « TVA exo.lég - Pas de TVA sur HT 00,00% - D (TEE, TCE, Microentreprise) ».
        $this->assertSame($attendu, Produit::deduireCodeTva(0.0, $regime));
    }

    public static function regimesEtCodesExoneration(): array
    {
        return [
            'TEE — exoneration legale'   => ['TEE', 'TVAD'],
            'TCE — exoneration legale'   => ['TCE', 'TVAD'],
            'RME — exoneration legale'   => ['RME', 'TVAD'],
            // Assujettis : une exoneration a 0 % y est conventionnelle.
            'RNI — conventionnelle'      => ['RNI', 'TVAC'],
            'RSI — conventionnelle'      => ['RSI', 'TVAC'],
            'sans regime'                => ['', 'TVAC'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('modesDePaiementDuLexique')]
    public function test_tous_les_modes_de_paiement_du_lexique_sont_traduits(string $saisi, string $attendu): void
    {
        $this->simulerReponseFne();

        FneService::normaliserFacture($this->venteMinimale(['mode_paiement' => $saisi]));

        $this->assertSame($attendu, $this->payloadEnvoye()['paymentMethod']);
    }

    public static function modesDePaiementDuLexique(): array
    {
        // Annexe 1 du referentiel : les six valeurs admises par la plateforme.
        return [
            'especes'       => ['Caisse', 'cash'],
            'carte'         => ['Carte', 'card'],
            'cheque'        => ['Chèque', 'check'],
            'mobile money'  => ['Mobile Money', 'mobile-money'],
            'virement'      => ['Virement', 'transfer'],
            'a terme'       => ['Crédit', 'deferred'],
        ];
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

        // Le json de reference omet `clientNcc`, mais la plateforme le valide
        // desormais comme une chaine : son absence provoquait un HTTP 400
        // « clientNcc must be a string ». Un tiers non immatricule n'en a pas,
        // d'ou la chaine vide.
        $this->assertSame('', $payload['clientNcc']);

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

    public function test_un_recu_n_est_pas_transmis_a_la_fne(): void
    {
        Http::fake();

        $recu = $this->venteMinimale(['type_piece' => Vente::TYPE_RECU]);

        $resultat = FneService::normaliserFacture($recu);

        $this->assertFalse($resultat['success']);
        $this->assertArrayHasKey('rne_mapping', $resultat['errors']);
        Http::assertNothingSent();
    }

    public function test_un_taux_de_tva_hors_bareme_dgi_bloque_la_normalisation(): void
    {
        Http::fake();

        // 5 % n'existe pas au barème DGI : aucun code FNE ne le représente.
        $produit = $this->creerProduit(['reference' => 'ref-tva5', 'taux_tva' => 5]);
        $vente = $this->venteMinimale([], $produit);
        $vente->details()->update(['montant_tva' => 1000, 'montant_ttc' => 21000]);

        $resultat = FneService::normaliserFacture($vente->fresh());

        $this->assertFalse($resultat['success']);
        $this->assertArrayHasKey('taux_tva', $resultat['errors']);
        $this->assertStringContainsString('18 %, 9 % et 0 %', $resultat['message']);

        // Rien ne part : une facture certifiée à 18 % ne correspondrait pas
        // à celle établie ici.
        Http::assertNothingSent();
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('tauxDgiValides')]
    public function test_les_taux_du_bareme_dgi_laissent_passer_la_normalisation(float $taux, int $tva): void
    {
        $this->simulerReponseFne();

        $produit = $this->creerProduit(['reference' => 'ref-' . $taux, 'taux_tva' => $taux]);
        $vente = $this->venteMinimale([], $produit);
        $vente->details()->update(['montant_tva' => $tva]);

        $resultat = FneService::normaliserFacture($vente->fresh());

        $this->assertTrue($resultat['success'], $resultat['message'] ?? '');
    }

    public static function tauxDgiValides(): array
    {
        return [
            'taux normal 18 %' => [18.0, 3600],
            'taux reduit 9 %'  => [9.0, 1800],
            'exoneration 0 %'  => [0.0, 0],
        ];
    }

    // ─── Droit de timbre de quittance (article 873 du CGI) ───────────────

    #[\PHPUnit\Framework\Attributes\DataProvider('baremeArticle873')]
    public function test_le_bareme_legal_du_timbre_de_quittance(float $somme, float $attendu): void
    {
        $this->assertSame(
            $attendu,
            \App\Modules\Admin\Services\TimbreQuittanceService::montantDu($somme)
        );
    }

    public static function baremeArticle873(): array
    {
        return [
            // Tranche 0 – 5 000 : aucun droit. La borne est inclusive.
            'somme nulle'          => [0.0, 0.0],
            'borne haute exoneree' => [5_000.0, 0.0],
            // Tranche 5 001 – 100 000 : 100 F. Les deux factures certifiees
            // par la DGI le 4 aout 2026 y tombaient — 16 700 et 29 382 F —
            // et ont bien recu 100 F.
            'premier franc taxe'   => [5_001.0, 100.0],
            'facture 16 700'       => [16_700.0, 100.0],
            'facture 29 382'       => [29_382.0, 100.0],
            'borne haute 100 000'  => [100_000.0, 100.0],
            // Tranche 100 001 – 500 000 : 500 F. La facture de 270 900 F
            // relevait de cette tranche.
            'facture 270 900'      => [270_900.0, 500.0],
            'borne haute 500 000'  => [500_000.0, 500.0],
            'tranche 1 000 000'    => [750_000.0, 1_000.0],
            'tranche 5 000 000'    => [3_000_000.0, 2_000.0],
            'au-dela de 5 000 000' => [8_000_000.0, 5_000.0],
        ];
    }

    public function test_le_timbre_ne_frappe_que_les_reglements_en_especes(): void
    {
        $this->entreprise->update(['timbre_quittance' => true]);

        $especes = $this->venteMinimale(['mode_paiement' => 'Caisse', 'montant_ttc' => 23600]);
        $this->assertSame(100.0, $especes->timbre_quittance);

        // Un virement laisse sa propre trace : la quittance ne s'y applique pas.
        $virement = $this->venteMinimale(['mode_paiement' => 'Banque', 'montant_ttc' => 23600]);
        $this->assertSame(0.0, $virement->timbre_quittance);
    }

    public function test_le_montant_renvoye_par_la_plateforme_prime_sur_le_bareme(): void
    {
        $this->entreprise->update(['timbre_quittance' => true]);

        // Barème : 500 F pour cette tranche. Mais la DGI a retenu 0 — l'option
        // était décochée sur la plateforme au moment de la certification.
        $vente = $this->venteMinimale([
            'mode_paiement'     => 'Caisse',
            'montant_ttc'       => 270_900,
            'fne_timbre_fiscal' => 0,
        ]);

        $this->assertSame(0.0, $vente->timbre_quittance);
        $this->assertSame(270_900.0, $vente->net_a_payer);
    }

    public function test_un_avoir_ne_porte_jamais_de_timbre(): void
    {
        $this->entreprise->update(['timbre_quittance' => true]);

        // Le timbre frappe la quittance, l'acte qui constate un encaissement ;
        // un avoir constate l'inverse. La plateforme le confirme : la réponse
        // de remboursement ne comporte aucun champ `fiscalStamp`, là où celles
        // de vente et de bordereau d'achat en ont un.
        $avoir = $this->venteMinimale([
            'mode_paiement' => 'Caisse',
            'montant_ttc'   => 270_900,
            'type_facture'  => 'avoir',
        ]);

        $this->assertSame(0.0, $avoir->timbre_quittance);
        $this->assertSame(270_900.0, $avoir->net_a_payer);
    }

    public function test_le_timbre_entre_dans_le_net_a_payer(): void
    {
        $this->entreprise->update(['timbre_quittance' => true]);

        $vente = $this->venteMinimale(['mode_paiement' => 'Caisse', 'montant_ttc' => 16_700]);

        $this->assertSame(16_800.0, $vente->net_a_payer);
    }

    public function test_sans_option_declaree_aucun_timbre_n_est_annonce(): void
    {
        // La case reflete le reglage de la plateforme FNE : sans elle, annoncer
        // un timbre reviendrait a reclamer au client une somme que la DGI ne
        // retiendra pas.
        $this->entreprise->update(['timbre_quittance' => false]);

        $vente = $this->venteMinimale(['mode_paiement' => 'Caisse', 'montant_ttc' => 16_700]);

        $this->assertSame(0.0, $vente->timbre_quittance);
    }

    // ─── Bordereau d'achat (BAPA) ────────────────────────────────────────

    public function test_le_payload_bapa_porte_un_ncc_client_meme_vide(): void
    {
        $this->simulerReponseFne();

        $achat = $this->achatBapaMinimal();
        FneService::normaliserAchatBapa($achat);

        $payload = $this->payloadEnvoye();

        // La plateforme valide `clientNcc` comme une chaine, meme sur un
        // bordereau ou le tiers n'est justement pas immatricule. Son absence
        // provoquait un rejet « clientNcc must be a string » (HTTP 400).
        $this->assertArrayHasKey('clientNcc', $payload);
        $this->assertIsString($payload['clientNcc']);
    }

    public function test_un_bordereau_d_achat_ne_transmet_aucune_taxe(): void
    {
        $this->simulerReponseFne();

        FneService::normaliserAchatBapa($this->achatBapaMinimal());

        $payload = $this->payloadEnvoye();

        // Un achat aupres d'un tiers non immatricule ne supporte pas de TVA :
        // les lignes ne portent donc aucun code de taxe.
        foreach ($payload['items'] as $ligne) {
            $this->assertArrayNotHasKey('taxes', $ligne);
        }
    }

    public function test_un_bordereau_d_achat_supporte_le_timbre_de_quittance(): void
    {
        $this->entreprise->update(['timbre_quittance' => true]);

        // Bordereau certifie le 5 aout 2026 : 13 200 F HT regles en especes,
        // 100 F de timbre retenus par la DGI — deuxieme tranche de l'article
        // 873. La plateforme applique donc bien le bareme aux bordereaux.
        $achat = $this->achatBapaMinimal();
        $achat->update(['mode_paiement' => 'Caisse', 'montant_ttc' => 13_200]);

        $this->assertSame(
            100.0,
            \App\Modules\Admin\Services\TimbreQuittanceService::pourAchat($achat->fresh())
        );
    }

    public function test_une_facture_d_achat_ordinaire_ne_porte_pas_de_timbre(): void
    {
        $this->entreprise->update(['timbre_quittance' => true]);

        // Elle n'est pas certifiee, la quittance emane du fournisseur et non
        // de nous : rien ne permettrait de verifier le montant retenu.
        $achat = $this->achatBapaMinimal();
        $achat->update(['type_facture' => 'normale', 'mode_paiement' => 'Caisse', 'montant_ttc' => 13_200]);

        $this->assertSame(
            0.0,
            \App\Modules\Admin\Services\TimbreQuittanceService::pourAchat($achat->fresh())
        );
    }

    public function test_un_bordereau_ne_transmet_ni_tva_ni_taxes_personnalisees(): void
    {
        $this->simulerReponseFne();

        FneService::normaliserAchatBapa($this->achatBapaMinimal());

        $payload = $this->payloadEnvoye();

        // Le json de reference d'un bordereau ne comporte ni `taxes` ni
        // `customTaxes`, ni sur la ligne ni a la racine : la DGI n'en retient
        // aucune. Le document ne doit donc en afficher aucune non plus.
        $this->assertArrayNotHasKey('customTaxes', $payload);
        foreach ($payload['items'] as $ligne) {
            $this->assertArrayNotHasKey('taxes', $ligne);
            $this->assertArrayNotHasKey('customTaxes', $ligne);
            $this->assertSame(
                ['reference', 'description', 'quantity', 'amount', 'discount', 'measurementUnit'],
                array_keys($ligne)
            );
        }
    }

    // ─── Alerte de stickers ──────────────────────────────────────────────

    public function test_l_alerte_stickers_se_declenche_au_seuil_et_chiffre_le_reste(): void
    {
        $this->entreprise->update([
            'fne_mode_facturation' => 'stickers',
            'sticker_solde_alerte' => 5,
            'fne_sticker_balance'  => 3,
        ]);

        $alerte = \App\Modules\Admin\Services\AlerteStickersService::pour($this->entreprise->fresh());

        $this->assertNotNull($alerte);
        $this->assertSame('bas', $alerte['niveau']);
        $this->assertSame(3, $alerte['pieces_restantes']);
        $this->assertSame(60.0, $alerte['valeur']); // 3 vignettes a 20 F
    }

    public function test_un_solde_epuise_est_signale_autrement_qu_un_solde_bas(): void
    {
        // A zero, ce n'est plus un avertissement : la plateforme refuse de
        // certifier, et les ventes cessent d'etre normalisees.
        $this->entreprise->update([
            'fne_mode_facturation' => 'stickers',
            'sticker_solde_alerte' => 5,
            'fne_sticker_balance'  => 0,
        ]);

        $alerte = \App\Modules\Admin\Services\AlerteStickersService::pour($this->entreprise->fresh());

        $this->assertSame('epuise', $alerte['niveau']);
    }

    public function test_aucune_alerte_au_dessus_du_seuil_ni_en_mode_provision(): void
    {
        $this->entreprise->update([
            'fne_mode_facturation' => 'stickers',
            'sticker_solde_alerte' => 5,
            'fne_sticker_balance'  => 40,
        ]);
        $this->assertNull(\App\Modules\Admin\Services\AlerteStickersService::pour($this->entreprise->fresh()));

        // En mode provision, la DGI decompte des francs : le nombre de
        // vignettes n'a pas de sens et l'alerte n'a rien a surveiller.
        $this->entreprise->update([
            'fne_mode_facturation' => 'provision',
            'fne_sticker_balance'  => 0,
        ]);
        $this->assertNull(\App\Modules\Admin\Services\AlerteStickersService::pour($this->entreprise->fresh()));
    }

    // ─── Code QR de vérification ─────────────────────────────────────────

    public function test_le_code_qr_encode_le_jeton_renvoye_par_la_plateforme(): void
    {
        $jeton = 'http://54.247.95.108/fr/verification/019fcdcb-1139-7009-8e13-8d6a258cbf84';

        $image = \App\Modules\Admin\Services\QrCodeFneService::imageDeVerification($jeton);

        $this->assertNotNull($image);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $image);

        $svg = base64_decode(substr($image, strlen('data:image/svg+xml;base64,')));
        $this->assertStringContainsString('<svg', $svg);

        // Marge de quatre modules : la norme ISO/IEC 18004 l'impose, et sans
        // elle beaucoup de lecteurs ne trouvent plus les motifs de repérage.
        $matrice = \App\Modules\Admin\Services\QrCodeFneService::matrice($jeton);
        preg_match('/viewBox="0 0 (\d+) \1"/', $svg, $vue);
        $this->assertSame(count($matrice) + 8, (int) $vue[1]);
    }

    public function test_aucune_image_n_est_produite_sans_jeton_de_la_plateforme(): void
    {
        // Une pièce non certifiée ne porte aucune marque : un code fabriqué
        // par nos soins ne certifierait rien.
        $this->assertNull(\App\Modules\Admin\Services\QrCodeFneService::imageDeVerification(null));
        $this->assertNull(\App\Modules\Admin\Services\QrCodeFneService::imageDeVerification(''));
        $this->assertNull(\App\Modules\Admin\Services\QrCodeFneService::imageDeVerification('   '));
    }

    // ─── Avoirs : reprise fidèle de la facture d'origine ─────────────────

    public function test_un_avoir_reprend_le_taux_de_tva_et_la_remise_de_la_ligne_creditee(): void
    {
        // Ligne exonérée (TVAD 0 %) remisée à 2 %, comme sur la facture
        // 1864699A26000000051 fournie par la DGI.
        $produit = $this->creerProduit(['reference' => 'ref-exo', 'taux_tva' => 0]);

        $facture = $this->venteMinimale(['fne_invoice_id' => 'uuid-facture'], $produit);
        $ligne = $facture->details->first();
        $ligne->update([
            'quantite'      => 1,
            'prix_unitaire' => 5000,
            'remise_taux'   => 2,
            'montant_tva'   => 0,
            'montant_ttc'   => 4900,
        ]);

        $taux = $this->invoquerMethodePrivee('tauxTvaDeLaLigne', $ligne->fresh());

        // Le calcul précédent testait `montant_ttc - montant_ht`, or la colonne
        // `montant_ht` n'existe pas : la soustraction portait sur null et toute
        // ligne repartait à 18 %, exonérations comprises.
        $this->assertSame(0.0, $taux);
    }

    public function test_le_taux_reconstitue_suit_le_taux_reellement_applique(): void
    {
        $produit = $this->creerProduit(['reference' => 'ref-neuf']);
        $facture = $this->venteMinimale([], $produit);

        $ligne = $facture->details->first();
        $ligne->update(['quantite' => 4, 'prix_unitaire' => 50000, 'remise_taux' => 0, 'montant_tva' => 36000]);
        $this->assertSame(18.0, $this->invoquerMethodePrivee('tauxTvaDeLaLigne', $ligne->fresh()));

        $ligne->update(['quantite' => 2, 'prix_unitaire' => 10000, 'remise_taux' => 0, 'montant_tva' => 1800]);
        $this->assertSame(9.0, $this->invoquerMethodePrivee('tauxTvaDeLaLigne', $ligne->fresh()));
    }

    /** Accès aux méthodes privées du contrôleur de vente, pour les tester isolément. */
    private function invoquerMethodePrivee(string $methode, ...$arguments)
    {
        $reflexion = new \ReflectionMethod(\App\Modules\Admin\Controleurs\VenteControleur::class, $methode);
        $reflexion->setAccessible(true);

        return $reflexion->invoke(null, ...$arguments);
    }

    // ─── Mode de facturation de la plateforme (stickers ou provision) ────

    public function test_le_mode_stickers_est_constate_depuis_le_solde_renvoye(): void
    {
        $this->simulerReponseFne(['balance_sticker' => 179]);

        FneService::normaliserFacture($this->venteMinimale());

        $this->entreprise->refresh();
        $this->assertSame('stickers', $this->entreprise->fne_mode_facturation);
        $this->assertSame(179, $this->entreprise->fne_sticker_balance);
        $this->assertNotNull($this->entreprise->fne_solde_maj_at);
    }

    public function test_le_mode_provision_est_constate_et_le_solde_garde_son_unite(): void
    {
        // Stickers désactivés côté DGI : la réponse ne porte plus de solde de
        // vignettes mais un solde en francs. On ne passe pas par
        // `simulerReponseFne`, qui renvoie toujours un `balance_sticker`.
        Http::fake(['*' => Http::response([
            'reference' => '9606123E25000000019',
            'token'     => 'https://fne.test/verification/019465c1',
            'balance_funds' => 45000,
            'invoice'   => ['id' => 'e2b2d8da-a532-4c08-9182-f5b428ca468d', 'items' => []],
        ], 200)]);

        FneService::normaliserFacture($this->venteMinimale());

        $this->entreprise->refresh();
        $this->assertSame('provision', $this->entreprise->fne_mode_facturation);
        $this->assertEquals(45000, $this->entreprise->fne_solde_provision);

        // Le solde en francs ne se convertit pas en nombre de vignettes : le
        // prix unitaire n'est transmis nulle part. Le code divisait auparavant
        // par 20 et rangeait le resultat dans le compteur de stickers, qui
        // affichait donc 2 250 vignettes imaginaires.
        $this->assertNotEquals(2250, $this->entreprise->fne_sticker_balance);
        $this->assertEmpty($this->entreprise->fne_sticker_balance);
    }

    public function test_le_mode_reste_inconnu_tant_qu_aucune_piece_n_est_normalisee(): void
    {
        $this->assertNull($this->entreprise->fne_mode_facturation);
    }

    public function test_les_ventes_validees_portent_une_etape_facture_et_jamais_le_statut_facturee(): void
    {
        // Les tableaux de bord filtraient sur un statut « Facturée » qui
        // n'existe pas : les compteurs et graphiques restaient donc a zero.
        $vente = $this->venteMinimale(['statut' => 'Payé']);

        $this->assertSame('Facture', $vente->etape);
        $this->assertNotSame('Facturée', $vente->statut);
        $this->assertSame(
            1,
            Vente::withoutGlobalScopes()->where('etape', 'Facture')->where('id', $vente->id)->count()
        );
        $this->assertSame(
            0,
            Vente::withoutGlobalScopes()->where('statut', 'Facturée')->count()
        );
    }

    public function test_un_recu_remplace_par_sa_facture_sort_des_agregats(): void
    {
        // Sans cette regle, le recu et la facture qui en decoule porteraient
        // les memes montants et doubleraient le chiffre d'affaires.
        $recu    = $this->venteMinimale(['type_piece' => Vente::TYPE_RECU]);
        $facture = $this->venteMinimale(['piece_liee_id' => $recu->id]);
        $recu->update(['piece_liee_id' => $facture->id]);

        $retenues = Vente::withoutGlobalScopes()->sansDoublonRecu()->pluck('id');

        $this->assertContains($facture->id, $retenues);
        $this->assertNotContains($recu->id, $retenues);
        $this->assertTrue($recu->fresh()->estRemplaceParUneFacture());
    }

    public function test_un_recu_seul_reste_compte(): void
    {
        $recu = $this->venteMinimale(['type_piece' => Vente::TYPE_RECU]);

        $this->assertContains(
            $recu->id,
            Vente::withoutGlobalScopes()->sansDoublonRecu()->pluck('id')
        );
    }

    /**
     * Vente d'une ligne, sans client ni remise : le socle des cas ci-dessus.
     */
    /** Bordereau d'achat minimal aupres d'un tiers non immatricule. */
    private function achatBapaMinimal(): Achat
    {
        $fournisseur = Fournisseur::create([
            'entreprise_id' => $this->entreprise->id,
            'nom'           => 'TIERS NON IMMATRICULE',
            'telephone'     => '0709080765',
            'email'         => 'tiers@exemple.ci',
        ]);

        $achat = Achat::create([
            'point_de_vente_id' => $this->pointDeVente->id,
            'fournisseur_id'    => $fournisseur->id,
            'numero_facture'    => 'BAPA-' . uniqid(),
            'date_achat'        => now()->toDateString(),
            'mode_paiement'     => 'Caisse',
            'montant_ht'        => 12000,
            'montant_tva'       => 0,
            'montant_ttc'       => 12000,
            'type_facture'      => 'bapa',
            'etape'             => 'Facture',
        ]);

        AchatDetail::create([
            'achat_id'      => $achat->id,
            'produit_id'    => $this->creerProduit(['reference' => 'ref-' . uniqid()])->id,
            'quantite'      => 1,
            'unite'         => 'sac',
            'prix_unitaire' => 12000,
            'montant_tva'   => 0,
            'montant_ttc'   => 12000,
        ]);

        return $achat->fresh();
    }

    public function test_une_quantite_fractionnee_part_telle_quelle_a_la_dgi(): void
    {
        // `intval` etait applique aux six points ou une quantite part a la DGI.
        // Depuis que les lignes portent des decimales, une vente de 12,5 kg de
        // cacao partait certifiee pour 12 kg : la piece etablie dans Selflow et
        // la piece certifiee divergeaient, sans que rien ne le signale.
        //
        // Le referentiel technique est formel — `quantity | number | Quantite | O`,
        // dans les trois tableaux de champs : certification (l. 272), recu
        // normalise (l. 508) et avoir (l. 684). Un `number` JSON n'est pas un
        // entier.
        $this->simulerReponseFne();

        $vente = $this->venteMinimale();
        // La TVA suit la quantite : le garde-fou des taux DGI refuse — a juste
        // titre — une ligne dont le taux reconstitue ne vaut aucun code FNE.
        $vente->details()->first()->update(['quantite' => 12.5, 'montant_tva' => 45000]);

        FneService::normaliserFacture($vente->fresh());

        $this->assertSame(12.5, $this->payloadEnvoye()['items'][0]['quantity']);
    }

    public function test_une_quantite_entiere_part_sans_decimale(): void
    {
        // La forme sur le fil ne change pas pour ce qui passait deja : les
        // exemples du referentiel montrent des entiers nus, et modifier la
        // forme d'un champ accepte serait un risque gratuit.
        $this->simulerReponseFne();

        $vente = $this->venteMinimale();
        // La TVA suit la quantite : le garde-fou des taux DGI refuse — a juste
        // titre — une ligne dont le taux reconstitue ne vaut aucun code FNE.
        $vente->details()->first()->update(['quantite' => 30, 'montant_tva' => 108000]);

        FneService::normaliserFacture($vente->fresh());

        $corps = json_encode($this->payloadEnvoye());

        $this->assertStringContainsString('"quantity":30,', $corps);
        $this->assertStringNotContainsString('"quantity":30.0', $corps);
    }

    public function test_la_quantite_transmise_s_aligne_sur_la_precision_de_la_base(): void
    {
        // Transmettre plus de decimales que la colonne `decimal(15,3)` n'en
        // garde rendrait la piece certifiee irreproductible depuis nos donnees.
        $this->simulerReponseFne();

        $vente = $this->venteMinimale();
        // La TVA suit la quantite : le garde-fou des taux DGI refuse — a juste
        // titre — une ligne dont le taux reconstitue ne vaut aucun code FNE.
        $vente->details()->first()->update(['quantite' => 12.5555, 'montant_tva' => 45199.8]);

        FneService::normaliserFacture($vente->fresh());

        $this->assertSame(12.556, $this->payloadEnvoye()['items'][0]['quantity']);
    }

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
