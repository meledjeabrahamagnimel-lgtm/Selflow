<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\CodeJournal;
use App\Modules\Admin\Modeles\Consignation;
use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\Fournisseur;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Services\ConsignationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Les emballages consignés : la caisse de bouteilles qu'on prête.
 *
 * **Rien n'existait**, et c'est le quotidien d'un dépôt de boissons, d'un
 * distributeur de gaz, d'un grossiste en eau minérale — c'est-à-dire d'une part
 * considérable du commerce ivoirien.
 *
 * - **la consignation passait en vente, ou nulle part.** Une caisse consignée
 *   2 000 francs gonflait le chiffre d'affaires de 2 000 francs que
 *   l'entreprise devra rendre : ce n'est pas un produit, c'est **une dette** ;
 * - **rien ne disait ce qui est dehors** — combien de casiers dorment chez les
 *   clients, depuis quand, et chez qui ;
 * - **le non-retour ne se constatait pas** : la consignation gardée restait
 *   indéfiniment en dette au bilan alors qu'elle était devenue un produit.
 */
class ConsignationTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private PointDeVente $depot;
    private Client $client;
    private Fournisseur $brasserie;
    private Produit $casier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create(['nom' => 'Dépôt de boissons du Plateau']);

        $this->depot = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Dépôt central', 'ville' => 'Abidjan', 'commune' => 'Plateau',
        ]);

        CodeJournal::create([
            'entreprise_id' => $this->entreprise->id,
            'code' => 'OD', 'intitule' => 'Opérations diverses', 'type' => 'OD',
        ]);

        $this->client = Client::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Maquis Chez Tantie', 'compte_comptable' => '411001',
        ]);

        $this->brasserie = Fournisseur::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Brasserie de Côte d\'Ivoire', 'compte_comptable' => '401001',
        ]);

        $this->casier = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'EMB-001',
            'nom' => 'Casier de 24 bouteilles', 'type' => 'marchandise', 'unite' => 'casier',
            'prix_achat' => 0, 'prix_vente' => 0,
            'prix_consignation' => 2000, 'delai_retour_jours' => 21,
        ]);
    }

    private function consigner(float $quantite = 10, ?float $prix = null): ?Consignation
    {
        return ConsignationService::consigner(
            $this->entreprise->id, $this->depot->id, Consignation::AU_CLIENT,
            $this->client->id, $this->casier, $quantite, $prix,
            ['reference' => 'VTE-000042']
        );
    }

    private function solde(string $compte): float
    {
        return round(
            (float) EcritureComptable::where('compte_debit', $compte)->sum('debit')
            - (float) EcritureComptable::where('compte_credit', $compte)->sum('credit'),
            2
        );
    }

    // ══════════════ La consignation est une dette ══════════════

    public function test_la_consignation_au_client_credite_le_419400_et_non_une_vente(): void
    {
        // **Le defaut central.** Une caisse consignee 2 000 francs gonflait le
        // chiffre d'affaires de 2 000 francs que l'entreprise devra rendre.
        $this->consigner(10);

        $this->assertSame(-20000.0, $this->solde('419400'),
            'La consignation vit au passif : c\'est une dette, non un produit.');
        $this->assertSame(20000.0, $this->solde('411001'),
            'Le client doit la somme.');
        $this->assertSame(0, EcritureComptable::where('compte_credit', 'like', '70%')->count(),
            'Aucun produit n\'est constaté à la consignation.');
    }

    public function test_le_prix_vient_de_la_fiche_article(): void
    {
        // Un depot consigne toujours le casier au meme prix : le ressaisir a
        // chaque vente serait une source d'ecart.
        $consignation = $this->consigner(5);

        $this->assertSame(2000.0, $consignation->prix_consigne);
        $this->assertSame(10000.0, $consignation->montant);
    }

    public function test_le_delai_de_retour_vient_de_la_fiche_article(): void
    {
        $consignation = $this->consigner(5);

        $this->assertSame(now()->addDays(21)->toDateString(),
            $consignation->date_limite_retour->toDateString());
    }

    public function test_une_consignation_a_prix_nul_n_ecrit_rien(): void
    {
        // Elle encombrerait l'ecran de ce qui est dehors sans rien y apporter.
        $sansConsigne = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'EMB-002',
            'nom' => 'Carton perdu', 'type' => 'marchandise',
            'prix_achat' => 0, 'prix_vente' => 0,
        ]);

        $this->assertNull(ConsignationService::consigner(
            $this->entreprise->id, $this->depot->id, Consignation::AU_CLIENT,
            $this->client->id, $sansConsigne, 10
        ));

        $this->assertSame(0, Consignation::count());
        $this->assertSame(0, EcritureComptable::count());
    }

    public function test_un_sens_inconnu_est_refuse(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        ConsignationService::consigner(
            $this->entreprise->id, $this->depot->id, 'ailleurs',
            $this->client->id, $this->casier, 10
        );
    }

    // ══════════════ La reprise ══════════════

    public function test_la_reprise_au_meme_prix_eteint_la_dette(): void
    {
        $consignation = $this->consigner(10);

        ConsignationService::rendre($consignation, 10);

        $this->assertSame(0.0, $this->solde('419400'), 'La dette est éteinte.');
        $this->assertSame(0.0, $this->solde('411001'), 'Le client ne doit plus rien.');
        $this->assertSame(Consignation::RENDUE, $consignation->fresh()->statut);
    }

    public function test_les_retours_partiels_sont_la_regle(): void
    {
        // Un client rend huit casiers sur dix et garde les deux autres pour la
        // semaine suivante.
        $consignation = $this->consigner(10);

        ConsignationService::rendre($consignation, 8);

        $consignation->refresh();

        $this->assertSame(2.0, $consignation->quantiteDehors());
        $this->assertSame(4000.0, $consignation->resteDu());
        $this->assertSame(Consignation::EN_COURS, $consignation->statut);
    }

    public function test_plusieurs_retours_partiels_soldent_la_consignation(): void
    {
        $consignation = $this->consigner(10);

        ConsignationService::rendre($consignation, 6);
        ConsignationService::rendre($consignation->fresh(), 4);

        $this->assertSame(Consignation::RENDUE, $consignation->fresh()->statut);
        $this->assertSame(0.0, $this->solde('419400'));
    }

    public function test_une_reprise_a_prix_reduit_laisse_un_boni(): void
    {
        // C'est ce qui se pratique quand l'emballage revient abime : la
        // difference reste a l'entreprise, sur le compte 707400.
        $consignation = $this->consigner(10);

        ConsignationService::rendre($consignation, 10, 1500);

        $this->assertSame(0.0, $this->solde('419400'), 'La dette est éteinte en entier.');
        $this->assertSame(5000.0, $this->solde('411001'), 'Le client n\'est crédité que de 15 000.');
        $this->assertSame(-5000.0, $this->solde('707400'), 'Les 5 000 de différence sont un boni.');
    }

    public function test_reprendre_plus_que_ce_qui_est_dehors_est_refuse(): void
    {
        // Cela rembourserait ce qui n'a jamais ete consigne.
        $consignation = $this->consigner(10);
        ConsignationService::rendre($consignation, 7);

        $this->expectException(\InvalidArgumentException::class);

        ConsignationService::rendre($consignation->fresh(), 5);
    }

    public function test_rembourser_plus_que_le_prix_consigne_est_refuse(): void
    {
        // L'entreprise rendrait plus qu'elle n'a recu, et perdrait de l'argent
        // sans qu'aucune ligne ne le dise.
        $consignation = $this->consigner(10);

        $this->expectException(\InvalidArgumentException::class);

        ConsignationService::rendre($consignation, 10, 3000);
    }

    public function test_une_reprise_de_quantite_nulle_est_refusee(): void
    {
        $consignation = $this->consigner(10);

        $this->expectException(\InvalidArgumentException::class);

        ConsignationService::rendre($consignation, 0);
    }

    public function test_une_consignation_close_ne_se_reprend_plus(): void
    {
        $consignation = $this->consigner(10);
        ConsignationService::rendre($consignation, 10);

        $this->expectException(\LogicException::class);

        ConsignationService::rendre($consignation->fresh(), 1);
    }

    // ══════════════ Le non-retour ══════════════

    public function test_le_non_retour_transforme_la_dette_en_produit(): void
    {
        // La consignation gardee cesse d'etre une dette : le bilan cessait de
        // la porter alors que personne ne la reclamerait jamais.
        $consignation = $this->consigner(10);

        ConsignationService::constaterLeNonRetour($consignation);

        $this->assertSame(0.0, $this->solde('419400'), 'La dette est soldée.');
        $this->assertSame(-20000.0, $this->solde('707400'), 'Le boni est constaté.');
        $this->assertSame(Consignation::NON_RENDUE, $consignation->fresh()->statut);
    }

    public function test_le_non_retour_ne_porte_que_sur_ce_qui_reste_dehors(): void
    {
        $consignation = $this->consigner(10);
        ConsignationService::rendre($consignation, 6);

        ConsignationService::constaterLeNonRetour($consignation->fresh());

        // Quatre casiers a 2 000 : 8 000 de boni, pas 20 000.
        $this->assertSame(-8000.0, $this->solde('707400'));
        $this->assertSame(0.0, $this->solde('419400'));
    }

    public function test_une_consignation_close_ne_se_constate_pas_deux_fois(): void
    {
        $consignation = $this->consigner(10);
        ConsignationService::constaterLeNonRetour($consignation);

        $this->expectException(\LogicException::class);

        ConsignationService::constaterLeNonRetour($consignation->fresh());
    }

    public function test_aucune_facture_n_est_etablie_par_le_service(): void
    {
        // **Le non-retour est une vente**, soumise a la TVA et a la
        // certification de la plateforme : elle passe par l'ecran de vente
        // ordinaire, dont la conformite est acquise et gelee. Fabriquer ici une
        // seconde route vers la FNE remettrait cette conformite en jeu.
        $consignation = $this->consigner(10);
        ConsignationService::constaterLeNonRetour($consignation);

        $this->assertSame(0, \App\Modules\Admin\Modeles\Vente::count());
        $this->assertSame(0, EcritureComptable::where('compte_credit', 'like', '443%')->count());
    }

    // ══════════════ Le sens inverse : ce qu'un fournisseur nous consigne ══════════════

    public function test_la_consignation_du_fournisseur_est_une_creance(): void
    {
        // C'est l'inverse exact, et confondre les deux met le bilan a l'envers.
        ConsignationService::consigner(
            $this->entreprise->id, $this->depot->id, Consignation::DU_FOURNISSEUR,
            $this->brasserie->id, $this->casier, 50, 2000, ['reference' => 'ACH-000010']
        );

        $this->assertSame(100000.0, $this->solde('409400'),
            'Ce que nous avons versé est une créance, à l\'actif.');
        $this->assertSame(-100000.0, $this->solde('401001'),
            'Le fournisseur nous doit cette somme.');
    }

    public function test_ce_qu_on_ne_rend_pas_au_fournisseur_est_un_mali(): void
    {
        // Une charge, compte 622400 — et non un produit.
        $consignation = ConsignationService::consigner(
            $this->entreprise->id, $this->depot->id, Consignation::DU_FOURNISSEUR,
            $this->brasserie->id, $this->casier, 50, 2000
        );

        ConsignationService::constaterLeNonRetour($consignation);

        $this->assertSame(100000.0, $this->solde('622400'), 'La perte est une charge.');
        $this->assertSame(0.0, $this->solde('409400'), 'La créance est soldée.');
        $this->assertSame(0.0, $this->solde('707400'), 'Aucun boni : c\'est nous qui perdons.');
    }

    public function test_rendre_moins_cher_au_fournisseur_nous_coute(): void
    {
        $consignation = ConsignationService::consigner(
            $this->entreprise->id, $this->depot->id, Consignation::DU_FOURNISSEUR,
            $this->brasserie->id, $this->casier, 50, 2000
        );

        // Le fournisseur ne nous rembourse que 1 800 par casier.
        ConsignationService::rendre($consignation, 50, 1800);

        $this->assertSame(0.0, $this->solde('409400'));
        $this->assertSame(10000.0, $this->solde('622400'), 'Les 10 000 perdus sont un mali.');
    }

    // ══════════════ Ce qui est dehors ══════════════

    public function test_l_entreprise_sait_ce_qui_dort_chez_ses_clients(): void
    {
        // Un depot ne savait pas combien de casiers sont dehors, ni depuis
        // quand, ni chez qui.
        $this->consigner(10);
        $seconde = $this->consigner(5);
        ConsignationService::rendre($seconde, 2);

        $dehors = ConsignationService::ceQuiEstDehors($this->entreprise->id, Consignation::AU_CLIENT);

        $this->assertSame(13.0, $dehors['quantite']);
        $this->assertSame(26000.0, $dehors['montant']);
    }

    public function test_ce_qui_a_depasse_son_delai_est_signale(): void
    {
        $consignation = $this->consigner(10);
        $consignation->update(['date_limite_retour' => now()->subDay()->toDateString()]);

        $this->assertTrue($consignation->fresh()->estEnRetard());
        $this->assertSame(1,
            ConsignationService::ceQuiEstDehors($this->entreprise->id, Consignation::AU_CLIENT)['en_retard']);
    }

    public function test_une_consignation_sans_delai_n_expire_pas(): void
    {
        // C'est l'usage pour une bouteille de gaz, qu'un menage garde des
        // annees.
        $this->casier->update(['delai_retour_jours' => null]);

        $consignation = $this->consigner(10);

        $this->assertNull($consignation->date_limite_retour);
        $this->assertFalse($consignation->estEnRetard());
    }

    public function test_une_consignation_close_ne_compte_plus_comme_dehors(): void
    {
        $consignation = $this->consigner(10);
        ConsignationService::rendre($consignation, 10);

        $this->assertSame(0.0,
            ConsignationService::ceQuiEstDehors($this->entreprise->id, Consignation::AU_CLIENT)['quantite']);
    }

    public function test_ce_qui_est_dehors_ne_melange_pas_les_deux_sens(): void
    {
        $this->consigner(10);
        ConsignationService::consigner(
            $this->entreprise->id, $this->depot->id, Consignation::DU_FOURNISSEUR,
            $this->brasserie->id, $this->casier, 50, 2000
        );

        $this->assertSame(10.0,
            ConsignationService::ceQuiEstDehors($this->entreprise->id, Consignation::AU_CLIENT)['quantite']);
        $this->assertSame(50.0,
            ConsignationService::ceQuiEstDehors($this->entreprise->id, Consignation::DU_FOURNISSEUR)['quantite']);
    }

    public function test_ce_qui_est_dehors_ne_traverse_pas_les_entreprises(): void
    {
        // Ce qu'un concurrent a dehors dit combien il livre et a qui.
        $this->consigner(10);

        $autre = Entreprise::create(['nom' => 'Dépôt rival']);

        $this->assertSame(0.0,
            ConsignationService::ceQuiEstDehors($autre->id, Consignation::AU_CLIENT)['quantite']);
    }

    // ══════════════ Les écrans, et ce qu'un attaquant tenterait ══════════════

    private function connecte(): void
    {
        $this->entreprise->update([
            'regime_imposition' => 'RNI', 'adresse' => 'Plateau, Abidjan',
            'rccm' => 'CI-ABJ-2026-B-00321', 'ncc' => '2601234A',
            'gerant_fonction' => 'Gérant', 'secteur_activite' => ['Commerce'],
            'modules_actifs' => ['principal', 'stock', 'ventes', 'produits', 'tiers'],
        ]);

        $admin = \App\Modules\Authentification\Modeles\Utilisateur::create([
            'nom' => 'Sanogo', 'prenom' => 'Ibrahim', 'email' => 'ibrahim@exemple.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $this->entreprise->id,
            'point_de_vente_id' => $this->depot->id,
        ]);

        $this->actingAs($admin)->withSession(['point_de_vente_actif_id' => $this->depot->id]);
    }

    public function test_l_ecran_annonce_ce_qui_est_dehors(): void
    {
        $this->consigner(10);
        $this->connecte();

        $this->get(route('admin.consignations.index'))
            ->assertOk()
            ->assertSee('Maquis Chez Tantie')
            ->assertSee('Casier de 24 bouteilles')
            ->assertSee('419400');
    }

    public function test_l_ecran_consigne(): void
    {
        $this->connecte();

        $this->post(route('admin.consignations.enregistrer'), [
            'sens' => 'client', 'client_id' => $this->client->id,
            'produit_id' => $this->casier->id, 'quantite' => 12,
        ])->assertSessionHas('succes');

        $this->assertSame(24000.0, Consignation::first()->montant);
    }

    public function test_une_consignation_sans_tiers_est_refusee(): void
    {
        // Une consignation qui ne designe personne ne dit ni chez qui
        // l'emballage dort, ni a qui la dette est due.
        $this->connecte();

        $this->post(route('admin.consignations.enregistrer'), [
            'sens' => 'client', 'produit_id' => $this->casier->id, 'quantite' => 12,
        ])->assertSessionHasErrors('client_id');

        $this->assertSame(0, Consignation::count());
    }

    public function test_une_consignation_sans_emballage_ni_nom_est_refusee(): void
    {
        // La ligne serait illisible sur l'ecran de ce qui est dehors.
        $this->connecte();

        $this->post(route('admin.consignations.enregistrer'), [
            'sens' => 'client', 'client_id' => $this->client->id, 'quantite' => 12,
        ])->assertSessionHas('erreur');

        $this->assertSame(0, Consignation::count());
    }

    public function test_l_ecran_refuse_de_rembourser_plus_que_le_prix_consigne(): void
    {
        // Par la route directement : l'entreprise rendrait plus qu'elle n'a
        // recu, et perdrait de l'argent sans qu'aucune ligne ne le dise.
        $consignation = $this->consigner(10);
        $this->connecte();

        $this->post(route('admin.consignations.rendre', $consignation), [
            'quantite' => 10, 'prix_de_reprise' => 50000,
        ])->assertSessionHas('erreur');

        $this->assertSame(0.0, $consignation->fresh()->montant_rembourse);
        $this->assertSame(-20000.0, $this->solde('419400'), 'La dette est intacte.');
    }

    public function test_l_ecran_refuse_de_reprendre_plus_que_ce_qui_est_dehors(): void
    {
        $consignation = $this->consigner(10);
        $this->connecte();

        $this->post(route('admin.consignations.rendre', $consignation), ['quantite' => 40])
            ->assertSessionHas('erreur');

        $this->assertSame(0.0, $consignation->fresh()->quantite_rendue);
    }

    public function test_la_consignation_d_une_autre_entreprise_est_fermee(): void
    {
        // Ce qu'un concurrent a dehors dit combien il livre et a qui.
        $this->connecte();

        $autre = Entreprise::create(['nom' => 'Dépôt rival']);
        $sonDepot = PointDeVente::create([
            'entreprise_id' => $autre->id,
            'nom' => 'Dépôt rival', 'ville' => 'Abidjan', 'commune' => 'Yopougon',
        ]);
        $sonClient = Client::create(['entreprise_id' => $autre->id, 'nom' => 'Client rival']);

        $sienne = ConsignationService::consigner(
            $autre->id, $sonDepot->id, Consignation::AU_CLIENT,
            $sonClient->id, null, 30, 2000, ['designation' => 'Casier rival']
        );

        $this->post(route('admin.consignations.rendre', $sienne), ['quantite' => 30])
            ->assertForbidden();
        $this->post(route('admin.consignations.non_retour', $sienne))->assertForbidden();

        $this->get(route('admin.consignations.index'))->assertOk()->assertDontSee('Casier rival');

        $this->assertSame(0.0, $sienne->fresh()->quantite_rendue);
        $this->assertSame(Consignation::EN_COURS, $sienne->fresh()->statut);
    }

    // ══════════════ L'équilibre ══════════════

    public function test_toutes_les_ecritures_sont_equilibrees(): void
    {
        $a = $this->consigner(10);
        ConsignationService::rendre($a, 6, 1800);

        $b = $this->consigner(4);
        ConsignationService::constaterLeNonRetour($b);

        $c = ConsignationService::consigner(
            $this->entreprise->id, $this->depot->id, Consignation::DU_FOURNISSEUR,
            $this->brasserie->id, $this->casier, 20, 2000
        );
        ConsignationService::rendre($c, 20, 1900);

        $this->assertEqualsWithDelta(
            EcritureComptable::sum('debit'), EcritureComptable::sum('credit'), 0.01
        );
    }

    public function test_chaque_ecriture_ne_porte_qu_un_compte(): void
    {
        // C'est la convention du projet et celle de Comptaflow, dont le point
        // d'entree retient `compte_debit` s'il est present et ignore
        // `compte_credit`.
        $consignation = $this->consigner(10);
        ConsignationService::rendre($consignation, 10, 1500);

        foreach (EcritureComptable::all() as $ecriture) {
            $this->assertTrue(
                ($ecriture->compte_debit === null) !== ($ecriture->compte_credit === null),
                'Une écriture porte un compte, jamais deux.'
            );
        }
    }
}
