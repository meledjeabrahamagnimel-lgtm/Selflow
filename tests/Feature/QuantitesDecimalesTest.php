<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Stock;
use App\Modules\Admin\Regles\Quantite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Les quantités ne sont plus entières.
 *
 * Le référentiel livre des kilos, des litres et des mètres carrés. Tant que la
 * colonne était un `integer`, 12,5 kg de cacao entraient en stock pour 12 —
 * sans erreur, sans message, sans trace. Au bout d'un an de réceptions, l'écart
 * entre le stock théorique et le comptage physique n'avait plus d'explication.
 *
 * Ces tests tiennent la chaîne entière : la colonne, le cast, l'accesseur, et
 * la règle de validation qui garde l'entrée.
 */
class QuantitesDecimalesTest extends TestCase
{
    use RefreshDatabase;

    private Produit $cacao;
    private PointDeVente $site;

    protected function setUp(): void
    {
        parent::setUp();

        $entreprise = Entreprise::create(['nom' => 'Coopérative du Bandama']);

        $this->site = PointDeVente::create([
            'entreprise_id' => $entreprise->id,
            'nom' => 'Magasin central', 'ville' => 'Abidjan', 'commune' => 'Cocody',
        ]);

        $this->cacao = Produit::create([
            'entreprise_id' => $entreprise->id, 'reference' => 'CAC-001',
            'nom' => 'Fèves de cacao', 'type' => 'marchandise', 'unite' => 'kg',
            'prix_achat' => 1200, 'prix_vente' => 1500,
        ]);
    }

    public function test_la_fiche_de_stock_retient_les_decimales(): void
    {
        $stock = Stock::create([
            'produit_id' => $this->cacao->id,
            'point_de_vente_id' => $this->site->id,
            'quantite_disponible' => 12.5,
        ]);

        $this->assertSame(12.5, $stock->fresh()->quantite_disponible);
    }

    public function test_le_millieme_survit_a_l_aller_retour_en_base(): void
    {
        // Trois decimales : le gramme sur le kilo, le millilitre sur le litre.
        $stock = Stock::create([
            'produit_id' => $this->cacao->id,
            'point_de_vente_id' => $this->site->id,
            'quantite_disponible' => 0.125,
        ]);

        $this->assertSame(0.125, $stock->fresh()->quantite_disponible);
    }

    public function test_l_accesseur_du_produit_ne_tronque_plus(): void
    {
        // Le defaut se trouvait aussi a la lecture : stockActuel() etait typee
        // `int`, et PHP ramenait 12,5 a 12 meme quand la base tenait la bonne
        // valeur.
        Stock::create([
            'produit_id' => $this->cacao->id,
            'point_de_vente_id' => $this->site->id,
            'quantite_disponible' => 12.5,
        ]);

        $this->assertSame(12.5, $this->cacao->fresh()->stockActuel($this->site->id));
        $this->assertSame(12.5, $this->cacao->fresh()->stockSur($this->site->id));
    }

    public function test_le_mouvement_de_stock_garde_les_decimales(): void
    {
        $mouvement = MouvementStock::create([
            'produit_id' => $this->cacao->id,
            'point_de_vente_id' => $this->site->id,
            'type_mouvement' => 'Entrée',
            'quantite' => 12.5,
            'stock_avant' => 0.25,
            'stock_apres' => 12.75,
        ]);

        $frais = $mouvement->fresh();

        $this->assertSame(12.5, $frais->quantite);
        $this->assertSame(0.25, $frais->stock_avant);
        $this->assertSame(12.75, $frais->stock_apres);
    }

    public function test_increment_et_decrement_travaillent_en_decimal(): void
    {
        $this->cacao->incrementStock($this->site->id, 12.5);
        $this->assertSame(12.5, $this->cacao->fresh()->stockActuel($this->site->id));

        $this->cacao->decrementStock($this->site->id, 0.25);
        $this->assertSame(12.25, $this->cacao->fresh()->stockActuel($this->site->id));
    }

    #[DataProvider('quantitesAcceptees')]
    public function test_la_regle_accepte_une_quantite_valide($valeur): void
    {
        $this->assertTrue(
            Validator::make(['q' => $valeur], ['q' => Quantite::physique()])->passes(),
            "La quantité {$valeur} aurait dû être acceptée."
        );
    }

    public static function quantitesAcceptees(): array
    {
        return [
            'un entier'          => ['12'],
            'un demi-kilo'       => ['0.5'],
            'douze kilos et demi'=> ['12.5'],
            'un gramme'          => ['0.001'],
        ];
    }

    #[DataProvider('quantitesRefusees')]
    public function test_la_regle_refuse_une_quantite_invalide($valeur, string $pourquoi): void
    {
        $this->assertFalse(
            Validator::make(['q' => $valeur], ['q' => Quantite::physique()])->passes(),
            $pourquoi
        );
    }

    public static function quantitesRefusees(): array
    {
        return [
            'zéro'     => ['0', "Une quantité nulle n'est pas une quantité."],
            'négative' => ['-3', "Le sens d'un mouvement est porté par son type, pas par le signe."],
            'quatre décimales' => ['12.5555',
                "La colonne n'en garde que trois : mieux vaut refuser que d'arrondir en silence."],
            'hors plafond' => ['99999999999999',
                "Sans borne, la base renverrait une erreur au lieu d'un message de formulaire."],
            'du texte' => ['beaucoup', 'Une quantité est un nombre.'],
        ];
    }

    public function test_la_regle_facultative_accepte_zero_mais_pas_le_negatif(): void
    {
        // Un stock d'ouverture a zero, un seuil d'alerte a zero : legitimes.
        $this->assertTrue(Validator::make(['q' => '0'], ['q' => Quantite::facultative()])->passes());
        $this->assertTrue(Validator::make(['q' => null], ['q' => Quantite::facultative()])->passes());
        $this->assertFalse(Validator::make(['q' => '-1'], ['q' => Quantite::facultative()])->passes());
    }
}
