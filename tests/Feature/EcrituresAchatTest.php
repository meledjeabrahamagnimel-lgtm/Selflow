<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\AchatDetail;
use App\Modules\Admin\Modeles\Categorie;
use App\Modules\Admin\Modeles\CodeJournal;
use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\Fournisseur;
use App\Modules\Admin\Modeles\Operation;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Services\ComptabiliteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * La forme des écritures d'un achat.
 *
 * Le lot 9.1 a redressé la vente ; l'achat portait **le même défaut, à
 * l'identique**, et personne ne l'avait regardé :
 *
 *  1. **L'achat comptant ne passait pas par le 401.** Une seule opération
 *     « caisse contre charges » était écrite. Le compte du fournisseur ne
 *     bougeait donc jamais sur ce qu'on lui payait au comptant, son numéro de
 *     tiers n'était transmis à Comptaflow sur aucun de ces achats — l'écriture
 *     y retombait sur le seul compte collectif — et le journal des achats ne
 *     contenait pas les achats comptant.
 *
 *  2. **Toute la TVA déductible partait en 4452**, « TVA récupérable sur
 *     achats », y compris celle d'un loyer, d'honoraires ou d'un transport.
 *     SYSCOHADA distingue 4451 (immobilisations), 4452 (achats), 4453
 *     (transports) et 4454 (services extérieurs et autres charges), et l'état
 *     de TVA déductible reprend cette distinction.
 *
 * Ce qui était déjà juste et ne bouge pas : le bordereau d'achat (BAPA) ne
 * déduit aucune TVA, parce que le tiers n'en facture aucune.
 */
class EcrituresAchatTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private PointDeVente $site;
    private Fournisseur $fournisseur;
    private Produit $ciment;
    private Produit $honoraires;
    private Produit $transport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entreprise = Entreprise::create(['nom' => 'Quincaillerie du Plateau']);

        $this->site = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Dépôt', 'ville' => 'Abidjan', 'commune' => 'Plateau',
        ]);

        foreach ([['ACH', 'Achats', 'Achat'], ['CAI', 'Caisse', 'Trésorerie']] as [$code, $intitule, $type]) {
            CodeJournal::create([
                'entreprise_id' => $this->entreprise->id,
                'code' => $code, 'intitule' => $intitule, 'type' => $type,
            ]);
        }

        $this->fournisseur = Fournisseur::create([
            'entreprise_id'    => $this->entreprise->id,
            'nom'              => 'Cimenterie de Côte d\'Ivoire',
            'compte_comptable' => '401000',
            'numero_tiers'     => '400012',
        ]);

        // Trois natures de charge, trois comptes de TVA déductible.
        $marchandises = Categorie::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Marchandises', 'prefixe' => 'MAR', 'compte_achat' => '601000',
        ]);
        $services = Categorie::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Honoraires', 'prefixe' => 'HON', 'compte_achat' => '632000',
        ]);
        $transports = Categorie::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Transports', 'prefixe' => 'TRA', 'compte_achat' => '614000',
        ]);

        $this->ciment = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'CIM-50',
            'nom' => 'Ciment 50 kg', 'type' => 'marchandise',
            'prix_achat' => 5000, 'prix_vente' => 6500, 'taux_tva' => 18,
            'categorie_id' => $marchandises->id,
        ]);

        $this->honoraires = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'HON-CPT',
            'nom' => 'Honoraires du comptable', 'type' => 'service',
            'prix_achat' => 100000, 'prix_vente' => 0, 'taux_tva' => 18,
            'categorie_id' => $services->id,
        ]);

        $this->transport = Produit::create([
            'entreprise_id' => $this->entreprise->id, 'reference' => 'TRA-LIV',
            'nom' => 'Transport sur achats', 'type' => 'service',
            'prix_achat' => 20000, 'prix_vente' => 0, 'taux_tva' => 18,
            'categorie_id' => $transports->id,
        ]);
    }

    // ── 1. Le passage par le compte fournisseur ──────────────────────

    public function test_un_achat_comptant_credite_le_compte_du_fournisseur(): void
    {
        // Le défaut : « caisse contre charges », sans aucune ligne 401.
        $achat = $this->achat([['produit' => $this->ciment, 'quantite' => 10, 'prix' => 5000, 'tva' => 9000]]);

        ComptabiliteService::genererEcrituresAchat($achat, 59000, 'Espèces');

        $facturation = $this->operations($achat)->firstWhere('type_operation', 'FactureAchat');

        $this->assertNotNull($facturation, "L'achat comptant n'a produit aucune opération de facturation.");

        $ligne401 = $this->ecrituresDe($facturation)->firstWhere('compte_credit', '401000');

        $this->assertNotNull($ligne401, 'Aucune ligne au compte du fournisseur.');
        $this->assertEqualsWithDelta(59000, (float) $ligne401->credit, 0.01);
        $this->assertSame(0.0, (float) $ligne401->debit);
    }

    public function test_un_achat_comptant_transmet_le_numero_de_tiers(): void
    {
        // Sans ligne 401, aucun numéro de tiers ne partait vers Comptaflow :
        // l'écriture y retombait sur le seul compte collectif, et le relevé
        // d'un fournisseur donné devenait impossible à établir.
        $achat = $this->achat([['produit' => $this->ciment, 'quantite' => 10, 'prix' => 5000, 'tva' => 9000]]);

        ComptabiliteService::genererEcrituresAchat($achat, 59000, 'Espèces');

        $ligne401 = EcritureComptable::where('compte_credit', '401000')->firstOrFail();

        $this->assertSame('400012', $ligne401->compte_tiers);
    }

    public function test_un_achat_comptant_figure_au_journal_des_achats(): void
    {
        // C'est le journal des achats qui justifie les charges déduites.
        $achat = $this->achat([['produit' => $this->ciment, 'quantite' => 10, 'prix' => 5000, 'tva' => 9000]]);

        ComptabiliteService::genererEcrituresAchat($achat, 59000, 'Espèces');

        $auJournalAchats = EcritureComptable::where('code_journal', 'ACH')
            ->where('reference_document', $achat->numero_facture)
            ->get();

        $this->assertGreaterThanOrEqual(3, $auJournalAchats->count());
        $this->assertTrue($auJournalAchats->contains('compte_credit', '401000'));
    }

    public function test_la_facturation_et_le_reglement_sont_deux_operations(): void
    {
        $achat = $this->achat([['produit' => $this->ciment, 'quantite' => 10, 'prix' => 5000, 'tva' => 9000]]);

        ComptabiliteService::genererEcrituresAchat($achat, 59000, 'Espèces');

        $types = $this->operations($achat)->pluck('type_operation')->all();

        $this->assertSame(['FactureAchat', 'ReglementAchat'], $types);
    }

    public function test_le_reglement_debite_le_fournisseur_et_credite_la_caisse(): void
    {
        $achat = $this->achat([['produit' => $this->ciment, 'quantite' => 10, 'prix' => 5000, 'tva' => 9000]]);

        ComptabiliteService::genererEcrituresAchat($achat, 59000, 'Espèces');

        $reglement = $this->operations($achat)->firstWhere('type_operation', 'ReglementAchat');
        $lignes    = $this->ecrituresDe($reglement);

        $this->assertEqualsWithDelta(59000, (float) $lignes->firstWhere('compte_debit', '401000')->debit, 0.01);
        $this->assertEqualsWithDelta(59000, (float) $lignes->where('credit', '>', 0)->first()->credit, 0.01);
    }

    public function test_un_achat_a_credit_n_ecrit_aucun_reglement(): void
    {
        $achat = $this->achat([['produit' => $this->ciment, 'quantite' => 10, 'prix' => 5000, 'tva' => 9000]]);

        ComptabiliteService::genererEcrituresAchat($achat, 0, 'Crédit');

        $this->assertSame(['FactureAchat'], $this->operations($achat)->pluck('type_operation')->all());
    }

    public function test_un_acompte_laisse_le_solde_au_compte_du_fournisseur(): void
    {
        $achat = $this->achat([['produit' => $this->ciment, 'quantite' => 10, 'prix' => 5000, 'tva' => 9000]]);

        ComptabiliteService::genererEcrituresAchat($achat, 20000, 'Espèces');

        $lignes = EcritureComptable::where('compte_debit', '401000')
            ->orWhere('compte_credit', '401000')->get();

        // 59 000 crédités à la facturation, 20 000 débités au règlement :
        // il reste 39 000 dus.
        $this->assertEqualsWithDelta(39000, $lignes->sum('credit') - $lignes->sum('debit'), 0.01);
    }

    // ── 2. La TVA déductible se range par nature de charge ───────────

    public function test_la_tva_sur_marchandises_va_au_compte_des_achats(): void
    {
        $achat = $this->achat([['produit' => $this->ciment, 'quantite' => 10, 'prix' => 5000, 'tva' => 9000]]);

        ComptabiliteService::genererEcrituresAchat($achat, 59000, 'Espèces');

        $this->assertEqualsWithDelta(9000, $this->debitDu('445200'), 0.01);
    }

    public function test_la_tva_sur_honoraires_va_au_compte_des_services(): void
    {
        // Elle partait en 4452 avec les marchandises : pour un cabinet, dont
        // l'essentiel des charges est en 62 et 63, l'état était entièrement
        // faux.
        $achat = $this->achat([['produit' => $this->honoraires, 'quantite' => 1, 'prix' => 100000, 'tva' => 18000]]);

        ComptabiliteService::genererEcrituresAchat($achat, 118000, 'Espèces');

        $this->assertEqualsWithDelta(18000, $this->debitDu('445400'), 0.01);
        $this->assertSame(0.0, $this->debitDu('445200'));
    }

    public function test_la_tva_sur_transport_va_au_compte_des_transports(): void
    {
        $achat = $this->achat([['produit' => $this->transport, 'quantite' => 1, 'prix' => 20000, 'tva' => 3600]]);

        ComptabiliteService::genererEcrituresAchat($achat, 23600, 'Espèces');

        $this->assertEqualsWithDelta(3600, $this->debitDu('445300'), 0.01);
    }

    public function test_une_facture_mixte_ventile_sa_tva_sur_trois_comptes(): void
    {
        $achat = $this->achat([
            ['produit' => $this->ciment,     'quantite' => 10, 'prix' => 5000,   'tva' => 9000],
            ['produit' => $this->honoraires, 'quantite' => 1,  'prix' => 100000, 'tva' => 18000],
            ['produit' => $this->transport,  'quantite' => 1,  'prix' => 20000,  'tva' => 3600],
        ]);

        ComptabiliteService::genererEcrituresAchat($achat, 0, 'Crédit');

        $this->assertEqualsWithDelta(9000,  $this->debitDu('445200'), 0.01);
        $this->assertEqualsWithDelta(18000, $this->debitDu('445400'), 0.01);
        $this->assertEqualsWithDelta(3600,  $this->debitDu('445300'), 0.01);
    }

    // ── 3. Ce qui ne bouge pas ──────────────────────────────────────

    public function test_un_bordereau_d_achat_ne_deduit_toujours_aucune_tva(): void
    {
        // Un BAPA constate un achat auprès d'un tiers non immatriculé : il ne
        // facture aucune TVA, donc il n'y a rien à déduire. Le lot 1 l'avait
        // corrigé, la ventilation par nature ne doit pas le défaire.
        $achat = $this->achat(
            [['produit' => $this->ciment, 'quantite' => 10, 'prix' => 5000, 'tva' => 9000]],
            ['type_facture' => 'bapa', 'montant_ttc' => 50000]
        );

        ComptabiliteService::genererEcrituresAchat($achat, 50000, 'Espèces');

        foreach (['445100', '445200', '445300', '445400'] as $compte) {
            $this->assertSame(0.0, $this->debitDu($compte), "Le bordereau a déduit une TVA en {$compte}.");
        }
    }

    public function test_l_operation_de_facturation_est_equilibree(): void
    {
        $achat = $this->achat([
            ['produit' => $this->ciment,     'quantite' => 10, 'prix' => 5000,   'tva' => 9000],
            ['produit' => $this->honoraires, 'quantite' => 1,  'prix' => 100000, 'tva' => 18000],
        ]);

        ComptabiliteService::genererEcrituresAchat($achat, 0, 'Crédit');

        $lignes = $this->ecrituresDe($this->operations($achat)->firstOrFail());

        $this->assertEqualsWithDelta($lignes->sum('debit'), $lignes->sum('credit'), 0.01);
    }

    public function test_l_ecart_d_arrondi_de_la_tva_est_reporte_et_n_empeche_pas_la_cloture(): void
    {
        // La pièce fait foi : c'est son montant que le fournisseur réclame. La
        // somme des lignes doit y revenir au franc près, sans quoi l'opération
        // ne peut pas se clôturer.
        $achat = $this->achat(
            [['produit' => $this->ciment, 'quantite' => 3, 'prix' => 3333.33, 'tva' => 1800]],
            ['montant_tva' => 1800]
        );

        ComptabiliteService::genererEcrituresAchat($achat, 0, 'Crédit');

        $this->assertEqualsWithDelta(1800, $this->debitDu('445200'), 0.01);
    }

    // ── Les taxes supportées à l'achat : la colonne est retirée ──────

    /**
     * `achats.montant_autres_taxes` n'existe plus — décision du propriétaire,
     * 24/08/2026. Elle était déclarée en `fillable` et en `casts`, et rien ne
     * l'écrivait ni ne la lisait : une colonne qui annonce un montant que rien
     * ne calcule finit par être crue.
     */
    public function test_l_achat_ne_porte_plus_de_colonne_autres_taxes(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Schema::hasColumn('achats', 'montant_autres_taxes'),
            "La colonne a été retirée de la table des achats."
        );

        $this->assertNotContains('montant_autres_taxes', (new Achat)->getFillable());
    }

    /**
     * La vente, elle, la garde : elle y porte les taxes parafiscales
     * **collectées pour l'État**, créditées au 447000 et reversées. C'est une
     * dette, pas une charge — et le retrait de l'achat ne doit pas l'emporter
     * avec lui.
     */
    public function test_la_vente_garde_sa_colonne_autres_taxes(): void
    {
        $this->assertTrue(
            \Illuminate\Support\Facades\Schema::hasColumn('ventes', 'montant_autres_taxes')
        );

        $this->assertContains(
            'montant_autres_taxes',
            (new \App\Modules\Admin\Modeles\Vente)->getFillable()
        );
    }

    /**
     * Les deux tables de taxes de l'achat sont supprimées — même décision,
     * même jour. `achat_detail_taxes` n'avait jamais rien porté ;
     * `achat_taxes` était **remplie par le formulaire et relue par personne**,
     * pendant que l'écran en gonflait le total affiché.
     */
    public function test_l_achat_ne_porte_plus_de_tables_de_taxes(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('achat_taxes'));
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasTable('achat_detail_taxes'));
    }

    /**
     * La vente, elle, les garde : ce sont elles qui portent le champ
     * `customTaxes` réellement transmis à la plateforme.
     */
    public function test_la_vente_garde_ses_tables_de_taxes(): void
    {
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('vente_taxes'));
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasTable('vente_detail_taxes'));
    }

    /**
     * Le formulaire ne propose plus un champ qui n'écrit nulle part. Garder la
     * saisie après avoir retiré la table serait pire que le défaut d'origine.
     */
    public function test_le_formulaire_d_achat_ne_propose_plus_de_taxes_sur_le_ttc(): void
    {
        $vue = file_get_contents(
            base_path('app/Modules/Admin/Vues/achats/nouveau.blade.php')
        );

        $this->assertStringNotContainsString('taxes_ttc[', $vue);
        $this->assertStringNotContainsString('taxesTtcConteneur', $vue);
        $this->assertStringNotContainsString('ajouterTaxeTtc', $vue);
    }

    /**
     * Le pavé de totaux de l'écran d'achat n'avait **aucune ligne de TVA**, et
     * son total valait le seul HT net : sur un achat à 18 %, l'écran annonçait
     * 18 % de moins que la pièce enregistrée. La ligne « Autres taxes » qui
     * disparaît lui cède la place.
     */
    public function test_le_formulaire_d_achat_affiche_la_tva(): void
    {
        $vue = file_get_contents(
            base_path('app/Modules/Admin/Vues/achats/nouveau.blade.php')
        );

        $this->assertStringNotContainsString('totAutresTaxes', $vue);
        $this->assertStringContainsString('totTva', $vue);
        // Le taux du catalogue doit atteindre l'écran, sinon il ne peut pas
        // calculer la même TVA que le serveur.
        $this->assertStringContainsString('data-tva=', $vue);
        $this->assertStringContainsString('const total = htNet + tvaNette;', $vue);
    }

    /**
     * Le retrait ne doit rien changer aux écritures : elles ne la lisaient pas.
     */
    public function test_les_ecritures_d_achat_sont_inchangees_par_le_retrait(): void
    {
        $achat = $this->achat(
            [['produit' => $this->ciment, 'quantite' => 10, 'prix' => 5000, 'tva' => 9000]]
        );

        ComptabiliteService::genererEcrituresAchat($achat, 59000, 'Espèces');

        $this->assertEqualsWithDelta(50000, $this->debitDu('601000'), 0.01);
        $this->assertEqualsWithDelta(9000, $this->debitDu('445200'), 0.01);
        $this->assertEqualsWithDelta(
            59000,
            (float) EcritureComptable::where('compte_credit', '401000')->sum('credit'),
            0.01
        );
    }

    // ─────────────────────────────────────────────────────────────────

    /**
     * @param  array<int, array{produit: Produit, quantite: float, prix: float, tva: float}>  $lignes
     */
    private function achat(array $lignes, array $attributs = []): Achat
    {
        $ht  = 0.0;
        $tva = 0.0;
        foreach ($lignes as $l) {
            $ht  += $l['quantite'] * $l['prix'];
            $tva += $l['tva'];
        }

        $achat = Achat::create(array_merge([
            'point_de_vente_id' => $this->site->id,
            'fournisseur_id'    => $this->fournisseur->id,
            'numero_facture'    => 'ACH-' . uniqid(),
            'date_achat'        => '2026-08-21',
            'mode_paiement'     => 'Espèces',
            'montant_ht'        => $ht,
            'montant_tva'       => $tva,
            'montant_ttc'       => $ht + $tva,
            'statut'            => 'Payé',
            'type_facture'      => 'normale',
            'etape'             => 'Facture',
        ], $attributs));

        foreach ($lignes as $l) {
            AchatDetail::create([
                'achat_id'      => $achat->id,
                'produit_id'    => $l['produit']->id,
                'quantite'      => $l['quantite'],
                'prix_unitaire' => $l['prix'],
                'montant_tva'   => $l['tva'],
                'montant_ttc'   => $l['quantite'] * $l['prix'] + $l['tva'],
            ]);
        }

        return $achat->fresh();
    }

    private function debitDu(string $compte): float
    {
        return (float) EcritureComptable::where('compte_debit', $compte)->sum('debit');
    }

    /** @return \Illuminate\Support\Collection<int, EcritureComptable> */
    private function ecrituresDe(Operation $operation)
    {
        return EcritureComptable::where('operation_id', $operation->id)->get();
    }

    private function operations(Achat $achat)
    {
        return Operation::where('entreprise_id', $this->entreprise->id)
            ->where('reference_document', $achat->numero_facture)
            ->orderBy('id')
            ->get();
    }
}
