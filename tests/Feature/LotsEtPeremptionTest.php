<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\Lot;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Services\LotService;
use App\Modules\Admin\Services\StockService;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les lots : la péremption appartient à l'arrivage, pas à l'article.
 *
 * `produits.date_peremption` portait **une seule date par article**. Une
 * pharmacie qui reçoit trois arrivages de paracétamol — mars, juin, novembre —
 * n'en enregistrait qu'un : la saisie du troisième écrasait les deux premiers,
 * et les boîtes de mars restaient en rayon sans que rien ne les signale.
 *
 * Trois manques, donc, et un quatrième que le lot a mis au jour :
 *
 * - une date par article au lieu d'une par arrivage ;
 * - aucune traçabilité — un rappel de lot du fabricant était impossible à
 *   honorer, faute de savoir quel arrivage était parti chez quel client ;
 * - aucun ordre de sortie ; rien n'imposait de servir d'abord ce qui périme le
 *   plus tôt ;
 * - **et `bientotPerime()` était faux.** `diffInDays()` rend une différence
 *   signée : une date future donne un nombre négatif, `-200 <= 30` est vrai, et
 *   l'écran des rebuts annonçait donc le catalogue entier comme proche de la
 *   péremption. Une alerte qui crie tout le temps ne se lit plus.
 */
class LotsEtPeremptionTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private Utilisateur $admin;
    private PointDeVente $officine;
    private Produit $paracetamol;
    private Produit $ciment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create([
            'nom'               => 'Pharmacie du Plateau',
            'regime_imposition' => 'RNI',
            'adresse'           => 'Plateau, Abidjan',
            'rccm'              => 'CI-ABJ-2026-B-00099',
            'ncc'               => '2601234A',
            'gerant_fonction'   => 'Pharmacien',
            'secteur_activite'  => ['Santé'],
            'modules_actifs'    => ['principal', 'ventes', 'achats', 'stock', 'produits', 'tiers'],
        ]);

        $this->officine = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Officine centrale', 'ville' => 'Abidjan', 'commune' => 'Plateau',
        ]);

        $this->admin = Utilisateur::create([
            'nom' => 'Diallo', 'prenom' => 'Fatou', 'email' => 'fatou@exemple.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $this->entreprise->id,
            'point_de_vente_id' => $this->officine->id,
        ]);

        $this->paracetamol = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'MED-001',
            'nom' => 'Paracétamol 500 mg', 'type' => 'marchandise', 'unite' => 'boîte',
            'prix_achat' => 400, 'prix_vente' => 600,
            'suivi_par_lot' => true, 'preavis_peremption' => 60,
        ]);

        // Un sac de ciment n'a pas de date : imposer un numero de lot a sa
        // reception ferait perdre du temps sans rien apporter.
        $this->ciment = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'CIM-001',
            'nom' => 'Ciment CPJ 45', 'type' => 'marchandise', 'unite' => 'sac',
            'prix_achat' => 5000, 'prix_vente' => 6500,
            'suivi_par_lot' => false,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['point_de_vente_actif_id' => $this->officine->id]);
    }

    private function recevoir(string $numero, string $peremption, float $quantite, float $cout = 400): ?MouvementStock
    {
        return StockService::entree(
            $this->paracetamol, $this->officine->id, $quantite, MouvementStock::RECEPTION,
            ['cout_unitaire' => $cout, 'lot' => ['numero' => $numero, 'date_peremption' => $peremption]]
        );
    }

    private function lot(string $numero): Lot
    {
        return Lot::where('produit_id', $this->paracetamol->id)
            ->where('numero_lot', $numero)->firstOrFail();
    }

    // ══════════════ Trois arrivages, trois dates ══════════════

    public function test_trois_arrivages_gardent_chacun_leur_date(): void
    {
        // **Le defaut principal** : la saisie du troisieme ecrasait les deux
        // premiers, et les boites de mars restaient en rayon.
        $this->recevoir('L-MARS', now()->addDays(20)->toDateString(), 50);
        $this->recevoir('L-JUIN', now()->addDays(120)->toDateString(), 80);
        $this->recevoir('L-NOVEMBRE', now()->addDays(300)->toDateString(), 100);

        $this->assertSame(3, Lot::where('produit_id', $this->paracetamol->id)->count());
        $this->assertSame(now()->addDays(20)->toDateString(),
            $this->lot('L-MARS')->date_peremption->toDateString());
        $this->assertSame(now()->addDays(300)->toDateString(),
            $this->lot('L-NOVEMBRE')->date_peremption->toDateString());
    }

    public function test_le_stock_reste_la_somme_des_lots(): void
    {
        $this->recevoir('L-MARS', now()->addDays(20)->toDateString(), 50);
        $this->recevoir('L-JUIN', now()->addDays(120)->toDateString(), 80);

        $this->assertSame(130.0, $this->paracetamol->fresh()->stockActuel($this->officine->id));
        $this->assertSame(130.0, LotService::disponible($this->paracetamol, $this->officine->id));
    }

    public function test_deux_receptions_du_meme_numero_alimentent_la_meme_fiche(): void
    {
        // Un numero de lot designe un arrivage du fabricant, pas une livraison.
        $this->recevoir('L-MARS', now()->addDays(20)->toDateString(), 50);
        $this->recevoir('L-MARS', now()->addDays(20)->toDateString(), 30);

        $this->assertSame(1, Lot::where('produit_id', $this->paracetamol->id)->count());
        $this->assertSame(80.0, $this->lot('L-MARS')->quantite);
    }

    public function test_le_cout_du_lot_se_pondere_comme_le_cump(): void
    {
        // C'est la meme marchandise, arrivee en deux fois.
        //   (50 × 400 + 50 × 500) ÷ 100 = 450
        $this->recevoir('L-MARS', now()->addDays(20)->toDateString(), 50, 400);
        $this->recevoir('L-MARS', now()->addDays(20)->toDateString(), 50, 500);

        $this->assertSame(450.0, $this->lot('L-MARS')->cout_unitaire);
    }

    // ══════════════ FEFO, et non FIFO ══════════════

    public function test_la_sortie_sert_d_abord_ce_qui_perime_le_plus_tot(): void
    {
        // Les deux regles coincident souvent, jamais toujours : l'arrivage
        // recent a date courte doit partir avant l'ancien a date longue, et le
        // FIFO laisserait perimer le premier.
        $this->recevoir('L-ANCIEN-DATE-LONGUE', now()->addDays(300)->toDateString(), 50);
        $this->recevoir('L-RECENT-DATE-COURTE', now()->addDays(15)->toDateString(), 50);

        StockService::sortie($this->paracetamol, $this->officine->id, 30, MouvementStock::LIVRAISON);

        $this->assertSame(20.0, $this->lot('L-RECENT-DATE-COURTE')->quantite,
            'Le FEFO doit prendre le lot le plus proche de sa date.');
        $this->assertSame(50.0, $this->lot('L-ANCIEN-DATE-LONGUE')->quantite);
    }

    public function test_une_sortie_peut_couvrir_plusieurs_lots(): void
    {
        $this->recevoir('L-A', now()->addDays(15)->toDateString(), 20);
        $this->recevoir('L-B', now()->addDays(120)->toDateString(), 50);

        StockService::sortie($this->paracetamol, $this->officine->id, 35, MouvementStock::LIVRAISON);

        $this->assertSame(0.0, $this->lot('L-A')->quantite);
        $this->assertSame(35.0, $this->lot('L-B')->quantite);
    }

    public function test_une_sortie_a_cheval_reste_un_seul_mouvement(): void
    {
        // La comptabilite, le CUMP (Cout Unitaire Moyen Pondere) et le journal
        // ne changent pas : un mouvement, et le detail des lots a cote.
        $this->recevoir('L-A', now()->addDays(15)->toDateString(), 20);
        $this->recevoir('L-B', now()->addDays(120)->toDateString(), 50);

        $sortie = StockService::sortie($this->paracetamol, $this->officine->id, 35,
            MouvementStock::LIVRAISON);

        $this->assertSame(1, MouvementStock::where('sous_type', MouvementStock::LIVRAISON)->count());
        $this->assertSame(2, $sortie->lots()->count());
        $this->assertSame(35.0, (float) $sortie->lots()->sum('quantite'));
    }

    public function test_les_lots_sans_date_partent_en_dernier(): void
    {
        // Ils ne periment pas : rien ne presse de les sortir.
        StockService::entree($this->paracetamol, $this->officine->id, 40, MouvementStock::RECEPTION,
            ['lot' => ['numero' => 'L-SANS-DATE']]);
        $this->recevoir('L-AVEC-DATE', now()->addDays(200)->toDateString(), 40);

        StockService::sortie($this->paracetamol, $this->officine->id, 10, MouvementStock::LIVRAISON);

        $this->assertSame(30.0, $this->lot('L-AVEC-DATE')->quantite);
        $this->assertSame(40.0, $this->lot('L-SANS-DATE')->quantite);
    }

    // ══════════════ Le refus de servir du périmé ══════════════

    public function test_livrer_un_lot_perime_est_refuse(): void
    {
        // Vendre un produit perime engage la responsabilite du commercant, et
        // aucun ecran ne rattrape cela apres coup.
        $this->recevoir('L-PERIME', now()->subDay()->toDateString(), 50);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('L-PERIME');

        StockService::sortie($this->paracetamol, $this->officine->id, 10, MouvementStock::LIVRAISON);
    }

    public function test_le_refus_ne_touche_pas_au_stock(): void
    {
        $this->recevoir('L-PERIME', now()->subDay()->toDateString(), 50);

        try {
            StockService::sortie($this->paracetamol, $this->officine->id, 10, MouvementStock::LIVRAISON);
        } catch (\RuntimeException) {
            // attendu
        }

        $this->assertSame(50.0, $this->paracetamol->fresh()->stockActuel($this->officine->id));
        $this->assertSame(50.0, $this->lot('L-PERIME')->quantite);
    }

    public function test_le_rebut_emporte_le_lot_perime(): void
    {
        // C'est par la que la marchandise perimee s'en va : elle est tracee
        // comme une perte, non comme une vente.
        $this->recevoir('L-PERIME', now()->subDay()->toDateString(), 50);

        StockService::sortie($this->paracetamol, $this->officine->id, 50, MouvementStock::REBUT);

        $this->assertSame(0.0, $this->lot('L-PERIME')->quantite);
        $this->assertSame(0.0, $this->paracetamol->fresh()->stockActuel($this->officine->id));
    }

    public function test_le_retour_fournisseur_emporte_aussi_le_perime(): void
    {
        // Un lot perime se renvoie.
        $this->recevoir('L-PERIME', now()->subDay()->toDateString(), 50);

        StockService::sortie($this->paracetamol, $this->officine->id, 50,
            MouvementStock::RETOUR_FOURNISSEUR);

        $this->assertSame(0.0, $this->lot('L-PERIME')->quantite);
    }

    public function test_un_lot_du_jour_meme_se_sert_encore(): void
    {
        // « A consommer jusqu'au 30 » veut dire que le 30 est compris.
        $this->recevoir('L-AUJOURDHUI', now()->toDateString(), 50);

        StockService::sortie($this->paracetamol, $this->officine->id, 10, MouvementStock::LIVRAISON);

        $this->assertSame(40.0, $this->lot('L-AUJOURDHUI')->quantite);
    }

    public function test_un_lot_perime_ne_bloque_pas_ce_qui_ne_l_est_pas_apres_rebut(): void
    {
        $this->recevoir('L-PERIME', now()->subDay()->toDateString(), 20);
        $this->recevoir('L-BON', now()->addDays(200)->toDateString(), 50);

        StockService::sortie($this->paracetamol, $this->officine->id, 20, MouvementStock::REBUT);
        StockService::sortie($this->paracetamol, $this->officine->id, 10, MouvementStock::LIVRAISON);

        $this->assertSame(40.0, $this->lot('L-BON')->quantite);
    }

    // ══════════════ La traçabilité ══════════════

    public function test_un_lot_dit_chez_qui_il_est_parti(): void
    {
        // C'est la question que pose un rappel du fabricant, et a laquelle rien
        // ne savait repondre.
        $client = Client::create([
            'entreprise_id' => $this->entreprise->id, 'nom' => 'Clinique Sainte-Anne',
        ]);

        $this->recevoir('L-RAPPELE', now()->addDays(200)->toDateString(), 50);

        StockService::sortie($this->paracetamol, $this->officine->id, 12, MouvementStock::LIVRAISON,
            ['client_id' => $client->id, 'reference' => 'VTE-000123']);

        $trace = LotService::tracer($this->lot('L-RAPPELE'));

        $this->assertCount(2, $trace, 'La réception et la livraison figurent toutes deux.');

        $livraison = $trace->firstWhere('motif', MouvementStock::LIVRAISON);

        $this->assertSame(12.0, $livraison['quantite']);
        $this->assertSame('Clinique Sainte-Anne', $livraison['client']);
        $this->assertSame('VTE-000123', $livraison['piece']);
    }

    // ══════════════ La contre-passation ══════════════

    public function test_annuler_une_livraison_rend_au_lot_ce_qu_elle_avait_pris(): void
    {
        // Sans cela, une vente annulee ferait disparaitre la tracabilite du lot
        // vendu, et un rappel ne retrouverait plus le client.
        $this->recevoir('L-A', now()->addDays(200)->toDateString(), 50);

        $sortie = StockService::sortie($this->paracetamol, $this->officine->id, 20,
            MouvementStock::LIVRAISON);

        StockService::contrePasser($sortie);

        $this->assertSame(50.0, $this->lot('L-A')->quantite);
        $this->assertSame(50.0, $this->paracetamol->fresh()->stockActuel($this->officine->id));
    }

    public function test_annuler_une_reception_reprend_au_lot_ce_qu_elle_lui_avait_donne(): void
    {
        // **Le sens de l'origine commande.** Rendre dans les deux cas
        // fabriquerait de la marchandise : une reception annulee laisserait son
        // lot plein.
        $reception = $this->recevoir('L-A', now()->addDays(200)->toDateString(), 50);

        StockService::contrePasser($reception);

        $this->assertSame(0.0, $this->lot('L-A')->quantite);
        $this->assertSame(0.0, $this->paracetamol->fresh()->stockActuel($this->officine->id));
    }

    public function test_la_contre_passation_porte_la_meme_ventilation(): void
    {
        $this->recevoir('L-A', now()->addDays(15)->toDateString(), 20);
        $this->recevoir('L-B', now()->addDays(200)->toDateString(), 50);

        $sortie = StockService::sortie($this->paracetamol, $this->officine->id, 35,
            MouvementStock::LIVRAISON);

        $inverse = StockService::contrePasser($sortie);

        $this->assertSame(2, $inverse->lots()->count());
        $this->assertSame(35.0, (float) $inverse->lots()->sum('quantite'));
    }

    // ══════════════ Les articles sans suivi ══════════════

    public function test_un_article_sans_suivi_n_ecrit_aucun_lot(): void
    {
        // Imposer un numero de lot a la reception d'un sac de ciment ferait
        // perdre du temps sans rien apporter.
        StockService::entree($this->ciment, $this->officine->id, 100, MouvementStock::RECEPTION,
            ['cout_unitaire' => 5000, 'lot' => ['numero' => 'IGNORE']]);

        $this->assertSame(0, Lot::where('produit_id', $this->ciment->id)->count());
        $this->assertSame(100.0, $this->ciment->fresh()->stockActuel($this->officine->id));
    }

    public function test_une_entree_sans_numero_de_lot_n_ecrit_rien_mais_bouge_le_stock(): void
    {
        // Un stock d'ouverture, un transfert, un ecart d'inventaire n'ont pas
        // d'arrivage a eux. Bloquer la reception serait pire : l'ecart se lit
        // ensuite entre le stock et la somme des lots.
        StockService::entree($this->paracetamol, $this->officine->id, 40, MouvementStock::INVENTAIRE);

        $this->assertSame(0, Lot::where('produit_id', $this->paracetamol->id)->count());
        $this->assertSame(40.0, $this->paracetamol->fresh()->stockActuel($this->officine->id));
        $this->assertSame(0.0, LotService::disponible($this->paracetamol, $this->officine->id));
    }

    public function test_une_sortie_sans_lot_disponible_ne_bloque_pas_la_vente(): void
    {
        // Le stock a pu etre pose avant l'activation du suivi. Bloquer une
        // vente pour une raison que l'utilisateur ne peut pas corriger
        // sur-le-champ serait pire que la ventilation partielle.
        StockService::entree($this->paracetamol, $this->officine->id, 40, MouvementStock::INVENTAIRE);

        $sortie = StockService::sortie($this->paracetamol, $this->officine->id, 10,
            MouvementStock::LIVRAISON);

        $this->assertNotNull($sortie);
        $this->assertSame(0, $sortie->lots()->count());
        $this->assertSame(30.0, $this->paracetamol->fresh()->stockActuel($this->officine->id));
    }

    // ══════════════ L'alerte qui criait tout le temps ══════════════

    public function test_une_date_lointaine_n_est_pas_proche_de_la_peremption(): void
    {
        // **Le calcul d'origine etait faux** : `diffInDays()` rend une
        // difference signee, `-200 <= 30` est vrai, et l'ecran des rebuts
        // annoncait le catalogue entier comme proche de la peremption.
        $this->recevoir('L-LOINTAIN', now()->addDays(300)->toDateString(), 50);

        $this->assertFalse($this->lot('L-LOINTAIN')->bientotPerime());
        $this->assertCount(0, LotService::bientotPerimes($this->entreprise->id, $this->officine->id));
    }

    public function test_le_meme_defaut_est_corrige_sur_la_fiche_article(): void
    {
        $this->ciment->update(['date_peremption' => now()->addDays(300)->toDateString()]);

        $this->assertFalse($this->ciment->fresh()->bientotPerime(),
            'Une date à 300 jours n\'est pas proche : la comparaison signée disait le contraire.');
    }

    public function test_une_date_proche_est_bien_signalee(): void
    {
        // Le preavis de l'article vaut 60 jours : un medicament se retire des
        // rayons bien plus tot qu'une denree.
        $this->recevoir('L-PROCHE', now()->addDays(30)->toDateString(), 50);

        $this->assertTrue($this->lot('L-PROCHE')->bientotPerime());
        $this->assertCount(1, LotService::bientotPerimes($this->entreprise->id, $this->officine->id));
    }

    public function test_le_preavis_suit_l_article_et_non_un_reglage_unique(): void
    {
        // Trente jours conviennent a l'alimentaire ; un medicament se retire
        // bien plus tot, un cosmetique bien plus tard. Un preavis unique fait
        // crier l'alerte au mauvais moment.
        $this->recevoir('L-45J', now()->addDays(45)->toDateString(), 50);

        $this->assertTrue($this->lot('L-45J')->bientotPerime(), 'Préavis de l\'article : 60 jours.');

        $this->paracetamol->update(['preavis_peremption' => 15]);

        $this->assertFalse($this->lot('L-45J')->fresh()->bientotPerime());
    }

    public function test_un_lot_perime_et_vide_n_encombre_plus_l_ecran(): void
    {
        $this->recevoir('L-PERIME', now()->subDay()->toDateString(), 50);
        StockService::sortie($this->paracetamol, $this->officine->id, 50, MouvementStock::REBUT);

        $this->assertCount(0, LotService::perimes($this->entreprise->id, $this->officine->id));
    }

    public function test_l_ecran_des_rebuts_liste_les_lots(): void
    {
        $this->recevoir('L-PERIME', now()->subDays(5)->toDateString(), 50);
        $this->recevoir('L-PROCHE', now()->addDays(20)->toDateString(), 30);

        $this->get(route('admin.stock.rebut'))
            ->assertOk()
            ->assertSee('L-PERIME')
            ->assertSee('L-PROCHE');
    }

    // ══════════════ Le cloisonnement par site ══════════════

    public function test_le_meme_numero_de_lot_fait_deux_fiches_sur_deux_sites(): void
    {
        // Comme le stock lui-meme : la marchandise n'est pas au meme endroit.
        $annexe = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Officine annexe', 'ville' => 'Abidjan', 'commune' => 'Cocody',
        ]);

        $this->recevoir('L-A', now()->addDays(200)->toDateString(), 50);
        StockService::entree($this->paracetamol, $annexe->id, 30, MouvementStock::RECEPTION,
            ['lot' => ['numero' => 'L-A', 'date_peremption' => now()->addDays(200)->toDateString()]]);

        $this->assertSame(2, Lot::where('numero_lot', 'L-A')->count());
        $this->assertSame(50.0, LotService::disponible($this->paracetamol, $this->officine->id));
        $this->assertSame(30.0, LotService::disponible($this->paracetamol, $annexe->id));
    }

    public function test_une_sortie_ne_pioche_pas_dans_le_lot_d_un_autre_site(): void
    {
        $annexe = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Officine annexe', 'ville' => 'Abidjan', 'commune' => 'Cocody',
        ]);

        // Le lot de l'annexe perime plus tot : le FEFO le prendrait s'il ne
        // regardait pas le site.
        StockService::entree($this->paracetamol, $annexe->id, 30, MouvementStock::RECEPTION,
            ['lot' => ['numero' => 'L-ANNEXE', 'date_peremption' => now()->addDay()->toDateString()]]);
        $this->recevoir('L-CENTRALE', now()->addDays(200)->toDateString(), 50);

        StockService::sortie($this->paracetamol, $this->officine->id, 10, MouvementStock::LIVRAISON);

        $this->assertSame(30.0, $this->lot('L-ANNEXE')->quantite);
        $this->assertSame(40.0, $this->lot('L-CENTRALE')->quantite);
    }

    // ══════════════ Ce qu'un attaquant tenterait ══════════════

    public function test_la_vente_refuse_le_perime_avant_d_ecrire_quoi_que_ce_soit(): void
    {
        // Le FEFO propose le lot le plus proche de sa date : c'est donc lui qui
        // bloque, et il faut le dire avant d'ecrire. Vendre un produit perime
        // engage la responsabilite du commercant, et aucun ecran ne rattrape
        // cela apres coup.
        $this->recevoir('L-PERIME', now()->subDays(3)->toDateString(), 50);

        $this->post(route('admin.ventes.enregistrer'), [
            'etape'         => 'Facture',
            'mode_paiement' => 'Espèces',
            'articles'      => [[
                'produit_id' => $this->paracetamol->id, 'quantite' => 5, 'unite' => 'boîte',
            ]],
        ])->assertSessionHas('error');

        $this->assertSame(0, \App\Modules\Admin\Modeles\Vente::count());
        $this->assertSame(50.0, $this->paracetamol->fresh()->stockActuel($this->officine->id));
        $this->assertSame(50.0, $this->lot('L-PERIME')->quantite);
    }

    public function test_un_lot_perime_au_fond_de_la_file_ne_bloque_pas_la_vente(): void
    {
        // Le refus doit viser ce que le FEFO sortirait, non tout ce qui traine.
        // Bloquer sur un lot qu'on n'atteindra pas arreterait la caisse sans
        // raison.
        $this->recevoir('L-BON', now()->addDays(10)->toDateString(), 50);

        // Un lot sans date passe en dernier : il ne perime pas.
        StockService::entree($this->paracetamol, $this->officine->id, 20, MouvementStock::RECEPTION,
            ['lot' => ['numero' => 'L-SANS-DATE']]);

        $this->post(route('admin.ventes.enregistrer'), [
            'etape'         => 'Facture',
            'mode_paiement' => 'Espèces',
            'articles'      => [[
                'produit_id' => $this->paracetamol->id, 'quantite' => 5, 'unite' => 'boîte',
            ]],
        ]);

        $this->assertSame(1, \App\Modules\Admin\Modeles\Vente::count());
        $this->assertSame(45.0, $this->lot('L-BON')->quantite);
    }

    public function test_un_preavis_demesure_est_refuse(): void
    {
        // La colonne est un `smallint` : sans borne, la valeur serait tronquee
        // en base et le preavis vaudrait autre chose que ce qui a ete saisi.
        $this->put(route('admin.produits.modifier', $this->paracetamol), [
            'nom' => 'Paracétamol 500 mg', 'type' => 'marchandise',
            'prix_achat' => 400, 'prix_vente' => 600, 'taux_tva' => 18,
            'compte_vente' => '701000', 'compte_achat' => '601000',
            'stock_actuel' => 0, 'stock_minimum' => 5,
            'preavis_peremption' => 999999,
        ])->assertSessionHasErrors('preavis_peremption');

        $this->assertSame(60, $this->paracetamol->fresh()->preavis_peremption);
    }

    public function test_la_date_de_peremption_saisie_est_enfin_enregistree(): void
    {
        // **Le champ etait au formulaire et n'etait pas enregistre.**
        // L'utilisateur saisissait une date, l'ecran la reprenait vide a la
        // visite suivante, et l'alerte des rebuts ne voyait jamais l'article.
        $this->put(route('admin.produits.modifier', $this->ciment), [
            'nom' => 'Ciment CPJ 45', 'type' => 'marchandise',
            'prix_achat' => 5000, 'prix_vente' => 6500, 'taux_tva' => 18,
            'compte_vente' => '701000', 'compte_achat' => '601000',
            'stock_actuel' => 0, 'stock_minimum' => 5,
            'date_peremption' => now()->addDays(90)->toDateString(),
        ]);

        $this->assertSame(now()->addDays(90)->toDateString(),
            $this->ciment->fresh()->date_peremption?->toDateString());
    }

    public function test_un_lot_ne_se_lit_pas_depuis_une_autre_entreprise(): void
    {
        // Un rappel de lot est une information commerciale : savoir ce qu'un
        // concurrent a en stock et quand cela perime en dit long sur ses
        // achats.
        $autre = Entreprise::create(['nom' => 'Pharmacie concurrente']);
        $sonOfficine = PointDeVente::create([
            'entreprise_id' => $autre->id,
            'nom' => 'Officine rivale', 'ville' => 'Abidjan', 'commune' => 'Yopougon',
        ]);
        $sonProduit = Produit::create([
            'entreprise_id' => $autre->id, 'reference' => 'MED-001',
            'nom' => 'Amoxicilline', 'type' => 'marchandise',
            'prix_achat' => 800, 'prix_vente' => 1200, 'suivi_par_lot' => true,
        ]);

        StockService::entree($sonProduit, $sonOfficine->id, 30, MouvementStock::RECEPTION,
            ['lot' => ['numero' => 'L-CONCURRENT', 'date_peremption' => now()->subDay()->toDateString()]]);

        $this->assertCount(0, LotService::perimes($this->entreprise->id));

        $this->get(route('admin.stock.rebut'))
            ->assertOk()
            ->assertDontSee('L-CONCURRENT')
            ->assertDontSee('Amoxicilline');
    }

    public function test_un_transfert_sort_du_lot_source_sans_en_creer_a_l_arrivee(): void
    {
        // Sans numero de lot dans le contexte, l'entree a destination n'ecrit
        // pas de lot : le suivi se regularise a l'arrivee. L'ecart se lit.
        $annexe = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Officine annexe', 'ville' => 'Abidjan', 'commune' => 'Cocody',
        ]);

        $this->recevoir('L-A', now()->addDays(200)->toDateString(), 50);

        StockService::transferer($this->paracetamol, $this->officine->id, $annexe->id, 20);

        $this->assertSame(30.0, $this->lot('L-A')->quantite);
        $this->assertSame(20.0, $this->paracetamol->fresh()->stockActuel($annexe->id));
    }
}
