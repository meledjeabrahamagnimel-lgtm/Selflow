<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Categorie;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PlanComptable;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Services\SouscriptionProfilService;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Souscription à un profil d'activité.
 *
 * C'est le moment où le secteur cesse d'être une étiquette. Choisir
 * « Commercial » ne chargeait rien : l'utilisateur repartait avec un catalogue
 * vide. Ces tests fixent ce qu'il reçoit, et ce qu'on ne doit jamais lui
 * reprendre.
 */
class SouscriptionProfilTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentielSeeder::class);
        $this->entreprise = Entreprise::create(['nom' => 'Boutique du carrefour']);
    }

    public function test_souscrire_charge_les_familles_les_articles_et_les_comptes(): void
    {
        $bilan = SouscriptionProfilService::souscrire($this->entreprise, ['boutique_quartier']);

        $this->assertSame(1, $bilan['profils']);
        $this->assertSame(4, $bilan['familles']);
        $this->assertSame(17, $bilan['articles']);

        $this->assertSame(4, Categorie::where('entreprise_id', $this->entreprise->id)->count());
        $this->assertSame(17, Produit::where('entreprise_id', $this->entreprise->id)->count());

        // Le trousseau est pose au passage : sans plan, un article s'imputerait
        // sur des comptes absents.
        $this->assertGreaterThanOrEqual(34, PlanComptable::where('entreprise_id', $this->entreprise->id)->count());
    }

    public function test_un_article_arrive_avec_son_type_et_ses_comptes(): void
    {
        SouscriptionProfilService::souscrire($this->entreprise, ['boutique_quartier']);

        $riz = Produit::where('entreprise_id', $this->entreprise->id)
            ->where('reference', 'BOUQ-VIV-001')
            ->firstOrFail();

        $this->assertSame('Riz sac 25 kg', $riz->nom);
        $this->assertSame('marchandise', $riz->type);
        $this->assertSame('sac', $riz->unite);
        $this->assertSame('701000', $riz->compte_vente);
        $this->assertSame('601000', $riz->compte_achat);
        $this->assertSame('Vivres et alimentation', $riz->categorieRelation->nom);

        // Les prix restent a saisir : le classeur les laisse volontairement
        // vides, ils varient selon la zone et la periode.
        $this->assertEquals(0, $riz->prix_achat);
        $this->assertEquals(0, $riz->prix_vente);
    }

    public function test_un_service_arrive_comme_service_donc_sans_stock(): void
    {
        // Un cabinet comptable ne doit jamais lire « rupture » sur une mission.
        SouscriptionProfilService::souscrire($this->entreprise, ['cabinet_comptable']);

        $prestations = Produit::where('entreprise_id', $this->entreprise->id)->get();

        $this->assertGreaterThan(0, $prestations->count());

        // Aucun article d'un cabinet ne se compte : ni les missions, qui sont
        // des services, ni les frais de greffe, qui sont des achats non
        // stockes. C'est ce qui evitera de leur creer une fiche de stock.
        foreach ($prestations as $prestation) {
            $this->assertFalse(
                $prestation->estStockable(),
                "« {$prestation->nom} » ({$prestation->type}) ne devrait pas se stocker."
            );
        }

        $this->assertTrue($prestations->contains(fn ($p) => $p->type === 'service'));
    }

    public function test_les_comptes_de_famille_portent_un_intitule_lisible(): void
    {
        SouscriptionProfilService::souscrire($this->entreprise, ['boutique_quartier']);

        // 311000 vient de la famille Vivres : son intitule doit dire de quoi il
        // s'agit, pas repeter « Marchandises A » du plan brut.
        $stock = PlanComptable::where('entreprise_id', $this->entreprise->id)
            ->where('numero', '311000')
            ->firstOrFail();

        $this->assertStringContainsString('Vivres et alimentation', $stock->libelle);
    }

    public function test_les_modules_du_profil_s_ouvrent(): void
    {
        $bilan = SouscriptionProfilService::souscrire($this->entreprise, ['boulangerie']);

        $this->assertContains('stock', $bilan['modules']);
        $this->assertContains('production', $bilan['modules']);
        $this->assertContains('ventes', $bilan['modules']);

        $this->assertTrue($this->entreprise->fresh()->moduleEstActif('production'));
    }

    public function test_une_activite_mixte_cumule_sans_doubler(): void
    {
        // Une quincaillerie qui livre des chantiers souscrit aux deux profils.
        $bilan = SouscriptionProfilService::souscrire($this->entreprise, ['quincaillerie', 'maconnerie']);

        $this->assertSame(2, $bilan['profils']);
        $this->assertContains('chantiers', $bilan['modules']);

        // Aucune categorie en double, et aucun prefixe en collision malgre des
        // codes de famille qui peuvent se repeter d'un profil a l'autre.
        $categories = Categorie::where('entreprise_id', $this->entreprise->id)->get();
        $this->assertSame($categories->count(), $categories->pluck('nom')->unique()->count());
        $this->assertSame($categories->count(), $categories->pluck('prefixe')->unique()->count());
    }

    public function test_souscrire_deux_fois_au_meme_profil_ne_recree_rien(): void
    {
        SouscriptionProfilService::souscrire($this->entreprise, ['boutique_quartier']);
        $second = SouscriptionProfilService::souscrire($this->entreprise, ['boutique_quartier']);

        $this->assertSame(0, $second['profils']);
        $this->assertSame(17, Produit::where('entreprise_id', $this->entreprise->id)->count());
    }

    public function test_ce_que_l_utilisateur_a_modifie_survit_a_une_nouvelle_souscription(): void
    {
        SouscriptionProfilService::souscrire($this->entreprise, ['boutique_quartier']);

        Produit::where('entreprise_id', $this->entreprise->id)
            ->where('reference', 'BOUQ-VIV-001')
            ->update(['nom' => 'Riz parfumé Dinor 25 kg', 'prix_vente' => 17500]);

        SouscriptionProfilService::souscrire($this->entreprise, ['depot_boissons']);

        $riz = Produit::where('entreprise_id', $this->entreprise->id)
            ->where('reference', 'BOUQ-VIV-001')
            ->firstOrFail();

        $this->assertSame('Riz parfumé Dinor 25 kg', $riz->nom);
        $this->assertEquals(17500, $riz->prix_vente);
    }

    public function test_un_profil_inconnu_est_refuse_sans_rien_creer(): void
    {
        // Les codes viennent d'un formulaire : rien ne doit se creer sur la foi
        // d'une valeur envoyee par le navigateur.
        try {
            SouscriptionProfilService::souscrire($this->entreprise, ['boutique_quartier', 'profil_invente']);
            $this->fail('Un profil inconnu aurait dû être refusé.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('profil_invente', $e->getMessage());
        }

        $this->assertSame(0, Produit::where('entreprise_id', $this->entreprise->id)->count());
        $this->assertSame(0, $this->entreprise->profils()->count());
    }

    public function test_un_module_non_autorise_ne_s_ouvre_pas(): void
    {
        // Le superadmin ouvre tout par defaut, mais s'il restreint, la
        // souscription ne peut pas passer outre.
        $this->entreprise->update([
            'modules_autorises' => ['principal', 'ventes', 'achats', 'tiers', 'produits', 'rapports', 'comptabilite'],
        ]);

        $bilan = SouscriptionProfilService::souscrire($this->entreprise, ['boulangerie']);

        $this->assertNotContains('production', $bilan['modules']);
        $this->assertNotContains('stock', $bilan['modules']);
        $this->assertFalse($this->entreprise->fresh()->moduleEstActif('production'));
    }

    public function test_sans_restriction_tous_les_modules_sont_autorises(): void
    {
        $this->assertSame(
            Entreprise::TOUS_LES_MODULES,
            $this->entreprise->modulesAutorises()
        );
    }

    public function test_le_catalogue_reste_cloisonne_entre_entreprises(): void
    {
        $voisine = Entreprise::create(['nom' => 'Quincaillerie du plateau']);

        SouscriptionProfilService::souscrire($this->entreprise, ['boutique_quartier']);
        SouscriptionProfilService::souscrire($voisine, ['quincaillerie']);

        $this->assertSame(17, Produit::where('entreprise_id', $this->entreprise->id)->count());
        $this->assertSame(12, Produit::where('entreprise_id', $voisine->id)->count());

        // La reference du referentiel est la meme, mais chaque entreprise a la
        // sienne : aucune contrainte globale ne fait echouer la seconde.
        $this->assertSame(0, Produit::where('entreprise_id', $voisine->id)
            ->where('reference', 'BOUQ-VIV-001')->count());
    }
}
