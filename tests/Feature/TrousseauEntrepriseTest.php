<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\CodeJournal;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PlanComptable;
use App\Modules\Admin\Services\TrousseauEntrepriseService;
use Database\Seeders\ReferentielSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trousseau de départ d'une entreprise.
 *
 * Une entreprise fraîchement créée n'avait ni plan comptable ni journal : la
 * première vente s'imputait sur des comptes inventés à la volée. Ces tests
 * fixent ce qu'elle reçoit, et ce qui ne doit jamais lui être repris.
 */
class TrousseauEntrepriseTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferentielSeeder::class);
        $this->entreprise = Entreprise::create(['nom' => 'Boutique du carrefour']);
    }

    public function test_une_entreprise_neuve_recoit_de_quoi_travailler(): void
    {
        $bilan = TrousseauEntrepriseService::doter($this->entreprise);

        $this->assertSame(38, $bilan['comptes']);
        $this->assertSame(10, $bilan['journaux']);

        // Les comptes que toute écriture de vente au comptant touche. Les
        // quatre derniers manquaient : le service imputait déjà les taxes
        // parafiscales au `447000` sans que ce compte figure au plan, et la
        // balance affichait un numéro sans intitulé.
        foreach (['411000', '401000', '443100', '443200', '443300', '445200',
                  '447000', '447800', '571000', '521000', '701000', '601000'] as $numero) {
            $this->assertTrue(
                PlanComptable::where('entreprise_id', $this->entreprise->id)->where('numero', $numero)->exists(),
                "Le compte {$numero} manque au trousseau."
            );
        }
    }

    public function test_les_journaux_couvrent_les_types_attendus_par_la_comptabilite(): void
    {
        TrousseauEntrepriseService::doter($this->entreprise);

        // `ComptabiliteService` cherche ses journaux par type et retombe sur un
        // code en dur s'il n'en trouve pas. Le trousseau doit couvrir les
        // quatre types qu'il interroge, sinon le repli sert toujours.
        foreach (['Vente' => 'VTE', 'Achat' => 'ACH', 'OD' => 'OD', 'RAN' => 'RAN', 'Banque' => 'BQE'] as $type => $code) {
            $journal = CodeJournal::where('entreprise_id', $this->entreprise->id)
                ->where('type', $type)
                ->first();

            $this->assertNotNull($journal, "Aucun journal de type « {$type} ».");
        }

        $this->assertSame('CAI', CodeJournal::where('entreprise_id', $this->entreprise->id)
            ->where('type', 'Caisse')->value('code'));
    }

    public function test_le_mobile_money_ivoirien_est_livre_d_office(): void
    {
        TrousseauEntrepriseService::doter($this->entreprise);

        $intitules = CodeJournal::where('entreprise_id', $this->entreprise->id)
            ->where('type', 'Banque')->pluck('intitule');

        foreach (['MTN Mobile Money', 'Orange Money', 'Moov Money', 'Wave'] as $operateur) {
            $this->assertTrue($intitules->contains($operateur), "{$operateur} manque.");
        }
    }

    public function test_chaque_journal_de_tresorerie_a_son_compte_au_plan(): void
    {
        TrousseauEntrepriseService::doter($this->entreprise);

        // Sans cela, l'écriture de règlement s'imputerait sur un compte absent
        // du plan, et la balance ne le montrerait nulle part.
        $journaux = CodeJournal::where('entreprise_id', $this->entreprise->id)
            ->whereNotNull('compte')->get();

        $this->assertGreaterThanOrEqual(6, $journaux->count());

        foreach ($journaux as $journal) {
            $this->assertTrue(
                PlanComptable::where('entreprise_id', $this->entreprise->id)
                    ->where('numero', $journal->compte)->exists(),
                "Le journal « {$journal->intitule} » pointe sur {$journal->compte}, absent du plan."
            );
        }
    }

    public function test_les_journaux_sans_tresorerie_n_ont_pas_de_compte(): void
    {
        TrousseauEntrepriseService::doter($this->entreprise);

        foreach (['VTE', 'ACH', 'OD', 'RAN'] as $code) {
            $this->assertNull(
                CodeJournal::where('entreprise_id', $this->entreprise->id)->where('code', $code)->value('compte'),
                "Le journal {$code} ne devrait porter aucun compte."
            );
        }
    }

    public function test_le_report_a_nouveau_est_livre_sans_compte(): void
    {
        // C'est le journal qui reprend les soldes d'un exercice sur le suivant.
        // Il ne servira qu'a la cloture, mais il doit exister des le depart :
        // le creer au moment ou l'on en a besoin, c'est le creer dans l'urgence.
        TrousseauEntrepriseService::doter($this->entreprise);

        $ran = CodeJournal::where('entreprise_id', $this->entreprise->id)
            ->where('code', 'RAN')->firstOrFail();

        $this->assertSame('RAN', $ran->type);
        $this->assertSame('Report à nouveau', $ran->intitule);
        $this->assertNull($ran->compte);

        // Pilote par le systeme : il existe, mais il ne s'offre pas a la saisie.
        // Le proposer dans une liste inviterait a passer une ecriture a la main
        // dans un journal que la cloture recalcule.
        $this->assertTrue($ran->systeme);
        $this->assertFalse(CodeJournal::saisissables()
            ->where('entreprise_id', $this->entreprise->id)
            ->where('code', 'RAN')->exists());

        // Il reste visible en consultation : le grand livre doit pouvoir
        // afficher les ecritures de cloture.
        $this->assertTrue(CodeJournal::actifs()
            ->where('entreprise_id', $this->entreprise->id)
            ->where('code', 'RAN')->exists());
    }

    public function test_les_journaux_de_tresorerie_pointent_sur_la_racine(): void
    {
        TrousseauEntrepriseService::doter($this->entreprise);

        // La caisse pointe sur 571000 et la banque sur 521000 : deux racines de
        // meme rang. Un 521100 aurait designe « Banque X », une des places
        // reservees aux banques de l'entreprise.
        $compte = fn (string $code) => CodeJournal::where('entreprise_id', $this->entreprise->id)
            ->where('code', $code)->value('compte');

        $this->assertSame('571000', $compte('CAI'));
        $this->assertSame('521000', $compte('BQE'));
    }

    public function test_doter_deux_fois_ne_duplique_rien(): void
    {
        TrousseauEntrepriseService::doter($this->entreprise);
        $second = TrousseauEntrepriseService::doter($this->entreprise);

        $this->assertSame(0, $second['comptes']);
        $this->assertSame(0, $second['journaux']);
    }

    public function test_ce_que_l_utilisateur_a_modifie_n_est_jamais_ecrase(): void
    {
        TrousseauEntrepriseService::doter($this->entreprise);

        CodeJournal::where('entreprise_id', $this->entreprise->id)
            ->where('code', 'BQE')
            ->update(['intitule' => 'Ecobank Cocody']);

        TrousseauEntrepriseService::doter($this->entreprise);

        $this->assertSame('Ecobank Cocody', CodeJournal::where('entreprise_id', $this->entreprise->id)
            ->where('code', 'BQE')->value('intitule'));
    }

    public function test_ce_qui_ne_sert_pas_s_archive_et_disparait_des_listes(): void
    {
        TrousseauEntrepriseService::doter($this->entreprise);

        $wave = CodeJournal::where('entreprise_id', $this->entreprise->id)
            ->where('code', 'WAVE')->firstOrFail();
        $wave->update(['archive_le' => now()]);

        $this->assertTrue($wave->fresh()->estArchive());
        $this->assertFalse(CodeJournal::actifs()
            ->where('entreprise_id', $this->entreprise->id)
            ->where('code', 'WAVE')->exists());

        // Archivé, pas supprimé : les écritures qui le citent restent lisibles.
        $this->assertTrue(CodeJournal::where('entreprise_id', $this->entreprise->id)
            ->where('code', 'WAVE')->exists());
    }

    public function test_deux_entreprises_ont_chacune_leur_trousseau(): void
    {
        $voisine = Entreprise::create(['nom' => 'Quincaillerie du plateau']);

        TrousseauEntrepriseService::doter($this->entreprise);
        TrousseauEntrepriseService::doter($voisine);

        // Le plan est cloisonne : le meme 701000 existe chez les deux, sans
        // que l'unicite globale d'antan ne fasse echouer la seconde creation.
        $this->assertSame(2, PlanComptable::where('numero', '701000')->count());

        // Et chacune a le meme nombre de comptes et de journaux.
        $this->assertSame(
            PlanComptable::where('entreprise_id', $this->entreprise->id)->count(),
            PlanComptable::where('entreprise_id', $voisine->id)->count()
        );
        $this->assertSame(10, CodeJournal::where('entreprise_id', $voisine->id)->count());
    }
}
