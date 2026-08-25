<?php

namespace Tests\Feature;

use App\Jobs\NormaliserFactureFne;
use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Vente;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Quand la pièce part à la DGI.
 *
 * La normalisation partait systématiquement à l'enregistrement, sans que
 * personne ne puisse s'y opposer. C'est le bon comportement pour une caisse
 * qui tourne, mais pas pour une entreprise qui vérifie ses pièces avant de les
 * certifier — **et une pièce certifiée ne se reprend pas**.
 *
 * Deux réglages, séparés parce que les deux usages le sont : une boutique peut
 * vouloir vérifier ses factures et laisser partir ses tickets de caisse tout
 * seuls. Le défaut reste l'automatique, pour ne rien changer aux entreprises
 * déjà en service sans qu'elles l'aient demandé.
 */
class NormalisationAutomatiqueTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private Utilisateur $admin;
    private PointDeVente $magasin;
    private Produit $article;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->entreprise = Entreprise::create([
            'nom'               => 'Quincaillerie du Plateau',
            'regime_imposition' => 'RNI',
            'adresse'           => 'Plateau, Abidjan',
            'rccm'              => 'CI-ABJ-2026-B-00321',
            'ncc'               => '2603210A',
            'gerant_fonction'   => 'Gérant',
            'secteur_activite'  => ['Commerce'],
            'modules_actifs'    => ['principal', 'ventes', 'achats', 'stock', 'produits', 'tiers'],
        ]);

        $this->magasin = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Magasin central', 'ville' => 'Abidjan', 'commune' => 'Plateau',
        ]);

        $this->admin = Utilisateur::create([
            'nom' => 'Bamba', 'prenom' => 'Salif', 'email' => 'salif-norm@exemple.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $this->entreprise->id,
            'point_de_vente_id' => $this->magasin->id,
        ]);

        $this->article = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'CIM-001',
            'nom' => 'Ciment CPJ 45', 'type' => 'service', 'unite' => 'sac',
            'prix_achat' => 5000, 'prix_vente' => 6500, 'taux_tva' => 18,
        ]);

        $this->client = Client::create([
            'entreprise_id' => $this->entreprise->id, 'nom' => 'Entreprise Konan BTP',
        ]);

        $this->actingAs($this->admin)
            ->withSession(['point_de_vente_actif_id' => $this->magasin->id]);
    }

    private function emettre(string $typePiece = Vente::TYPE_FACTURE): void
    {
        $this->post(route('admin.ventes.enregistrer'), [
            'etape'         => 'Facture',
            'type_piece'    => $typePiece,
            'client_id'     => $this->client->id,
            'mode_paiement' => 'Espèces',
            'articles'      => [[
                'produit_id' => $this->article->id,
                'quantite'   => 2,
                'unite'      => 'sac',
            ]],
        ])->assertSessionDoesntHaveErrors();
    }

    // ══════════════ Le défaut : automatique ══════════════

    public function test_par_defaut_la_facture_part_des_son_emission(): void
    {
        $this->emettre();

        Queue::assertPushed(NormaliserFactureFne::class);
    }

    // ══════════════ Le réglage manuel ══════════════

    public function test_en_manuel_la_facture_ne_part_pas_toute_seule(): void
    {
        $this->entreprise->update(['normalisation_auto_factures' => false]);

        $this->emettre();

        Queue::assertNotPushed(NormaliserFactureFne::class);

        // Elle reste enregistrée, et normalisable à la main.
        $this->assertSame(1, Vente::count());
        $this->assertFalse((bool) Vente::first()->normalise);
    }

    public function test_en_manuel_le_recu_ne_part_pas_tout_seul(): void
    {
        $this->entreprise->update(['normalisation_auto_recus' => false]);

        $this->emettre(Vente::TYPE_RECU);

        Queue::assertNotPushed(NormaliserFactureFne::class);
    }

    // ══════════════ Les deux réglages sont indépendants ══════════════

    public function test_le_reglage_des_recus_ne_retient_pas_les_factures(): void
    {
        // Le cas d'usage qui justifie deux réglages plutôt qu'un : vérifier ses
        // factures à la main, laisser partir ses tickets de caisse.
        $this->entreprise->update([
            'normalisation_auto_factures' => true,
            'normalisation_auto_recus'    => false,
        ]);

        $this->emettre(Vente::TYPE_FACTURE);

        Queue::assertPushed(NormaliserFactureFne::class);
    }

    public function test_le_reglage_des_factures_ne_retient_pas_les_recus(): void
    {
        $this->entreprise->update([
            'normalisation_auto_factures' => false,
            'normalisation_auto_recus'    => true,
        ]);

        $this->emettre(Vente::TYPE_RECU);

        Queue::assertPushed(NormaliserFactureFne::class);
    }

    // ══════════════ Le réglage se pose depuis l'écran ══════════════

    public function test_le_reglage_se_decoche_depuis_les_parametres(): void
    {
        // Une case non cochée n'est pas transmise : sans lecture explicite,
        // décocher n'aurait aucun effet.
        $this->put(route('admin.entreprise.parametres.enregistrer'), [
            'nom'               => $this->entreprise->nom,
            'regime_imposition' => 'RNI',
            'adresse'           => 'Plateau, Abidjan',
            'rccm'              => 'CI-ABJ-2026-B-00321',
            'ncc'               => '2603210A',
            'gerant_fonction'   => 'Gérant',
            'secteurs_activite' => ['Commerce'],
            // `normalisation_auto_factures` volontairement absent : décoché.
            'normalisation_auto_recus' => '1',
        ]);

        $entreprise = $this->entreprise->fresh();

        $this->assertFalse((bool) $entreprise->normalisation_auto_factures);
        $this->assertTrue((bool) $entreprise->normalisation_auto_recus);
    }

    // ══════════════ Ce que l'écran en dit ══════════════

    public function test_l_ecran_de_vente_n_annonce_plus_une_certification_suspendue(): void
    {
        // L'encadré annonçait une certification « suspendue tant que la FNE
        // n'a pas fourni les champs de mappage du reçu normalisé
        // électronique ». C'était vrai avant la refonte du reçu : depuis, il
        // emprunte la porte de la facture, et rien ne le retient. Le texte
        // disait à l'utilisateur que ses reçus n'étaient pas certifiés alors
        // qu'ils l'étaient.
        $corps = $this->get(route('admin.ventes.nouvelle'))->assertOk()->getContent();

        $this->assertStringNotContainsString('Normalisation RNE en attente', $corps);
        $this->assertStringNotContainsString('champs de mappage du reçu', $corps);
        $this->assertStringContainsString('Le reçu se certifie comme une facture', $corps);
    }

    public function test_l_ecran_dit_que_le_recu_part_des_son_emission(): void
    {
        $this->entreprise->update(['normalisation_auto_recus' => true]);

        $corps = $this->get(route('admin.ventes.nouvelle'))->assertOk()->getContent();

        $this->assertStringContainsString('Il part à la certification dès son émission', $corps);
    }

    public function test_l_ecran_dit_que_le_recu_attend_quand_le_reglage_est_decoche(): void
    {
        $this->entreprise->update(['normalisation_auto_recus' => false]);

        $corps = $this->get(route('admin.ventes.nouvelle'))->assertOk()->getContent();

        $this->assertStringContainsString('est décochée dans vos', $corps);
        $this->assertStringNotContainsString('Il part à la certification dès son émission', $corps);
    }
}
