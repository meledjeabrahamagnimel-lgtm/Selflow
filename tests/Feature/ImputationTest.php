<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Categorie;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Services\ImputationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sur quel compte s'impute un article.
 *
 * La question se résolvait par une paire — le compte de l'article, ou le
 * défaut de configuration — et le niveau le plus utile, **le rayon**, sautait.
 * Un article créé à la main après la souscription n'héritait donc de rien et
 * tombait sur `701000` : la balance d'un magasin qui a soigneusement réparti
 * ses rayons n'avait qu'une seule ligne de ventes.
 */
class ImputationTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private Categorie $boissons;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create(['nom' => 'Boutique du carrefour']);

        $this->boissons = Categorie::create([
            'entreprise_id'    => $this->entreprise->id,
            'nom'              => 'Boissons fraîches',
            'prefixe'          => 'BOI',
            'compte_vente'     => '701200',
            'compte_achat'     => '601200',
            'compte_stock'     => '311200',
            'compte_variation' => '603120',
        ]);
    }

    private function article(array $attributs = []): Produit
    {
        return Produit::create(array_merge([
            'entreprise_id' => $this->entreprise->id,
            'reference'     => 'ART-' . uniqid(),
            'nom'           => 'Sucrerie 33 cl',
            'type'          => 'marchandise',
            'prix_achat'    => 300,
            'prix_vente'    => 500,
            'categorie_id'  => $this->boissons->id,
        ], $attributs));
    }

    // ── Le rang 2 : le rayon ─────────────────────────────────────────

    public function test_un_article_sans_compte_herite_de_son_rayon(): void
    {
        // C'est le cas qui manquait : un article cree a la main apres la
        // souscription tombait sur le compte generique.
        $article = $this->article();

        $this->assertSame('701200', ImputationService::compteVente($article));
        $this->assertSame('601200', ImputationService::compteAchat($article));
        $this->assertSame('311200', ImputationService::compteStock($article));
        $this->assertSame('603120', ImputationService::compteVariation($article));
    }

    public function test_changer_le_compte_d_un_rayon_change_celui_de_ses_articles(): void
    {
        // C'est tout l'interet de porter l'imputation sur le rayon : un geste,
        // et non une fiche a rouvrir par article.
        $article = $this->article();

        $this->boissons->update(['compte_vente' => '701900']);

        $this->assertSame('701900', ImputationService::compteVente($article->fresh()));
    }

    // ── Le rang 1 : l'article ────────────────────────────────────────

    public function test_le_compte_de_l_article_prime_sur_celui_du_rayon(): void
    {
        // C'est l'exception que l'utilisateur assume : il l'a saisie expres.
        $article = $this->article(['compte_vente' => '701700']);

        $this->assertSame('701700', ImputationService::compteVente($article));
        $this->assertSame('601200', ImputationService::compteAchat($article), 'Les autres comptes suivent le rayon.');
    }

    public function test_un_compte_rempli_d_espaces_ne_vaut_pas_imputation(): void
    {
        // Un import maladroit remplit une colonne d'espaces : la traiter comme
        // un compte imputerait la vente sur un numero vide.
        $article = $this->article(['compte_vente' => '   ']);

        $this->assertSame('701200', ImputationService::compteVente($article));
    }

    // ── Le rang 3 : le filet ─────────────────────────────────────────

    public function test_un_article_sans_rayon_tombe_sur_le_defaut(): void
    {
        $article = $this->article(['categorie_id' => null]);

        $this->assertSame(
            config('selflow.plan_comptable_defaut.vente_defaut'),
            ImputationService::compteVente($article)
        );
    }

    public function test_le_stock_n_a_pas_de_filet(): void
    {
        // Il n'existe pas de « compte de stock generique » qui voudrait dire
        // quelque chose : les marchandises vont en 31, les matieres en 32, les
        // produits finis en 36. Les confondre rendrait le bilan faux plutot
        // qu'imprecis.
        $article = $this->article(['categorie_id' => null]);

        $this->assertNull(ImputationService::compteStock($article));
        $this->assertNull(ImputationService::compteVariation($article));
    }

    // ── L'inventaire permanent ───────────────────────────────────────

    public function test_un_article_complet_peut_tenir_l_inventaire_permanent(): void
    {
        $this->assertTrue(ImputationService::peutTenirLInventairePermanent($this->article()));
    }

    public function test_il_faut_les_deux_comptes_et_pas_un_seul(): void
    {
        // Le stock sans la variation ecrirait une entree de bilan sans
        // contrepartie de gestion, et le desequilibre n'apparaitrait qu'a la
        // balance, des semaines plus tard.
        $this->boissons->update(['compte_variation' => null]);

        $this->assertFalse(ImputationService::peutTenirLInventairePermanent($this->article()->fresh()));
    }

    public function test_un_service_ne_tient_pas_d_inventaire(): void
    {
        $mission = $this->article(['type' => 'service']);

        $this->assertFalse(ImputationService::peutTenirLInventairePermanent($mission));
    }

    // ── Ce que les écrans doivent pouvoir dire ───────────────────────

    public function test_les_comptes_manquants_se_nomment(): void
    {
        // Un article mal impute ne se voit pas avant la balance, et a ce
        // moment-la le mois est passe.
        $orphelin = $this->article(['categorie_id' => null]);

        $manquants = ImputationService::manqueUnCompte($orphelin);

        $this->assertContains('compte de vente', $manquants);
        $this->assertContains('compte de stock', $manquants);
        $this->assertContains('compte de variation de stock', $manquants);
    }

    public function test_un_service_orphelin_ne_reclame_pas_de_compte_de_stock(): void
    {
        $mission = $this->article(['type' => 'service', 'categorie_id' => null]);

        $this->assertNotContains('compte de stock', ImputationService::manqueUnCompte($mission));
    }

    public function test_un_article_complet_ne_manque_de_rien(): void
    {
        $this->assertSame([], ImputationService::manqueUnCompte($this->article()));
    }
}
