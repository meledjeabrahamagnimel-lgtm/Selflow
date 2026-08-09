<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Categorie;
use App\Modules\Admin\Modeles\CodeJournal;
use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\FicheTechnique;
use App\Modules\Admin\Modeles\FicheTechniqueDetail;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\OrdreProduction;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Stock;
use App\Modules\Admin\Services\StockService;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La fabrication : ce qu'elle consomme, ce qu'elle produit, ce qu'elle écrit.
 *
 * Trois défauts se tenaient ensemble dans `validerOrdre()`, et le premier était
 * grave :
 *
 * - **la comptabilité partait en double.** Depuis le lot 4.2, la porte unique du
 *   stock écrit elle-même l'inventaire permanent ; `validerOrdre()` appelait en
 *   plus `ComptabiliteService::genererEcritureProduction()`, qui écrivait les
 *   deux mêmes paires. Le coût de production ressortait au double, les matières
 *   deux fois en charge, et le compte de stock crédité deux fois pour une seule
 *   sortie. Sur un atelier qui produit tous les jours, le stock de matières
 *   partait en négatif au bilan sans que rien ne le signale ;
 * - **les comptes étaient en dur** — `311000` et `351100` quelle que soit la
 *   famille de l'article ;
 * - **le produit fini entrait à son propre `prix_achat`** — le prix d'achat
 *   d'une chose qu'on ne rachète pas, presque toujours nul. La fabrication
 *   apparaissait en perte sèche.
 */
class ProductionTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private Utilisateur $admin;
    private PointDeVente $atelier;
    private Produit $farine;
    private Produit $levure;
    private Produit $pain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create([
            'nom'               => 'Boulangerie du plateau',
            'regime_imposition' => 'RNI',
            'adresse'           => 'Plateau, Abidjan',
            'rccm'              => 'CI-ABJ-2026-B-00042',
            'ncc'               => '2601234A',
            'gerant_fonction'   => 'Gérant',
            'secteur_activite'  => ['Production'],
            'modules_actifs'    => ['principal', 'production', 'stock', 'produits'],
        ]);

        $this->atelier = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Fournil', 'ville' => 'Abidjan', 'commune' => 'Plateau',
        ]);

        CodeJournal::create([
            'entreprise_id' => $this->entreprise->id,
            'code' => 'OD', 'intitule' => 'Opérations diverses', 'type' => 'OD',
        ]);

        $this->admin = Utilisateur::create([
            'nom' => 'Traoré', 'prenom' => 'Aminata', 'email' => 'aminata@exemple.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $this->entreprise->id,
            'point_de_vente_id' => $this->atelier->id,
        ]);

        // Les matieres vont en 32, les produits finis en 36 : les confondre
        // rendrait le bilan faux plutot qu'imprecis.
        $matieres = Categorie::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Matières premières', 'prefixe' => 'MP',
            'compte_vente' => '701000', 'compte_achat' => '602000',
            'compte_stock' => '321000', 'compte_variation' => '603200',
        ]);

        $finis = Categorie::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Produits finis', 'prefixe' => 'PF',
            'compte_vente' => '701000', 'compte_achat' => '601000',
            'compte_stock' => '361000', 'compte_variation' => '736100',
        ]);

        $this->farine = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'MP-001',
            'nom' => 'Farine de blé', 'type' => 'matiere_premiere', 'unite' => 'kg',
            'prix_achat' => 500, 'prix_vente' => 0, 'categorie_id' => $matieres->id,
        ]);

        $this->levure = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'MP-002',
            'nom' => 'Levure', 'type' => 'matiere_premiere', 'unite' => 'kg',
            'prix_achat' => 2000, 'prix_vente' => 0, 'categorie_id' => $matieres->id,
        ]);

        $this->pain = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'PF-001',
            'nom' => 'Baguette', 'type' => 'produit_fini', 'unite' => 'pièce',
            'prix_achat' => 0, 'prix_vente' => 150, 'categorie_id' => $finis->id,
        ]);

        $fiche = FicheTechnique::create([
            'entreprise_id' => $this->entreprise->id,
            'produit_fini_id' => $this->pain->id,
            'description' => 'Baguette de 250 g',
        ]);

        // 0,2 kg de farine et 0,005 kg de levure par baguette.
        FicheTechniqueDetail::create(['fiche_technique_id' => $fiche->id,
            'ingredient_id' => $this->farine->id, 'quantite' => 0.2, 'unite' => 'kg']);
        FicheTechniqueDetail::create(['fiche_technique_id' => $fiche->id,
            'ingredient_id' => $this->levure->id, 'quantite' => 0.005, 'unite' => 'kg']);

        $this->actingAs($this->admin)
            ->withSession(['point_de_vente_actif_id' => $this->atelier->id]);
    }

    /**
     * Approvisionner le fournil, au coût réellement payé.
     */
    private function approvisionner(): void
    {
        StockService::entree($this->farine, $this->atelier->id, 100, MouvementStock::RECEPTION,
            ['cout_unitaire' => 500]);
        StockService::entree($this->levure, $this->atelier->id, 5, MouvementStock::RECEPTION,
            ['cout_unitaire' => 2000]);
    }

    private function ordre(float $quantite = 100): OrdreProduction
    {
        return OrdreProduction::create([
            'entreprise_id'     => $this->entreprise->id,
            'point_de_vente_id' => $this->atelier->id,
            'produit_fini_id'   => $this->pain->id,
            'code_ordre'        => OrdreProduction::genererCode($this->entreprise->id),
            'quantite_cible'    => $quantite,
            'statut'            => 'Brouillon',
            'date_production'   => now()->toDateString(),
        ]);
    }

    private function valider(OrdreProduction $ordre)
    {
        return $this->post(route('admin.production.ordres.valider', $ordre));
    }

    /**
     * Les écritures d'un compte, débit et crédit confondus.
     */
    private function mouvementSur(string $compte): float
    {
        return (float) EcritureComptable::where('compte_debit', $compte)->sum('debit')
             + (float) EcritureComptable::where('compte_credit', $compte)->sum('credit');
    }

    // ══════════════ La comptabilité ne part plus en double ══════════════

    public function test_la_consommation_de_matiere_n_est_ecrite_qu_une_fois(): void
    {
        // 100 baguettes : 20 kg de farine à 500 = 10 000, et 0,5 kg de levure
        // à 2 000 = 1 000. Le compte de variation des matières porte 11 000 au
        // débit — et non 22 000, comme lorsque les deux services écrivaient
        // chacun leur paire.
        $this->approvisionner();
        $this->valider($this->ordre(100));

        $this->assertSame(11000.0,
            (float) EcritureComptable::where('compte_debit', '603200')->sum('debit'),
            'La consommation de matières était comptée deux fois.');
    }

    public function test_le_stock_de_matiere_n_est_credite_qu_une_fois(): void
    {
        // C'est le versant qui faisait passer le compte de stock en négatif :
        // une seule sortie physique, deux crédits comptables.
        $this->approvisionner();
        $this->valider($this->ordre(100));

        $this->assertSame(11000.0,
            (float) EcritureComptable::where('compte_credit', '321000')->sum('credit'));
    }

    public function test_le_journal_de_stock_ne_porte_qu_un_mouvement_par_matiere(): void
    {
        $this->approvisionner();
        $this->valider($this->ordre(100));

        $this->assertSame(1, MouvementStock::where('produit_id', $this->farine->id)
            ->where('sous_type', MouvementStock::PRODUCTION_CONSOMMATION)->count());
    }

    // ══════════════ Les comptes viennent du rayon ══════════════

    public function test_les_matieres_s_imputent_en_32_et_non_en_31(): void
    {
        // `311000` partait en dur : une boulangerie et une quincaillerie
        // imputaient au meme compte, et les matieres se melangeaient aux
        // marchandises.
        $this->approvisionner();
        $this->valider($this->ordre(100));

        $this->assertSame(0.0, $this->mouvementSur('311000'));
        $this->assertGreaterThan(0, $this->mouvementSur('321000'));
    }

    public function test_le_produit_fini_s_impute_sur_le_compte_de_son_rayon(): void
    {
        // `351100` partait en dur, et n'existe dans aucun plan : le stock de
        // produits finis est en 36.
        $this->approvisionner();
        $this->valider($this->ordre(100));

        $this->assertSame(0.0, $this->mouvementSur('351100'));
        $this->assertSame(11000.0,
            (float) EcritureComptable::where('compte_debit', '361000')->sum('debit'));
    }

    // ══════════════ Le produit fini entre à son coût de revient ══════════════

    public function test_le_produit_fini_entre_au_cout_de_ce_qui_l_a_fabrique(): void
    {
        // 11 000 de matieres pour 100 baguettes : 110 la baguette. Le prix
        // d'achat de la fiche vaut zero — c'est le prix d'achat d'une chose
        // qu'on ne rachete pas — et la fabrication ressortait donc en perte
        // seche : les matieres en charge, et rien en face.
        $this->approvisionner();
        $this->valider($this->ordre(100));

        $this->assertSame(110.0, (float) Stock::where('produit_id', $this->pain->id)
            ->where('point_de_vente_id', $this->atelier->id)->value('cump'));
    }

    public function test_le_cout_suit_le_prix_reellement_paye_et_non_le_catalogue(): void
    {
        // La farine renchérit : le second sac arrive à 700. Le CUMP (Coût
        // Unitaire Moyen Pondéré) des matières passe à 600, et la baguette
        // coûte davantage — ce que `prix_achat`, figé à 500, ne savait pas dire.
        StockService::entree($this->farine, $this->atelier->id, 100, MouvementStock::RECEPTION,
            ['cout_unitaire' => 500]);
        StockService::entree($this->farine, $this->atelier->id, 100, MouvementStock::RECEPTION,
            ['cout_unitaire' => 700]);
        StockService::entree($this->levure, $this->atelier->id, 5, MouvementStock::RECEPTION,
            ['cout_unitaire' => 2000]);

        $this->valider($this->ordre(100));

        // 20 kg × 600 + 0,5 kg × 2 000 = 13 000, soit 130 la baguette.
        $this->assertSame(130.0, (float) Stock::where('produit_id', $this->pain->id)
            ->where('point_de_vente_id', $this->atelier->id)->value('cump'));
    }

    public function test_la_valeur_entree_egale_la_valeur_sortie(): void
    {
        // Rien ne se perd a la fabrication : ce qui sort des matieres entre en
        // produits finis, a l'exact centime pres. C'est ce qui rend le compte
        // de resultat juste.
        $this->approvisionner();
        $this->valider($this->ordre(100));

        $this->assertSame(
            (float) EcritureComptable::where('compte_credit', '321000')->sum('credit'),
            (float) EcritureComptable::where('compte_debit', '361000')->sum('debit')
        );
    }

    public function test_les_ecritures_de_production_sont_equilibrees(): void
    {
        $this->approvisionner();
        $this->valider($this->ordre(100));

        $this->assertEqualsWithDelta(
            EcritureComptable::sum('debit'), EcritureComptable::sum('credit'), 0.01
        );
    }

    // ══════════════ Le stock physique ══════════════

    public function test_les_matieres_sortent_et_le_produit_fini_entre(): void
    {
        $this->approvisionner();
        $this->valider($this->ordre(100));

        $this->assertSame(80.0, $this->farine->fresh()->stockActuel($this->atelier->id));
        $this->assertSame(4.5, $this->levure->fresh()->stockActuel($this->atelier->id));
        $this->assertSame(100.0, $this->pain->fresh()->stockActuel($this->atelier->id));
    }

    public function test_l_ordre_passe_a_termine(): void
    {
        $this->approvisionner();
        $ordre = $this->ordre(100);
        $this->valider($ordre);

        $this->assertSame('Terminé', $ordre->fresh()->statut);
    }

    public function test_un_ordre_deja_termine_ne_fabrique_pas_deux_fois(): void
    {
        $this->approvisionner();
        $ordre = $this->ordre(100);

        $this->valider($ordre);
        $this->valider($ordre);

        $this->assertSame(100.0, $this->pain->fresh()->stockActuel($this->atelier->id));
    }

    public function test_un_stock_de_matiere_insuffisant_arrete_tout(): void
    {
        // Rien ne doit bouger : ni le stock, ni la comptabilite. Un ordre
        // partiellement execute laisserait des matieres consommees sans
        // production en face.
        StockService::entree($this->farine, $this->atelier->id, 1, MouvementStock::RECEPTION,
            ['cout_unitaire' => 500]);

        $ordre = $this->ordre(100);
        $this->valider($ordre);

        $this->assertSame(1.0, $this->farine->fresh()->stockActuel($this->atelier->id));
        $this->assertSame(0.0, $this->pain->fresh()->stockActuel($this->atelier->id));
        $this->assertSame('Brouillon', $ordre->fresh()->statut);
    }

    // ══════════════ Le garde-fou d'imputation ══════════════

    public function test_un_produit_fini_sans_compte_arrete_la_validation(): void
    {
        // Chaque mouvement ecrit sa paire equilibree, ou n'ecrit rien. Une
        // fabrication dont les matieres s'imputent mais dont le produit fini ne
        // s'impute pas reste donc equilibree au bilan — et fausse au compte de
        // resultat : les matieres partent en charge, et rien n'entre en face.
        $this->pain->categorieRelation->update(['compte_stock' => null]);
        $this->approvisionner();

        $ordre = $this->ordre(100);
        $this->valider($ordre)->assertSessionHas('erreur');

        $this->assertSame('Brouillon', $ordre->fresh()->statut);
        $this->assertSame(100.0, $this->farine->fresh()->stockActuel($this->atelier->id));
    }

    public function test_un_atelier_qui_ne_tient_pas_de_comptabilite_fabrique_quand_meme(): void
    {
        // Le controle ne vise que le desequilibre. Un atelier qui n'a rien
        // parametre ne tient simplement pas d'inventaire permanent, et rien ne
        // l'y oblige.
        $this->farine->categorieRelation->update([
            'compte_stock' => null, 'compte_variation' => null,
        ]);
        $this->pain->categorieRelation->update([
            'compte_stock' => null, 'compte_variation' => null,
        ]);
        $this->approvisionner();

        $ordre = $this->ordre(100);
        $this->valider($ordre);

        $this->assertSame('Terminé', $ordre->fresh()->statut);
        $this->assertSame(100.0, $this->pain->fresh()->stockActuel($this->atelier->id));
        $this->assertSame(0, EcritureComptable::count());
    }

    // ══════════════ Cloisonnement ══════════════

    public function test_un_ordre_d_une_autre_entreprise_ne_se_valide_pas(): void
    {
        $autre = Entreprise::create(['nom' => 'Boulangerie concurrente']);
        $sonAtelier = PointDeVente::create([
            'entreprise_id' => $autre->id,
            'nom' => 'Fournil rival', 'ville' => 'Abidjan', 'commune' => 'Yopougon',
        ]);
        $sonPain = Produit::create([
            'entreprise_id' => $autre->id, 'reference' => 'PF-001',
            'nom' => 'Baguette rivale', 'type' => 'produit_fini',
            'prix_achat' => 0, 'prix_vente' => 150,
        ]);

        $sonOrdre = OrdreProduction::create([
            'entreprise_id' => $autre->id, 'point_de_vente_id' => $sonAtelier->id,
            'produit_fini_id' => $sonPain->id, 'code_ordre' => 'OP-2026-0001',
            'quantite_cible' => 10, 'statut' => 'Brouillon',
            'date_production' => now()->toDateString(),
        ]);

        $this->valider($sonOrdre)->assertForbidden();

        $this->assertSame('Brouillon', $sonOrdre->fresh()->statut);
    }
}
