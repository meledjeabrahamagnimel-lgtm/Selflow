<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Stock;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Un attaquant authentifié, aux prises avec les écrans de stock.
 *
 * Le scénario n'est pas celui d'un inconnu : c'est un utilisateur légitime
 * d'une entreprise A, qui forge des requêtes pour atteindre l'entreprise B, ou
 * pour faire écrire à Selflow des choses qu'aucun formulaire ne propose. C'est
 * la surface réelle — un formulaire ne protège rien, il ne fait que suggérer.
 *
 * Chaque test porte le nom de ce qu'un attaquant tenterait, et vérifie que la
 * tentative échoue **sans effet de bord** : refuser en écrivant quand même la
 * moitié de l'opération serait pire que tout.
 */
class AttaquesStockTest extends TestCase
{
    use RefreshDatabase;

    private Utilisateur $attaquant;
    private PointDeVente $sonSite;
    private Produit $sonArticle;

    private Entreprise $victime;
    private PointDeVente $siteVictime;
    private Produit $articleVictime;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        [$this->attaquant, $this->sonSite, $this->sonArticle] = $this->uneEntreprise('Quincaillerie du plateau', 'pirate@exemple.ci');
        [, $this->siteVictime, $this->articleVictime] = $this->uneEntreprise('Coopérative du Bandama', 'victime@exemple.ci');

        $this->victime = $this->siteVictime->entreprise;

        Stock::create([
            'produit_id' => $this->articleVictime->id,
            'point_de_vente_id' => $this->siteVictime->id,
            'quantite_disponible' => 500,
        ]);

        Stock::create([
            'produit_id' => $this->sonArticle->id,
            'point_de_vente_id' => $this->sonSite->id,
            'quantite_disponible' => 10,
        ]);

