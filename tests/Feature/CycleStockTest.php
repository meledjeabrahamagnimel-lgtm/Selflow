<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\Fournisseur;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Vente;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Le stock vu depuis les écrans, et non depuis le service.
 *
 * `StockServiceTest` vérifie le service isolé. Ici on passe par les routes,
 * parce que les défauts que le lot 3 corrige n'étaient pas dans le service —
 * ils étaient dans la façon dont douze endroits l'imitaient chacun à sa manière.
 */
class CycleStockTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private Utilisateur $admin;
    private PointDeVente $magasin;
    private Produit $cacao;
    private Client $client;
    private Fournisseur $fournisseur;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // L'entreprise doit etre completement inscrite et ses modules ouverts :
        // sans cela les middlewares `modules:` et `inscription.complete`
        // renvoient au tableau de bord avant d'atteindre le controleur.
        $this->entreprise = Entreprise::create([
            'nom'              => 'Coopérative du Bandama',
            'regime_imposition' => 'RNI',
            'adresse'          => 'Cocody, Abidjan',
            'rccm'             => 'CI-ABJ-2026-B-00001',
            'ncc'              => '2601234A',
            'gerant_fonction'  => 'Gérant',
            'secteur_activite' => ['Commerce'],
            'modules_actifs'   => ['principal', 'ventes', 'achats', 'stock', 'produits', 'tiers'],
        ]);

        $this->magasin = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Magasin central', 'ville' => 'Abidjan', 'commune' => 'Cocody',
        ]);

        $this->admin = Utilisateur::create([
            'nom' => 'Kouadio', 'prenom' => 'Lewis', 'email' => 'lewis@exemple.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $this->entreprise->id,
            'point_de_vente_id' => $this->magasin->id,
        ]);

        $this->cacao = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'CAC-001',
            'nom' => 'Fèves de cacao', 'type' => 'marchandise', 'unite' => 'kg',
            'prix_achat' => 1200, 'prix_vente' => 1500, 'taux_tva' => 18,
        ]);

        $this->client = Client::create([
            'entreprise_id' => $this->entreprise->id, 'nom' => 'Chocolaterie Baoulé',
        ]);

        $this->fournisseur = Fournisseur::create([
            'entreprise_id' => $this->entreprise->id, 'nom' => 'Planteurs réunis',
        ]);

        $this->actingAs($this->admin)->withSession(['point_de_vente_actif_id' => $this->magasin->id]);
    }

    private function stock(): float
    {
        return $this->cacao->fresh()->stockActuel($this->magasin->id);
    }

    // ── L'achat n'entre plus deux fois ───────────────────────────────

    public function test_une_facture_d_achat_marque_ses_lignes_receptionnees(): void
    {
        // C'etait la seconde porte du meme stock : la facture incrementait, la
        // ligne restait « a receptionner », et valider la reception dans la
        // file de StockControleur incrementait une seconde fois. Rien ne
        // l'interdisait.
        $this->acheter(30);

        $achat = Achat::firstOrFail();

        $this->assertSame(30.0, $this->stock());
        $this->assertSame(30.0, (float) $achat->details->first()->quantite_receptionnee);
    }

    public function test_la_file_des_receptions_ignore_une_facture_deja_entree(): void
    {
        // La file se fonde sur quantite < quantite_receptionnee : une ligne
        // entierement receptionnee n'y figure plus, donc personne ne peut la
        // receptionner une seconde fois.
        $this->acheter(30);

        $this->get(route('admin.stock.receptions'))
            ->assertOk()
            ->assertDontSee('CAC-001');
    }

    public function test_une_reception_de_douze_kilos_et_demi_est_acceptee(): void
    {
        $this->acheter(12.5);

        $this->assertSame(12.5, $this->stock());
    }

    // ── La vente ─────────────────────────────────────────────────────

    public function test_une_facture_de_vente_sort_le_stock_et_journalise(): void
    {
        $this->acheter(30);
        $this->vendre(10);

        $this->assertSame(20.0, $this->stock());

        $sortie = MouvementStock::where('sous_type', MouvementStock::LIVRAISON)->firstOrFail();

        $this->assertSame(10.0, $sortie->quantite);
        $this->assertSame(30.0, $sortie->stock_avant);
        $this->assertSame(20.0, $sortie->stock_apres);
        $this->assertSame(Vente::class, $sortie->piece_type);
    }

    public function test_une_vente_au_dela_du_stock_est_refusee(): void
    {
        $this->acheter(5);

        // La cle de session est `error` et non `erreur` : les deux formes
        // coexistent dans le projet.
        $this->vendre(10)->assertSessionHas('error');

        $this->assertSame(5.0, $this->stock());
    }

    // ── La modification ne réécrit plus l'histoire ───────────────────

    public function test_modifier_une_vente_contre_passe_au_lieu_d_effacer(): void
    {
        // Le code faisait
        //     MouvementStock::where('reference_document', ...)->delete()
        // Le stock revenait juste, mais la sortie de dix sacs disparaissait du
        // journal — et avec elle toute chance d'expliquer un ecart six mois
        // plus tard.
        $this->acheter(30);
        $this->vendre(10);

        $vente = Vente::where('type_piece', '!=', 'avoir')->latest('id')->firstOrFail();

        $this->put(route('admin.ventes.modifier.enregistrer', $vente), [
            'client_id'     => $this->client->id,
            'mode_paiement' => 'Espèces',
            'articles'      => [[
                'produit_id' => $this->cacao->id, 'quantite' => 4,
                'prix_unitaire' => 1500, 'unite' => 'kg',
            ]],
        ]);

        // 30 achetés − 10 vendus + 10 contre-passés − 4 revendus = 26
        $this->assertSame(26.0, $this->stock());

        // Rien n'a disparu : l'entrée, la sortie, sa contre-passation, la
        // nouvelle sortie.
        $this->assertSame(4, MouvementStock::count());
        $this->assertSame(1, MouvementStock::where('sous_type', MouvementStock::CONTREPASSATION)->count());
    }

    // ── L'avoir : le sort de la marchandise ──────────────────────────

    public function test_un_retour_vendable_rentre_en_stock(): void
    {
        $this->acheter(30);
        $this->vendre(10);
        $vente = Vente::where('type_piece', '!=', 'avoir')->latest('id')->firstOrFail();

        $this->avoirSur($vente, 4, 'reinject');

        $this->assertSame(24.0, $this->stock());
        $this->assertSame(1, MouvementStock::where('sous_type', MouvementStock::RETOUR_CLIENT)->count());
        $this->assertSame(0, MouvementStock::where('sous_type', MouvementStock::REBUT)->count());
    }

    public function test_un_retour_defectueux_rentre_puis_part_au_rebut(): void
    {
        // Le rebut ecrivait une entree fantome — quantite N, stock_avant 0,
        // stock_apres 0 — et rangeait sa raison dans une cle `notes` que la
        // table n'a pas. Retour vendable et retour defectueux etaient donc
        // indiscernables a l'ecran, et le rebut invisible.
        $this->acheter(30);
        $this->vendre(10);
        $vente = Vente::where('type_piece', '!=', 'avoir')->latest('id')->firstOrFail();

        $this->avoirSur($vente, 4, 'scrap');

        // La marchandise est bien revenue, puis elle est sortie : le stock ne
        // bouge pas, mais les deux faits sont ecrits.
        $this->assertSame(20.0, $this->stock());
        $this->assertSame(1, MouvementStock::where('sous_type', MouvementStock::RETOUR_CLIENT)->count());
        $this->assertSame(1, MouvementStock::where('sous_type', MouvementStock::REBUT)->count());
    }

    public function test_un_geste_commercial_ne_touche_pas_au_stock(): void
    {
        // La marchandise est restee chez le client : rien a ecrire.
        $this->acheter(30);
        $this->vendre(10);
        $vente = Vente::where('type_piece', '!=', 'avoir')->latest('id')->firstOrFail();

        $avant = MouvementStock::count();
        $this->avoirSur($vente, 4, 'none');

        $this->assertSame(20.0, $this->stock());
        $this->assertSame($avant, MouvementStock::count());
    }

    // ─────────────────────────────────────────────────────────────────

    private function acheter(float $quantite)
    {
        return $this->post(route('admin.achats.enregistrer'), [
            'fournisseur_id' => $this->fournisseur->id,
            'date_achat'     => now()->toDateString(),
            'mode_paiement'  => 'Espèces',
            'etape'          => 'Facture',
            'articles'       => [[
                'produit_id' => $this->cacao->id, 'quantite' => $quantite,
                'prix_unitaire' => 1200, 'unite' => 'kg',
            ]],
        ]);
    }

    private function vendre(float $quantite)
    {
        return $this->post(route('admin.ventes.enregistrer'), [
            'client_id'     => $this->client->id,
            'date_vente'    => now()->toDateString(),
            'mode_paiement' => 'Espèces',
            'etape'         => 'Facture',
            'articles'      => [[
                'produit_id' => $this->cacao->id, 'quantite' => $quantite,
                'prix_unitaire' => 1500, 'unite' => 'kg',
            ]],
        ]);
    }

    private function avoirSur(Vente $vente, float $quantite, string $sort)
    {
        $detail = $vente->details->first();

        return $this->post(route('admin.ventes.avoir.creer_nouveau'), [
            // L'identifiant public : c'est celui que l'écran reçoit de la
            // recherche et renvoie au formulaire.
            'parent_id' => $vente->uuid,
            'raison'    => 'Retour partiel',
            'items'     => [
                $detail->id => [
                    'quantite'      => $quantite,
                    'prix_unitaire' => $detail->prix_unitaire,
                    'stock_action'  => $sort,
                ],
            ],
        ]);
    }
}
