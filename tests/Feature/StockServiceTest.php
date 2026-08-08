<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Services\StockService;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La seule porte par laquelle le stock bouge.
 *
 * Le couple « modifier la fiche, puis écrire le mouvement » était recopié dans
 * plus de douze endroits. Ces tests fixent ce que le service garantit, et
 * surtout ce qu'il refuse : un journal qui s'efface, une quantité négative, un
 * motif inventé, une contre-passation faite deux fois.
 */
class StockServiceTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private Produit $cacao;
    private Produit $mission;
    private PointDeVente $magasin;
    private PointDeVente $depot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create(['nom' => 'Coopérative du Bandama']);

        $this->magasin = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Magasin central', 'ville' => 'Abidjan', 'commune' => 'Cocody',
        ]);

        $this->depot = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Dépôt de Bouaké', 'ville' => 'Bouaké', 'commune' => 'Bouaké',
        ]);

        $this->cacao = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'CAC-001',
            'nom' => 'Fèves de cacao', 'type' => 'marchandise', 'unite' => 'kg',
            'prix_achat' => 1200, 'prix_vente' => 1500,
        ]);

        $this->mission = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'PRESTA-001',
            'nom' => 'Audit qualité', 'type' => 'service', 'unite' => 'mission',
            'prix_achat' => 0, 'prix_vente' => 250000,
        ]);
    }

    // ── Le geste de base ─────────────────────────────────────────────

    public function test_une_entree_modifie_la_fiche_et_ecrit_le_mouvement(): void
    {
        $mouvement = StockService::entree($this->cacao, $this->magasin->id, 12.5, MouvementStock::RECEPTION);

        $this->assertSame(12.5, $this->cacao->fresh()->stockActuel($this->magasin->id));
        $this->assertSame(0.0, $mouvement->stock_avant);
        $this->assertSame(12.5, $mouvement->stock_apres);
        $this->assertSame(MouvementStock::ENTREE, $mouvement->type_mouvement);
        $this->assertSame(MouvementStock::RECEPTION, $mouvement->sous_type);
    }

    public function test_une_sortie_retire_et_journalise(): void
    {
        StockService::entree($this->cacao, $this->magasin->id, 20, MouvementStock::RECEPTION);
        $mouvement = StockService::sortie($this->cacao, $this->magasin->id, 7.25, MouvementStock::LIVRAISON);

        $this->assertSame(12.75, $this->cacao->fresh()->stockActuel($this->magasin->id));
        $this->assertSame(20.0, $mouvement->stock_avant);
        $this->assertSame(12.75, $mouvement->stock_apres);
    }

    public function test_la_fiche_et_le_mouvement_racontent_la_meme_chose(): void
    {
        // C'est la garantie que douze copies du meme couple ne donnaient pas :
        // le stock apres le dernier mouvement est le stock de la fiche.
        foreach ([10, 2.5, 0.125] as $quantite) {
            StockService::entree($this->cacao, $this->magasin->id, $quantite, MouvementStock::RECEPTION);
        }
        StockService::sortie($this->cacao, $this->magasin->id, 3, MouvementStock::LIVRAISON);

        $dernier = MouvementStock::latest('id')->first();

        $this->assertSame(9.625, $this->cacao->fresh()->stockActuel($this->magasin->id));
        $this->assertSame(9.625, $dernier->stock_apres);
    }

    public function test_un_service_ne_produit_aucun_mouvement(): void
    {
        // Une prestation ne s'epuise pas : ni fiche, ni ligne de journal.
        $this->assertNull(
            StockService::entree($this->mission, $this->magasin->id, 3, MouvementStock::RECEPTION)
        );

        $this->assertDatabaseMissing('mouvements_stock', ['produit_id' => $this->mission->id]);
        $this->assertDatabaseMissing('stocks', ['produit_id' => $this->mission->id]);
    }

    // ── Ce que le service refuse ─────────────────────────────────────

    public function test_une_quantite_negative_est_refusee(): void
    {
        // Le sens est porte par le type du mouvement, jamais par le signe :
        // une sortie de -5 serait une entree deguisee, invisible aux filtres.
        $this->expectException(\InvalidArgumentException::class);

        StockService::sortie($this->cacao, $this->magasin->id, -5, MouvementStock::LIVRAISON);
    }

    public function test_une_quantite_nulle_est_refusee(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        StockService::entree($this->cacao, $this->magasin->id, 0, MouvementStock::RECEPTION);
    }

    public function test_un_motif_inconnu_est_refuse(): void
    {
        // Sans ce controle, une faute de frappe creait un motif que les filtres
        // de l'ecran des mouvements ne connaissaient pas : la ligne existait,
        // mais aucune section ne l'affichait.
        $this->expectException(\InvalidArgumentException::class);

        StockService::entree($this->cacao, $this->magasin->id, 5, 'Réception');
    }

    public function test_un_transfert_vers_le_meme_site_est_refuse(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        StockService::transferer($this->cacao, $this->magasin->id, $this->magasin->id, 5);
    }

    // ── Le journal ne s'efface pas ───────────────────────────────────

    public function test_un_mouvement_ne_se_supprime_pas(): void
    {
        $mouvement = StockService::entree($this->cacao, $this->magasin->id, 10, MouvementStock::RECEPTION);

        $this->expectException(\LogicException::class);

        $mouvement->delete();
    }

    public function test_les_colonnes_qui_font_foi_ne_se_reecrivent_pas(): void
    {
        $mouvement = StockService::entree($this->cacao, $this->magasin->id, 10, MouvementStock::RECEPTION);

        $this->expectException(\LogicException::class);

        $mouvement->update(['quantite' => 999]);
    }

    public function test_le_libelle_d_affichage_reste_modifiable(): void
    {
        // `reference_document` n'est pas une colonne qui fait foi : corriger un
        // libelle ne reecrit pas l'histoire.
        $mouvement = StockService::entree($this->cacao, $this->magasin->id, 10, MouvementStock::RECEPTION);

        $mouvement->update(['reference_document' => 'BON-2026-0042']);

        $this->assertSame('BON-2026-0042', $mouvement->fresh()->reference_document);
    }

    // ── La contre-passation ──────────────────────────────────────────

    public function test_contre_passer_ecrit_l_inverse_et_garde_l_original(): void
    {
        $sortie = StockService::sortie(
            $this->cacao, $this->magasin->id, 10, MouvementStock::LIVRAISON,
            ['reference' => 'FAC-001']
        );

        $inverse = StockService::contrePasser($sortie);

        $this->assertSame(MouvementStock::ENTREE, $inverse->type_mouvement);
        $this->assertSame(MouvementStock::CONTREPASSATION, $inverse->sous_type);
        $this->assertSame($sortie->id, $inverse->contrepasse_id);
        $this->assertSame(10.0, $inverse->quantite);

        // Les deux lignes restent lisibles, et le stock est revenu.
        $this->assertSame(2, MouvementStock::count());
        $this->assertSame(0.0, $this->cacao->fresh()->stockActuel($this->magasin->id));
        $this->assertNotNull($sortie->fresh());
    }

    public function test_on_ne_contre_passe_pas_deux_fois_le_meme_mouvement(): void
    {
        // Le refaire fabriquerait de la marchandise qui n'a jamais existe.
        $sortie = StockService::sortie($this->cacao, $this->magasin->id, 10, MouvementStock::LIVRAISON);
        StockService::contrePasser($sortie);

        $this->expectException(\LogicException::class);

        StockService::contrePasser($sortie->fresh());
    }

    public function test_contre_passer_une_piece_reprend_tous_ses_mouvements(): void
    {
        // C'est ce qu'il faut appeler en modifiant une vente deja facturee.
        // VenteControleur faisait un delete() : le stock revenait juste, mais
        // l'histoire disparaissait.
        $vente = $this->uneVente();

        StockService::sortie($this->cacao, $this->magasin->id, 4, MouvementStock::LIVRAISON, ['piece' => $vente]);
        StockService::sortie($this->cacao, $this->depot->id, 6, MouvementStock::LIVRAISON, ['piece' => $vente]);

        // Un mouvement etranger a la piece, qui ne doit pas bouger.
        StockService::sortie($this->cacao, $this->magasin->id, 1, MouvementStock::REBUT);

        $this->assertSame(2, StockService::contrePasserLaPiece($vente));

        $this->assertSame(0.0, $this->cacao->fresh()->stockActuel($this->depot->id));
        $this->assertSame(-1.0, $this->cacao->fresh()->stockActuel($this->magasin->id));
        $this->assertSame(5, MouvementStock::count());
    }

    public function test_contre_passer_une_piece_deja_reprise_ne_fait_rien(): void
    {
        $vente = $this->uneVente();
        StockService::sortie($this->cacao, $this->magasin->id, 4, MouvementStock::LIVRAISON, ['piece' => $vente]);

        $this->assertSame(1, StockService::contrePasserLaPiece($vente));
        $this->assertSame(0, StockService::contrePasserLaPiece($vente));
    }

    public function test_le_mouvement_designe_sa_piece(): void
    {
        // `reference_document` est une chaine libre : renumeroter une facture
        // rompait le lien sans que rien ne le signale.
        $vente = $this->uneVente();

        $mouvement = StockService::sortie(
            $this->cacao, $this->magasin->id, 4, MouvementStock::LIVRAISON, ['piece' => $vente]
        );

        $this->assertTrue($mouvement->piece->is($vente));
    }

    // ── Le transfert ─────────────────────────────────────────────────

    public function test_un_transfert_ecrit_deux_mouvements_et_deplace_la_marchandise(): void
    {
        StockService::entree($this->cacao, $this->magasin->id, 30, MouvementStock::RECEPTION);

        [$sortie, $entree] = StockService::transferer($this->cacao, $this->magasin->id, $this->depot->id, 12.5);

        $this->assertSame(17.5, $this->cacao->fresh()->stockActuel($this->magasin->id));
        $this->assertSame(12.5, $this->cacao->fresh()->stockActuel($this->depot->id));

        // La contrepartie est l'autre site, dans les deux sens : la colonne
        // s'appelait « source » et recevait la destination sur la sortie.
        $this->assertSame($this->depot->id, $sortie->point_de_vente_contrepartie_id);
        $this->assertSame($this->magasin->id, $entree->point_de_vente_contrepartie_id);
    }

    // ── L'inventaire ─────────────────────────────────────────────────

    public function test_un_comptage_superieur_ecrit_une_entree(): void
    {
        StockService::entree($this->cacao, $this->magasin->id, 10, MouvementStock::RECEPTION);

        $ecart = StockService::inventorier($this->cacao, $this->magasin->id, 12.5);

        $this->assertSame(MouvementStock::ENTREE, $ecart->type_mouvement);
        $this->assertSame(2.5, $ecart->quantite);
        $this->assertSame(12.5, $this->cacao->fresh()->stockActuel($this->magasin->id));
    }

    public function test_un_comptage_inferieur_ecrit_une_sortie(): void
    {
        StockService::entree($this->cacao, $this->magasin->id, 10, MouvementStock::RECEPTION);

        $ecart = StockService::inventorier($this->cacao, $this->magasin->id, 8);

        $this->assertSame(MouvementStock::SORTIE, $ecart->type_mouvement);
        $this->assertSame(2.0, $ecart->quantite);
        $this->assertSame(8.0, $this->cacao->fresh()->stockActuel($this->magasin->id));
    }

    public function test_un_comptage_conforme_n_ecrit_rien(): void
    {
        // Un journal n'a pas a porter des lignes a zero.
        StockService::entree($this->cacao, $this->magasin->id, 10, MouvementStock::RECEPTION);

        $this->assertNull(StockService::inventorier($this->cacao, $this->magasin->id, 10));
        $this->assertSame(1, MouvementStock::count());
    }

    // ─────────────────────────────────────────────────────────────────

    private function uneVente(): Vente
    {
        $utilisateur = Utilisateur::create([
            'nom' => 'Kouadio', 'prenom' => 'Lewis', 'email' => 'lewis@exemple.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $this->entreprise->id,
        ]);

        return Vente::create([
            'point_de_vente_id' => $this->magasin->id,
            'utilisateur_id'    => $utilisateur->id,
            'numero_facture'    => 'FAC-2026-0001',
            'date_vente'        => now(),
            'mode_paiement'     => 'especes',
            'montant_ht'        => 15000,
            'montant_tva'       => 2700,
            'montant_ttc'       => 17700,
        ]);
    }
}
