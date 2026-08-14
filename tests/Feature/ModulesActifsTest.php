<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Les modules par combinaison — lot 8.4.
 *
 * `VerifierModulesActifs` ferme les écrans d'un module que l'entreprise n'a
 * pas souscrit, mais n'était couvert par aucun test : rien ne garantissait
 * que le blindage marchait, ni qu'une entreprise à modules réduits pouvait
 * quand même mener un cycle complet avec ce qu'elle a.
 *
 * Deux choses sont vérifiées ici :
 *
 * - **le blindage lui-même** — un module absent de `modules_actifs` ferme
 *   ses écrans (403 — Forbidden, accès interdit), un module présent les
 *   ouvre ;
 * - **un scénario de bout en bout pour une combinaison réduite** — une
 *   entreprise de services, sans stock ni achats, qui vend un service et
 *   voit son écriture comptable posée sans qu'aucun mouvement de stock ne
 *   soit créé. Le cycle complet vente + stock + achats + comptabilité
 *   ("Commerce") est déjà couvert par `CycleStockTest` et `BalanceTest` ;
 *   il n'est pas dupliqué ici.
 */
class ModulesActifsTest extends TestCase
{
    use RefreshDatabase;

    private function creerEntreprise(array $modules): Entreprise
    {
        return Entreprise::create([
            'nom'               => 'Cabinet Konan Conseil',
            'regime_imposition' => 'RNI',
            'adresse'           => 'Cocody, Abidjan',
            'rccm'              => 'CI-ABJ-2026-B-00042',
            'ncc'               => '2604242A',
            'gerant_fonction'   => 'Gérant',
            'secteur_activite'  => ['Services'],
            'modules_actifs'    => $modules,
        ]);
    }

    private function creerAdmin(Entreprise $entreprise, PointDeVente $pdv): Utilisateur
    {
        return Utilisateur::create([
            'nom' => 'Konan', 'prenom' => 'Aya', 'email' => 'aya@exemple.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $entreprise->id,
            'point_de_vente_id' => $pdv->id,
        ]);
    }

    // ══════════════ Le blindage lui-même ══════════════

    public function test_un_module_absent_ferme_ses_ecrans(): void
    {
        $entreprise = $this->creerEntreprise(['principal', 'comptabilite', 'produits', 'tiers', 'points_de_vente']);
        $pdv = PointDeVente::create(['entreprise_id' => $entreprise->id, 'nom' => 'Cabinet', 'ville' => 'Abidjan', 'commune' => 'Cocody']);
        $admin = $this->creerAdmin($entreprise, $pdv);

        $this->actingAs($admin)
            ->withSession(['point_de_vente_actif_id' => $pdv->id])
            ->get(route('admin.stock.index'))
            ->assertForbidden();

        $this->actingAs($admin)
            ->withSession(['point_de_vente_actif_id' => $pdv->id])
            ->get(route('admin.achats.nouveau'))
            ->assertForbidden();
    }

    public function test_un_module_present_ouvre_ses_ecrans(): void
    {
        $entreprise = $this->creerEntreprise(['principal', 'ventes', 'stock', 'achats', 'comptabilite', 'produits', 'tiers', 'points_de_vente']);
        $pdv = PointDeVente::create(['entreprise_id' => $entreprise->id, 'nom' => 'Boutique', 'ville' => 'Abidjan', 'commune' => 'Cocody']);
        $admin = $this->creerAdmin($entreprise, $pdv);

        $this->actingAs($admin)
            ->withSession(['point_de_vente_actif_id' => $pdv->id])
            ->get(route('admin.stock.index'))
            ->assertOk();

        $this->actingAs($admin)
            ->withSession(['point_de_vente_actif_id' => $pdv->id])
            ->get(route('admin.achats.nouveau'))
            ->assertOk();
    }

    public function test_le_superadmin_traverse_le_blindage_des_modules(): void
    {
        // Le superadmin n'appartient a aucune entreprise : il ne peut donc
        // avoir de `modules_actifs`. C'est `role:admin`, en amont, qui lui
        // ferme `/admin/*` — le blindage des modules lui-meme le laisse
        // passer, ce que ce test verifie au niveau du middleware.
        $superadmin = Utilisateur::create([
            'nom' => 'Plateforme', 'prenom' => 'Super', 'email' => 'super-modules@selflow.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'superadmin', 'entreprise_id' => null,
            'habilitations' => \App\Modules\Authentification\Regles\Habilitations::PLATEFORME,
        ]);

        $this->actingAs($superadmin);

        $middleware = new \App\Modules\Authentification\Middleware\VerifierModulesActifs();
        $requete = \Illuminate\Http\Request::create('/admin/stock', 'GET');

        $reponse = $middleware->handle($requete, fn ($r) => new \Illuminate\Http\Response('ok'), 'stock');

        $this->assertSame('ok', $reponse->getContent());
    }

    // ══════════════ Le scénario "Services" — sans stock ni achats ══════════════

    public function test_une_entreprise_de_services_vend_sans_toucher_au_stock(): void
    {
        Queue::fake();

        // Ni stock, ni achats : le cabinet ne gère aucune marchandise.
        $entreprise = $this->creerEntreprise(['principal', 'ventes', 'comptabilite', 'produits', 'tiers', 'points_de_vente']);
        $pdv = PointDeVente::create(['entreprise_id' => $entreprise->id, 'nom' => 'Cabinet', 'ville' => 'Abidjan', 'commune' => 'Cocody']);
        $admin = $this->creerAdmin($entreprise, $pdv);

        $prestation = Produit::create([
            'entreprise_id' => $entreprise->id, 'reference' => 'SRV-001',
            'nom' => 'Consultation fiscale', 'type' => 'service', 'unite' => 'heure',
            'prix_achat' => 0, 'prix_vente' => 50000, 'taux_tva' => 18,
        ]);

        $client = Client::create(['entreprise_id' => $entreprise->id, 'nom' => 'Client Cabinet']);

        $mouvementsAvant = MouvementStock::count();
        $ecrituresAvant  = EcritureComptable::count();

        $reponse = $this->actingAs($admin)
            ->withSession(['point_de_vente_actif_id' => $pdv->id])
            ->post(route('admin.ventes.enregistrer'), [
                'etape'         => 'Facture',
                'client_id'     => $client->id,
                'mode_paiement' => 'Espèces',
                'articles'      => [[
                    'produit_id' => $prestation->id,
                    'quantite'   => 2,
                    'unite'      => 'heure',
                ]],
            ]);

        $reponse->assertSessionDoesntHaveErrors();

        // Un service ne produit aucun mouvement de stock : `estStockable()`
        // renvoie faux pour ce type, quel que soit l'état du module.
        $this->assertSame($mouvementsAvant, MouvementStock::count(),
            'La vente d’un service ne doit créer aucun mouvement de stock.');

        // La comptabilité, elle, suit la vente — le module `comptabilite`
        // est actif et n’a pas besoin du module `stock` pour fonctionner.
        $this->assertGreaterThan($ecrituresAvant, EcritureComptable::count(),
            'La vente d’un service doit tout de même poser une écriture comptable.');

        // Les écrans qui n'ont pas de sens ici restent fermés.
        $this->actingAs($admin)
            ->withSession(['point_de_vente_actif_id' => $pdv->id])
            ->get(route('admin.stock.index'))
            ->assertForbidden();
    }
}
