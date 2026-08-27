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
 * Archiver un article, c'est dire « je ne le vends plus ».
 *
 * Le mot n'avait pourtant d'effet que sur un seul écran — le catalogue, qui
 * range les archivés dans un second onglet. Partout ailleurs, l'article
 * continuait de se proposer comme avant :
 *
 *  - la caisse l'affichait en carte, prix compris, et on pouvait le vendre ;
 *  - le formulaire d'achat le proposait au réapprovisionnement ;
 *  - la fiche technique de production l'acceptait en ingrédient ;
 *  - l'écran des consignations le comptait parmi les emballages ;
 *  - le tableau de bord le portait en rupture de stock, poussant à commander
 *    ce qu'on avait décidé de ne plus vendre ;
 *  - l'ouverture d'un point de vente lui créait une fiche de stock neuve.
 *
 * Rien ne le signalait : l'utilisateur rangeait l'article et le retrouvait sur
 * une facture.
 *
 * Deux écrans font exception, et c'est délibéré. Le stock montre encore
 * l'article archivé **qui porte une quantité** — le taire ferait tomber la
 * valeur de l'inventaire alors que la marchandise est là et que les écritures
 * la portent. Et la modification d'une pièce garde l'article qu'elle porte
 * déjà, sans quoi la ligne disparaîtrait du formulaire, donc de la pièce.
 */
class ArticleArchiveTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private Utilisateur $admin;
    private PointDeVente $site;
    private Produit $bougie;
    private Produit $clavier;

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
            'secteur_activite'  => ['Commerce'],
            'modules_actifs'    => ['principal', 'ventes', 'achats', 'stock', 'production', 'produits', 'tiers', 'points_de_vente'],
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

        // L'article que le propriétaire a rangé, et un autre qui reste en vente :
        // sans le second, une liste vide passerait pour un succès.
        $this->bougie = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'DIV-BOUGIE',
            'nom' => 'Bougie', 'type' => 'marchandise', 'unite' => 'pièce',
            'prix_achat' => 1200, 'prix_vente' => 2000, 'taux_tva' => 18,
            'statut' => 'archive',
        ]);

        $this->clavier = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'INFO-CLAV',
            'nom' => 'Clavier', 'type' => 'marchandise', 'unite' => 'pièce',
            'prix_achat' => 30000, 'prix_vente' => 50000, 'taux_tva' => 18,
            'statut' => 'actif',
        ]);

        Client::create(['entreprise_id' => $this->entreprise->id, 'nom' => 'Konan Yao']);

        $this->actingAs($this->admin)->withSession(['point_de_vente_actif_id' => $this->site->id]);
    }

    // ── La règle, à sa source ────────────────────────────────────────

    public function test_le_filtre_ecarte_l_article_archive(): void
    {
        $retenus = Produit::where('entreprise_id', $this->entreprise->id)
            ->selectionnables()->pluck('reference')->all();

        $this->assertSame(['INFO-CLAV'], $retenus);
    }

    public function test_l_article_archive_sans_stock_quitte_les_ecrans_de_stock(): void
    {
        $visibles = Produit::where('entreprise_id', $this->entreprise->id)
            ->visiblesEnStock()->pluck('reference')->all();

        $this->assertSame(['INFO-CLAV'], $visibles);
    }

    public function test_l_article_archive_qui_porte_une_quantite_reste_au_stock(): void
    {
        // La marchandise est là. La cacher ferait tomber la valeur de
        // l'inventaire sans que la comptabilité bouge.
        Stock::create([
            'produit_id'        => $this->bougie->id,
            'point_de_vente_id' => $this->site->id,
            'quantite_disponible' => 7,
        ]);

        $visibles = Produit::where('entreprise_id', $this->entreprise->id)
            ->visiblesEnStock()->pluck('reference')->sort()->values()->all();

        $this->assertSame(['DIV-BOUGIE', 'INFO-CLAV'], $visibles);
    }

    // ── Les écrans ───────────────────────────────────────────────────

    public function test_la_caisse_ne_propose_plus_l_article_archive(): void
    {
        $reponse = $this->get(route('admin.ventes.nouvelle'));
        $reponse->assertOk();

        $produits = $reponse->viewData('produits')->pluck('reference')->all();

        $this->assertNotContains('DIV-BOUGIE', $produits);
        $this->assertContains('INFO-CLAV', $produits);
    }

    public function test_l_achat_ne_propose_plus_l_article_archive(): void
    {
        $reponse = $this->get(route('admin.achats.nouveau'));
        $reponse->assertOk();

        $produits = $reponse->viewData('produits')->pluck('reference')->all();

        $this->assertNotContains('DIV-BOUGIE', $produits);
        $this->assertContains('INFO-CLAV', $produits);
    }

    public function test_l_article_archive_ne_figure_plus_aux_alertes_de_rupture(): void
    {
        // À zéro sous son seuil : sans le filtre, il serait porté en rupture et
        // l'écran pousserait à recommander ce qu'on ne vend plus.
        Stock::create([
            'produit_id'        => $this->bougie->id,
            'point_de_vente_id' => $this->site->id,
            'quantite_disponible' => 0,
            'stock_minimum'     => 5,
        ]);

        $reponse = $this->get(route('admin.tableau_de_bord'));
        $reponse->assertOk();

        $alertes = collect($reponse->viewData('produitsEnAlerte'))->pluck('reference')->all();

        $this->assertNotContains('DIV-BOUGIE', $alertes);
    }

    public function test_un_nouveau_point_de_vente_n_ouvre_pas_de_fiche_pour_un_archive(): void
    {
        $this->post(route('admin.pdv.creer'), [
            'nom'     => 'Agence Yopougon',
            'ville'   => 'Abidjan',
            'commune' => 'Yopougon',
        ]);

        $nouveau = PointDeVente::where('nom', 'Agence Yopougon')->first();
        $this->assertNotNull($nouveau);

        $this->assertDatabaseMissing('stocks', [
            'produit_id'        => $this->bougie->id,
            'point_de_vente_id' => $nouveau->id,
        ]);
        $this->assertDatabaseHas('stocks', [
            'produit_id'        => $this->clavier->id,
            'point_de_vente_id' => $nouveau->id,
        ]);
    }

    // ── Cloisonnement ────────────────────────────────────────────────

    public function test_l_article_archive_d_une_autre_entreprise_n_apparait_pas_davantage(): void
    {
        $autre = Entreprise::create([
            'nom' => 'Voisine SARL', 'regime_imposition' => 'RSI',
            'modules_actifs' => ['principal', 'ventes'],
        ]);

        Produit::create([
            'entreprise_id' => $autre->id, 'reference' => 'AUT-001',
            'nom' => 'Article du voisin', 'type' => 'marchandise',
            'prix_achat' => 100, 'prix_vente' => 200, 'statut' => 'actif',
        ]);

        $reponse = $this->get(route('admin.ventes.nouvelle'));
        $produits = $reponse->viewData('produits')->pluck('reference')->all();

        $this->assertNotContains('AUT-001', $produits);
    }
}