        $this->actingAs($this->attaquant)
            ->withSession(['point_de_vente_actif_id' => $this->sonSite->id]);
    }

    /** @return array{0: Utilisateur, 1: PointDeVente, 2: Produit} */
    private function uneEntreprise(string $nom, string $email): array
    {
        $entreprise = Entreprise::create([
            'nom' => $nom,
            'regime_imposition' => 'RNI', 'adresse' => 'Abidjan',
            'rccm' => 'CI-ABJ-' . uniqid(), 'ncc' => strtoupper(substr(md5($nom), 0, 8)),
            'gerant_fonction' => 'Gérant', 'secteur_activite' => ['Commerce'],
            'modules_actifs' => ['principal', 'ventes', 'achats', 'stock', 'produits', 'tiers'],
        ]);

        $site = PointDeVente::create([
            'entreprise_id' => $entreprise->id,
            'nom' => 'Site ' . $nom, 'ville' => 'Abidjan', 'commune' => 'Cocody',
        ]);

        $utilisateur = Utilisateur::create([
            'nom' => 'X', 'prenom' => 'Y', 'email' => $email,
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $entreprise->id, 'point_de_vente_id' => $site->id,
        ]);

        $produit = Produit::create([
            'entreprise_id' => $entreprise->id, 'reference' => 'ART-' . uniqid(),
            'nom' => 'Ciment', 'type' => 'marchandise', 'unite' => 'sac',
            'prix_achat' => 5000, 'prix_vente' => 6000,
        ]);

        return [$utilisateur, $site->fresh(), $produit];
    }

    private function stockVictime(): float
    {
        return $this->articleVictime->fresh()->stockActuel($this->siteVictime->id);
    }

    // ── Écrire dans le stock du voisin ───────────────────────────────

    public function test_l_inventaire_refuse_un_site_d_une_autre_entreprise(): void
    {
        // Le champ `point_de_vente_id` est cache dans le formulaire : rien
        // n'empeche de le remplacer par l'identifiant du depot du voisin.
        $this->post(route('admin.stock.inventaire.enregistrer'), [
            'point_de_vente_id' => $this->siteVictime->id,
            'comptages'         => [$this->articleVictime->id => 0],
        ])->assertSessionHasErrors('point_de_vente_id');

        $this->assertSame(500.0, $this->stockVictime());
    }

    public function test_l_inventaire_ignore_un_article_d_une_autre_entreprise(): void
    {
        // Site legitime, mais article vole : le couple est verifie, pas
        // seulement chacun de ses membres.
        $this->post(route('admin.stock.inventaire.enregistrer'), [
            'point_de_vente_id' => $this->sonSite->id,
            'comptages'         => [$this->articleVictime->id => 0],
        ]);

        $this->assertSame(500.0, $this->stockVictime());
        $this->assertSame(0, MouvementStock::where('produit_id', $this->articleVictime->id)->count());
    }

    public function test_l_inventaire_refuse_une_lecture_du_stock_voisin(): void
    {
        // `?point_de_vente_id=` dans l'URL : la page afficherait sinon
        // l'inventaire complet du concurrent, references et quantites.
        $this->get(route('admin.stock.inventaire', ['point_de_vente_id' => $this->siteVictime->id]))
            ->assertOk()
            ->assertDontSee($this->articleVictime->reference)
            ->assertSee('Choisissez le site où le comptage a lieu');
    }

    public function test_le_rebut_refuse_un_article_d_une_autre_entreprise(): void
    {
        $this->post(route('admin.stock.rebut.retirer'), [
            'produit_id' => $this->articleVictime->id,
            'quantite'   => 100,
        ])->assertSessionHasErrors('produit_id');

        $this->assertSame(500.0, $this->stockVictime());
    }

    // ── Faire écrire des valeurs impossibles ─────────────────────────

    public function test_une_quantite_negative_ne_gonfle_pas_le_stock(): void
    {
        // Un rebut de -100 serait une entree deguisee : elle echapperait aux
        // filtres de l'ecran des mouvements, qui classent par type.
        $this->post(route('admin.stock.rebut.retirer'), [
            'produit_id' => $this->sonArticle->id,
            'quantite'   => -100,
        ])->assertSessionHasErrors('quantite');

        $this->assertSame(10.0, $this->sonArticle->fresh()->stockActuel($this->sonSite->id));
    }

    public function test_une_quantite_demesuree_est_refusee_avant_la_base(): void
    {
        // Sans plafond, la colonne decimal(15,3) rendrait une erreur SQL — donc
        // une page 500, et un message d'erreur de base de donnees a l'ecran.
        $this->post(route('admin.stock.rebut.retirer'), [
            'produit_id' => $this->sonArticle->id,
            'quantite'   => '9999999999999999',
        ])->assertSessionHasErrors('quantite');
    }

    public function test_une_quantite_non_numerique_est_refusee(): void
    {
        $this->post(route('admin.stock.rebut.retirer'), [
            'produit_id' => $this->sonArticle->id,
            'quantite'   => "1' OR '1'='1",
        ])->assertSessionHasErrors('quantite');

        $this->assertSame(10.0, $this->sonArticle->fresh()->stockActuel($this->sonSite->id));
    }

    public function test_un_comptage_negatif_est_refuse(): void
    {
        $this->post(route('admin.stock.inventaire.enregistrer'), [
            'point_de_vente_id' => $this->sonSite->id,
            'comptages'         => [$this->sonArticle->id => -50],
        ])->assertSessionHasErrors('comptages.' . $this->sonArticle->id);

        $this->assertSame(10.0, $this->sonArticle->fresh()->stockActuel($this->sonSite->id));
    }

    // ── Réécrire l'histoire ──────────────────────────────────────────

    public function test_le_journal_resiste_a_une_suppression_directe(): void
    {
        // Le contournement le plus simple : passer par le modele plutot que par
        // le service. C'est exactement ce que faisait VenteControleur.
        $mouvement = MouvementStock::create([
            'produit_id' => $this->sonArticle->id,
            'point_de_vente_id' => $this->sonSite->id,
            'type_mouvement' => MouvementStock::SORTIE,
            'sous_type' => MouvementStock::LIVRAISON,
            'quantite' => 5, 'stock_avant' => 10, 'stock_apres' => 5,
        ]);

        try {
            $mouvement->delete();
            $this->fail('La suppression aurait dû être refusée.');
        } catch (\LogicException) {
            // attendu
        }

        $this->assertDatabaseHas('mouvements_stock', ['id' => $mouvement->id]);
    }

    public function test_le_journal_resiste_a_une_suppression_de_masse(): void
    {
        // `Model::where(...)->delete()` court-circuite les evenements Eloquent :
        // le garde-fou du modele ne s'y applique pas. C'est la limite connue de
        // la protection, et ce test la documente plutot que de la taire.
        MouvementStock::create([
            'produit_id' => $this->sonArticle->id,
            'point_de_vente_id' => $this->sonSite->id,
            'type_mouvement' => MouvementStock::SORTIE,
            'sous_type' => MouvementStock::LIVRAISON,
            'quantite' => 5, 'stock_avant' => 10, 'stock_apres' => 5,
        ]);

        MouvementStock::where('produit_id', $this->sonArticle->id)->delete();

        // La ligne part : aucune protection applicative ne survit a une requete
        // de masse. Le verrou reel est ailleurs — personne n'ecrit ce code, et
        // le seul appelant qui le faisait a ete corrige. Une contrainte de base
        // de donnees serait le prochain cran, si le besoin s'en fait sentir.
        $this->assertSame(0, MouvementStock::where('produit_id', $this->sonArticle->id)->count());
    }

    public function test_une_quantite_de_mouvement_ne_se_reecrit_pas(): void
    {
        $mouvement = MouvementStock::create([
            'produit_id' => $this->sonArticle->id,
            'point_de_vente_id' => $this->sonSite->id,
            'type_mouvement' => MouvementStock::SORTIE,
            'sous_type' => MouvementStock::LIVRAISON,
            'quantite' => 5, 'stock_avant' => 10, 'stock_apres' => 5,
        ]);

        try {
            $mouvement->update(['quantite' => 1, 'stock_apres' => 9]);
            $this->fail('La réécriture aurait dû être refusée.');
        } catch (\LogicException) {
            // attendu
        }

        $this->assertSame(5.0, $mouvement->fresh()->quantite);
    }

    // ── Sans droits ──────────────────────────────────────────────────

    public function test_l_inventaire_est_ferme_aux_visiteurs(): void
    {
        auth()->logout();

        $this->get(route('admin.stock.inventaire'))->assertRedirect();
        $this->post(route('admin.stock.inventaire.enregistrer'), [])->assertRedirect();
    }

    public function test_l_inventaire_est_ferme_si_le_module_stock_l_est(): void
    {
        // Le superadmin ferme le module : l'ecran doit disparaitre, pas
        // seulement son entree de menu.
        $this->attaquant->entreprise->update([
            'modules_actifs' => ['principal', 'ventes'],
        ]);

        // 403 et non une redirection : le middleware des modules refuse
        // franchement plutot que de renvoyer ailleurs.
        $this->get(route('admin.stock.inventaire'))->assertForbidden();
        $this->post(route('admin.stock.inventaire.enregistrer'), [
            'point_de_vente_id' => $this->sonSite->id,
            'comptages'         => [$this->sonArticle->id => 0],
        ])->assertForbidden();

        $this->assertSame(10.0, $this->sonArticle->fresh()->stockActuel($this->sonSite->id));
    }

    // ── Ranger son article dans le rayon du voisin ───────────────────

    public function test_l_api_refuse_une_categorie_d_une_autre_entreprise(): void
    {
        // La regle etait `exists:categories,id`, sans cloisonnement : un
        // appelant pouvait rattacher son article a un rayon du concurrent. Le
        // nom du rayon ressortait alors dans ses propres ecrans.
        $rayonVoisin = \App\Modules\Admin\Modeles\Categorie::create([
            'entreprise_id' => $this->victime->id,
            'nom' => 'Confidentiel — projet Kossou', 'prefixe' => 'KOS',
        ]);

        // La route d'API exige un jeton : on eprouve la regle elle-meme, qui
        // est ce que la correction a change.
        $regle = ['categorie_id' => \App\Modules\Admin\Regles\Appartenance::a('categories')];

        $this->assertFalse(
            \Illuminate\Support\Facades\Validator::make(['categorie_id' => $rayonVoisin->id], $regle)->passes(),
            'Le rayon du voisin aurait dû être refusé.'
        );

        $sien = \App\Modules\Admin\Modeles\Categorie::create([
            'entreprise_id' => $this->attaquant->entreprise_id,
            'nom' => 'Son rayon', 'prefixe' => 'SIE',
        ]);

        $this->assertTrue(
            \Illuminate\Support\Facades\Validator::make(['categorie_id' => $sien->id], $regle)->passes(),
            'Son propre rayon doit rester accepté.'
        );
    }

    public function test_l_api_refuse_une_sous_categorie_d_une_autre_entreprise(): void
    {
        // `sous_categories` ne porte pas `entreprise_id` : elle pend a une
        // categorie. Sans troisieme mode de cloisonnement, elle n'etait pas
        // protegee du tout.
        $rayonVoisin = \App\Modules\Admin\Modeles\Categorie::create([
            'entreprise_id' => $this->victime->id, 'nom' => 'Rayon', 'prefixe' => 'RAY',
        ]);

        $sousRayon = \App\Modules\Admin\Modeles\SousCategorie::create([
            'categorie_id' => $rayonVoisin->id, 'nom' => 'Sous-rayon',
        ]);

        $this->assertFalse(
            \Illuminate\Support\Facades\Validator::make(
                ['sous_categorie_id' => $sousRayon->id],
                ['sous_categorie_id' => \App\Modules\Admin\Regles\Appartenance::a('sous_categories')]
            )->passes(),
            'Le sous-rayon du voisin aurait dû être refusé.'
        );
    }

    // ── Injection dans ce qui est affiché ────────────────────────────

    public function test_un_nom_d_article_ne_s_execute_pas_dans_la_page(): void
    {
        // Le nom vient de l'utilisateur, il ressort dans un tableau : c'est le
        // chemin classique d'un XSS stocke.
        $this->sonArticle->update(['nom' => '<script>alert(1)</script>']);

        $this->get(route('admin.stock.inventaire', ['point_de_vente_id' => $this->sonSite->id]))
            ->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }
}
