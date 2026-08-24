<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Stock;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Un service n'a pas de stock : il ne peut donc pas être en rupture.
 *
 * L'application le savait déjà là où cela comptait — `estStockable()` existe
 * depuis longtemps, et aucun contrôleur ne décrémente le stock d'une
 * prestation. Mais **aucun écran ne le lisait** :
 *
 *  - l'écran de vente affichait « Rupture de stock » en rouge sous chaque
 *    prestation, et ouvrait une alerte de rupture à chaque ligne ajoutée puis
 *    à chaque incrément de quantité. Un cabinet, qui ne vend que des services,
 *    voyait son catalogue entier en rouge et une modale par article ;
 *  - l'écran de stock listait tout le catalogue, prestations comprises, et son
 *    compteur d'alertes les comptait toutes en rupture ;
 *  - la liste des articles portait sa propre copie, écrite en dur, de la liste
 *    des types sans stock.
 *
 * Rien n'était jamais bloqué — le serveur écartait déjà ces articles — mais
 * rien ne le disait, et l'écran affirmait le contraire.
 */
class ArticlesSansStockTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private Utilisateur $admin;
    private PointDeVente $site;
    private Produit $riz;
    private Produit $conseil;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create([
            'nom'               => 'DC-Knowing CGA',
            'regime_imposition' => 'RNI',
            'adresse'           => 'Cocody, Abidjan',
            'rccm'              => 'CI-ABJ-2026-B-00042',
            'ncc'               => '2601234A',
            'gerant_fonction'   => 'Gérant',
            'secteur_activite'  => ['Services'],
            'modules_actifs'    => ['principal', 'ventes', 'achats', 'stock', 'produits', 'tiers'],
        ]);

        $this->site = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Siège', 'ville' => 'Abidjan', 'commune' => 'Cocody',
        ]);

        $this->admin = Utilisateur::create([
            'nom' => 'Kouadio', 'prenom' => 'Lewis', 'email' => 'lewis@dc-knowing.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $this->entreprise->id,
            'point_de_vente_id' => $this->site->id,
        ]);

        // Une marchandise réellement en rupture, et une prestation qui n'a
        // jamais eu de stock. Les deux doivent se lire différemment.
        $this->riz = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'RIZ-25',
            'nom' => 'Riz parfumé 25 kg', 'type' => 'marchandise', 'unite' => 'sac',
            'prix_achat' => 12000, 'prix_vente' => 20000, 'taux_tva' => 18,
        ]);

        $this->conseil = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'CONS-H',
            'nom' => 'Déclarations fiscales et sociales', 'type' => 'service', 'unite' => 'dossier',
            'prix_achat' => 0, 'prix_vente' => 5555, 'taux_tva' => 18,
        ]);

        Client::create(['entreprise_id' => $this->entreprise->id, 'nom' => 'Konan Yao']);

        $this->actingAs($this->admin)->withSession(['point_de_vente_actif_id' => $this->site->id]);
    }

    // ── La règle, à sa source ────────────────────────────────────────

    public function test_l_etat_de_stock_d_un_service_n_est_pas_une_rupture(): void
    {
        $this->assertSame(Produit::ETAT_SANS_STOCK, $this->conseil->etatStock());
        $this->assertSame('Rupture', $this->riz->etatStock());
    }

    public function test_le_filtre_stockables_ecarte_les_services(): void
    {
        $stockables = Produit::where('entreprise_id', $this->entreprise->id)
            ->stockables()->pluck('reference')->all();

        $this->assertSame(['RIZ-25'], $stockables);
    }

    public function test_un_consommable_non_stockable_est_traite_comme_un_service(): void
    {
        $eau = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'EAU-BUR',
            'nom' => 'Eau du bureau', 'type' => 'consommable_non_stockable',
            'prix_achat' => 500, 'prix_vente' => 0,
        ]);

        $this->assertSame(Produit::ETAT_SANS_STOCK, $eau->etatStock());
        $this->assertFalse($eau->est_stockable);
    }

    // ── L'écran de vente ─────────────────────────────────────────────

    public function test_l_ecran_de_vente_n_annonce_pas_de_rupture_sous_une_prestation(): void
    {
        $html = $this->get(route('admin.ventes.nouvelle'))->assertOk()->getContent();

        // La carte de la prestation ne porte ni la mention, ni la classe qui la
        // grise, ni le drapeau qui déclenche la modale.
        $carte = $this->carteDe($html, 'Déclarations fiscales et sociales');

        $this->assertStringNotContainsString('Rupture de stock', $carte);
        $this->assertStringNotContainsString('out-of-stock', $carte);
        $this->assertStringContainsString('data-stockable="0"', $carte);
        $this->assertStringContainsString('Service', $carte);
    }

    public function test_l_ecran_de_vente_annonce_toujours_la_rupture_d_une_marchandise(): void
    {
        $html  = $this->get(route('admin.ventes.nouvelle'))->assertOk()->getContent();
        $carte = $this->carteDe($html, 'Riz parfumé 25 kg');

        $this->assertStringContainsString('Rupture de stock', $carte);
        $this->assertStringContainsString('out-of-stock', $carte);
        $this->assertStringContainsString('data-stockable="1"', $carte);
    }

    public function test_l_ecran_de_modification_suit_la_meme_regle(): void
    {
        $vente = $this->venteDUneLigne();

        $html  = $this->get(route('admin.ventes.modifier', $vente))->assertOk()->getContent();
        $carte = $this->carteDe($html, 'Déclarations fiscales et sociales');

        $this->assertStringNotContainsString('Rupture de stock', $carte);
        $this->assertStringContainsString('data-stockable="0"', $carte);
    }

    // ── Les écrans de stock ──────────────────────────────────────────

    public function test_l_ecran_de_stock_ne_liste_pas_les_prestations(): void
    {
        $this->get(route('admin.stock.index'))
            ->assertOk()
            ->assertSee('RIZ-25')
            ->assertDontSee('CONS-H');
    }

    public function test_le_compteur_de_ruptures_ne_compte_pas_les_prestations(): void
    {
        // Une seule rupture réelle : le riz. Avec la prestation, l'écran en
        // annonçait deux.
        $this->get(route('admin.stock.index'))
            ->assertOk()
            ->assertSee('1 rupture(s)');
    }

    public function test_l_api_de_stock_ne_rend_pas_les_prestations(): void
    {
        // L'API mobile s'authentifie par jeton porteur, pas par session.
        $this->admin->forceFill(['jeton_api' => 'jeton-de-test', 'statut' => 'actif'])->save();

        $reponse = $this->getJson('/api/admin/stock', [
            'Authorization' => 'Bearer jeton-de-test',
        ])->assertOk()->json('stock');

        $noms = array_column($reponse, 'nom');

        $this->assertContains('Riz parfumé 25 kg', $noms);
        $this->assertNotContains('Déclarations fiscales et sociales', $noms);
    }

    // ── Les alertes du tableau de bord ───────────────────────────────

    public function test_une_prestation_dotee_d_une_fiche_de_stock_ne_remonte_pas_en_alerte(): void
    {
        // Une fiche de stock peut exister pour un article qui n'en gère pas :
        // plusieurs écrans en posent une pour tout le monde. Sans le filtre,
        // la prestation entrait dans les alertes de rupture.
        Stock::create([
            'produit_id'          => $this->conseil->id,
            'point_de_vente_id'   => $this->site->id,
            'quantite_disponible' => 0,
            'stock_minimum'       => 5,
            'stock_maximum'       => 100,
        ]);

        $this->get(route('admin.tableau_de_bord'))
            ->assertOk()
            ->assertDontSee('Déclarations fiscales et sociales');
    }

    // ── La liste des articles ────────────────────────────────────────

    public function test_la_liste_des_articles_lit_la_regle_du_modele(): void
    {
        // La vue portait sa propre copie, en dur, des types sans stock — à
        // trois endroits : une seconde source de vérité, qui aurait divergé au
        // premier type ajouté. Seules les couleurs de badge citent encore les
        // types nommément, et c'est légitime : ce sont des apparences.
        $vue = file_get_contents(base_path('app/Modules/Admin/Vues/produits/index.blade.php'));

        $this->assertStringNotContainsString(
            "in_array(\$p->type, ['service', 'consommable_non_stockable'])",
            $vue
        );
        $this->assertSame(3, substr_count($vue, '!$p->estStockable()'));
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * Le fragment HTML de la carte d'un produit donné, depuis l'ouverture de sa
     * div jusqu'à la fermeture du bloc de stock. Assertion sur la page entière,
     * une prestation « propre » passerait pour un riz en rupture.
     */
    private function carteDe(string $html, string $nomProduit): string
    {
        $position = strpos($html, 'data-nom="' . e($nomProduit) . '"');
        $this->assertNotFalse($position, "Carte introuvable pour « {$nomProduit} »");

        $debut = strrpos(substr($html, 0, $position), '<div class="produit-card');
        $this->assertNotFalse($debut);

        $fin = strpos($html, '</div>', strpos($html, 'produit-stock', $debut));

        return substr($html, $debut, $fin - $debut);
    }

    private function venteDUneLigne()
    {
        $this->post(route('admin.ventes.enregistrer'), [
            'client_id'     => Client::firstOrFail()->id,
            'date_vente'    => now()->toDateString(),
            'mode_paiement' => 'Espèces',
            'etape'         => 'Facture',
            'articles'      => [[
                'produit_id' => $this->conseil->id, 'quantite' => 1,
                'prix_unitaire' => 5555, 'unite' => 'dossier',
            ]],
        ]);

        return \App\Modules\Admin\Modeles\Vente::latest('id')->firstOrFail();
    }
}
