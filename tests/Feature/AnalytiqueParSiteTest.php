<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\Operation;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Services\AnalytiqueService;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Le résultat, site par site.
 *
 * `point_de_vente_id` était porté par chaque écriture depuis toujours, et
 * transmis à Comptaflow, qui l'ignore. **Aucun écran de Selflow ne s'en
 * servait** : la balance et le grand livre savent se restreindre à un site,
 * mais rien ne les mettait côte à côte — or c'est la seule question qui compte
 * quand on en tient plusieurs.
 *
 * Ce que ces épreuves gardent :
 *
 *  - le sens des colonnes : un produit vit au crédit, une charge au débit, et
 *    l'avoir qui écrit dans l'autre sens **retranche** au lieu de gonfler ;
 *  - les classes retenues : 6 et 7 seulement. Une ligne de trésorerie ou de
 *    tiers n'entre pas dans un résultat ;
 *  - les écritures sans site, qui sont dites plutôt que tues : les passer sous
 *    silence ferait que la somme des sites ne vaudrait pas le total ;
 *  - le cloisonnement : le site d'un concurrent n'apparaît pas.
 */
class AnalytiqueParSiteTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private PointDeVente $plateau;
    private PointDeVente $yopougon;
    private Utilisateur $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create(['nom' => 'Quincaillerie du Plateau']);

        $this->plateau = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Boutique du Plateau', 'ville' => 'Abidjan', 'commune' => 'Plateau',
        ]);

        $this->yopougon = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Dépôt de Yopougon', 'ville' => 'Abidjan', 'commune' => 'Yopougon',
        ]);

        $this->admin = Utilisateur::create([
            'entreprise_id' => $this->entreprise->id,
            'point_de_vente_id' => $this->plateau->id,
            'nom' => 'Kouadio', 'prenom' => 'Lewis',
            'email' => 'lewis@quincaillerie.ci',
            'password' => Hash::make('motdepasse-de-test'),
            'role' => 'admin', 'statut' => 'actif',
        ]);
    }

    public function test_chaque_site_porte_ses_produits_et_ses_charges(): void
    {
        $this->produit($this->plateau, 100000);
        $this->charge($this->plateau, 60000);

        $this->produit($this->yopougon, 40000);
        $this->charge($this->yopougon, 55000);

        $sites = collect(AnalytiqueService::parSite($this->entreprise->id)['sites'])->keyBy('nom');

        $this->assertSame(100000.0, $sites['Boutique du Plateau']['produits']);
        $this->assertSame(60000.0, $sites['Boutique du Plateau']['charges']);
        $this->assertSame(40000.0, $sites['Dépôt de Yopougon']['produits']);
        $this->assertSame(55000.0, $sites['Dépôt de Yopougon']['charges']);
    }

    public function test_le_resultat_se_calcule_par_site_et_peut_etre_negatif(): void
    {
        $this->produit($this->plateau, 100000);
        $this->charge($this->plateau, 60000);

        $this->produit($this->yopougon, 40000);
        $this->charge($this->yopougon, 55000);

        $sites = collect(AnalytiqueService::parSite($this->entreprise->id)['sites'])->keyBy('nom');

        $this->assertSame(40000.0, $sites['Boutique du Plateau']['resultat']);
        // Un dépôt qui coûte plus qu'il ne rapporte doit se voir.
        $this->assertSame(-15000.0, $sites['Dépôt de Yopougon']['resultat']);
    }

    /**
     * Un avoir débite un compte de produit. Retenir le seul crédit ferait
     * apparaître la vente annulée comme si elle avait eu lieu.
     */
    public function test_un_avoir_retranche_au_produit_au_lieu_de_gonfler_la_charge(): void
    {
        $this->produit($this->plateau, 100000);
        $this->ecriture($this->plateau, compteDebit: '701000', debit: 30000);

        $sites = collect(AnalytiqueService::parSite($this->entreprise->id)['sites'])->keyBy('nom');

        $this->assertSame(70000.0, $sites['Boutique du Plateau']['produits']);
        $this->assertSame(0.0, $sites['Boutique du Plateau']['charges']);
    }

    /**
     * Le résultat ne se lit pas sur le bilan : une ligne de caisse, de client
     * ou de TVA n'y entre pas.
     */
    public function test_les_comptes_de_bilan_n_entrent_pas_dans_le_resultat(): void
    {
        $this->ecriture($this->plateau, compteDebit: '571000', debit: 500000);
        $this->ecriture($this->plateau, compteCredit: '411000', credit: 500000);
        $this->ecriture($this->plateau, compteCredit: '443100', credit: 90000);

        $resultat = AnalytiqueService::parSite($this->entreprise->id);

        $this->assertSame(0.0, $resultat['totaux']['produits']);
        $this->assertSame(0.0, $resultat['totaux']['charges']);
        $this->assertSame(0, $resultat['totaux']['ecritures']);
    }

    public function test_le_total_de_l_entreprise_vaut_la_somme_des_sites(): void
    {
        $this->produit($this->plateau, 100000);
        $this->charge($this->plateau, 60000);
        $this->produit($this->yopougon, 40000);

        $resultat = AnalytiqueService::parSite($this->entreprise->id);

        $this->assertSame(140000.0, $resultat['totaux']['produits']);
        $this->assertSame(60000.0, $resultat['totaux']['charges']);
        $this->assertSame(80000.0, $resultat['totaux']['resultat']);
    }

    // ── Les écritures sans site ─────────────────────────────────────

    public function test_sans_ecriture_orpheline_rien_n_est_signale(): void
    {
        $this->produit($this->plateau, 100000);

        $this->assertNull(AnalytiqueService::parSite($this->entreprise->id)['non_ventile']);
    }

    /**
     * Les taire ferait que la somme des sites ne vaudrait pas le résultat de
     * l'entreprise, sans que rien ne l'explique.
     */
    public function test_une_ecriture_sans_site_est_dite_et_comptee_au_total(): void
    {
        $this->produit($this->plateau, 100000);
        $this->ecriture(null, compteCredit: '706000', credit: 25000);

        $resultat = AnalytiqueService::parSite($this->entreprise->id);

        $this->assertNotNull($resultat['non_ventile']);
        $this->assertSame('Sans site', $resultat['non_ventile']['nom']);
        $this->assertSame(25000.0, $resultat['non_ventile']['produits']);
        $this->assertSame(125000.0, $resultat['totaux']['produits']);
    }

    // ── La période ──────────────────────────────────────────────────

    public function test_la_periode_borne_ce_qui_est_compte(): void
    {
        $this->produit($this->plateau, 100000, '2026-03-15');
        $this->produit($this->plateau, 70000, '2026-08-15');

        $mars = AnalytiqueService::parSite($this->entreprise->id, '2026-03-01', '2026-03-31');

        $this->assertSame(100000.0, $mars['totaux']['produits']);
    }

    // ── Le cloisonnement ────────────────────────────────────────────

    public function test_le_site_d_une_autre_entreprise_n_apparait_pas(): void
    {
        $voisine = Entreprise::create(['nom' => 'Voisine SARL']);
        $siteVoisin = PointDeVente::create([
            'entreprise_id' => $voisine->id,
            'nom' => 'Magasin du voisin', 'ville' => 'Abidjan', 'commune' => 'Cocody',
        ]);

        EcritureComptable::create([
            'entreprise_id'     => $voisine->id,
            'point_de_vente_id' => $siteVoisin->id,
            'date_ecriture'     => now()->toDateString(),
            'libelle'            => 'Vente du voisin',
            'reference_document' => 'REF',
            'code_journal'       => 'VTE',
            'compte_credit'     => '701000',
            'debit'             => 0,
            'credit'            => 999999,
        ]);

        $resultat = AnalytiqueService::parSite($this->entreprise->id);

        $noms = array_column($resultat['sites'], 'nom');
        $this->assertNotContains('Magasin du voisin', $noms);
        $this->assertSame(0.0, $resultat['totaux']['produits']);
    }

    // ── L'écran ─────────────────────────────────────────────────────

    public function test_l_ecran_affiche_chaque_site_et_son_resultat(): void
    {
        $this->produit($this->plateau, 100000);
        $this->charge($this->yopougon, 55000);

        $reponse = $this->actingAs($this->admin)->get(route('admin.comptabilite.analytique'));

        $reponse->assertOk();
        $reponse->assertSee('Boutique du Plateau');
        $reponse->assertSee('Dépôt de Yopougon');
    }

    /**
     * Une charge de siège reste au site où elle a été saisie. L'écran doit le
     * dire : croire à une répartition qui n'a pas lieu ferait juger un magasin
     * sur un résultat qui n'est pas le sien.
     */
    public function test_l_ecran_dit_qu_aucune_cle_de_repartition_n_est_appliquee(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.comptabilite.analytique'))
            ->assertSee("Elle n'est pas répartie entre les magasins", false);
    }

    /**
     * Simulation d'attaque : un caissier sans l'habilitation de comptabilité
     * globale ne doit pas lire le résultat de tous les magasins.
     */
    public function test_un_caissier_sans_habilitation_n_atteint_pas_l_ecran(): void
    {
        $caissier = Utilisateur::create([
            'entreprise_id' => $this->entreprise->id,
            'point_de_vente_id' => $this->plateau->id,
            'nom' => 'Yao', 'prenom' => 'Amenan',
            'email' => 'amenan@quincaillerie.ci',
            'password' => Hash::make('motdepasse-de-test'),
            'role' => 'caissier', 'statut' => 'actif',
            'habilitations' => ['nouvelle_vente'],
        ]);

        $this->actingAs($caissier)
            ->get(route('admin.comptabilite.analytique'))
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────

    private function produit(PointDeVente $site, float $montant, ?string $date = null): void
    {
        $this->ecriture($site, compteCredit: '701000', credit: $montant, date: $date);
    }

    private function charge(PointDeVente $site, float $montant, ?string $date = null): void
    {
        $this->ecriture($site, compteDebit: '601000', debit: $montant, date: $date);
    }

    private function ecriture(
        ?PointDeVente $site,
        ?string $compteDebit = null,
        ?string $compteCredit = null,
        float $debit = 0,
        float $credit = 0,
        ?string $date = null,
    ): void {
        $date = $date ?? now()->toDateString();

        $operation = Operation::creer(
            $this->entreprise->id, $site?->id, $date, 'OD', 'VTE', 'REF', 'Écriture de test'
        );

        EcritureComptable::create([
            'operation_id'      => $operation->id,
            'entreprise_id'     => $this->entreprise->id,
            'point_de_vente_id' => $site?->id,
            'date_ecriture'     => $date,
            'libelle'            => 'Écriture de test',
            'reference_document' => 'REF',
            'code_journal'       => 'VTE',
            'compte_debit'      => $compteDebit,
            'compte_credit'     => $compteCredit,
            'debit'             => $debit,
            'credit'            => $credit,
        ]);
    }
}
