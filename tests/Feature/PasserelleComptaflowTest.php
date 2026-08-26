<?php

namespace Tests\Feature;

use App\Jobs\DeverserEcritureComptaflow;
use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\Operation;
use App\Modules\Admin\Modeles\Periode;
use App\Modules\Admin\Modeles\PointDeVente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Ce que Selflow envoie à Comptaflow.
 *
 * Le déversement partait amputé de quatre informations, et chacune manquait
 * pour une raison différente :
 *
 * - **le compte de tiers** — Comptaflow le cherchait dans son plan de tiers à
 *   partir du compte général, ne le trouvait pas, et rattachait l'écriture au
 *   seul compte collectif. Le relevé d'un client particulier devenait
 *   impossible à établir ;
 * - **une clé d'idempotence** — `n_saisie` recevait la référence de pièce, qui
 *   ne distingue pas un renvoi d'une écriture nouvelle. Rejouer une
 *   synchronisation dupliquait tout, et la balance doublait ;
 * - **le point de vente** — chez Comptaflow, un axe analytique et sa section.
 *   Sans lui, aucune ventilation par magasin n'est possible en aval ;
 * - **l'exercice** — Comptaflow prenait le sien, actif, sans jamais comparer.
 *   Une pièce d'un exercice clos se serait rangée dans l'exercice courant.
 */
class PasserelleComptaflowTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private PointDeVente $site;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'selflow.comptaflow_api_url'    => 'https://comptaflow.test',
            'selflow.comptaflow_api_secret' => 'secret-de-test',
        ]);

        $this->entreprise = Entreprise::create([
            'nom' => 'Boutique du carrefour',
            'comptaflow_sync_status' => 'active',
        ]);

        // La clé ne passe plus par l'affectation en masse : elle n'est pas
        // `$fillable`, précisément pour qu'aucune requête ne puisse l'y
        // glisser. Elle se pose comme le service la pose.
        $this->entreprise->comptaflow_sync_key = 'cle-de-liaison';
        $this->entreprise->save();

        $this->site = PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Magasin central', 'ville' => 'Abidjan', 'commune' => 'Cocody',
        ]);

        Periode::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => 'Exercice 2026',
            'date_debut' => '2026-01-01', 'date_fin' => '2026-12-31',
            'est_active' => true,
        ]);

        Http::fake([
            '*' => Http::response(['success' => true, 'count' => 1], 200),
        ]);
    }

    private function ecrire(array $attributs = []): EcritureComptable
    {
        $operation = Operation::creer(
            $this->entreprise->id, $this->site->id, '2026-03-10',
            'test', 'VTE', 'FAC-001', 'Vente de marchandises'
        );

        return EcritureComptable::create(array_merge([
            'operation_id'       => $operation->id,
            'entreprise_id'      => $this->entreprise->id,
            'point_de_vente_id'  => $this->site->id,
            'date_ecriture'      => '2026-03-10',
            'libelle'            => 'Vente de marchandises',
            'reference_document' => 'FAC-001',
            'code_journal'       => 'VTE',
            'compte_debit'       => '411000',
            'compte_tiers'       => 'CL0001',
            'debit'              => 100000,
            'credit'             => 0,
        ], $attributs));
    }

    /**
     * L'écriture telle qu'elle part sur le fil.
     */
    private function payload(): array
    {
        $envoyees = [];

        Http::recorded(function ($requete) use (&$envoyees) {
            $corps = $requete->data();

            if (isset($corps['ecritures'])) {
                $envoyees = $corps['ecritures'][0];
            }

            return true;
        });

        return $envoyees;
    }

    // ── Ce qui manquait ──────────────────────────────────────────────

    public function test_le_compte_de_tiers_part_avec_l_ecriture(): void
    {
        // Sans lui, le releve d'un client particulier est impossible : tout
        // atterrit sur le compte collectif 411000.
        $this->ecrire();

        $this->assertSame('CL0001', $this->payload()['compte_tiers']);
    }

    public function test_une_cle_d_idempotence_accompagne_chaque_ecriture(): void
    {
        // `n_saisie` recevait la reference de piece : rejouer une
        // synchronisation dupliquait tout, et la balance doublait.
        $ecriture = $this->ecrire();

        $this->assertSame(
            "SELFLOW-{$this->entreprise->id}-{$ecriture->id}",
            $this->payload()['cle_selflow']
        );
    }

    public function test_la_cle_distingue_deux_ecritures_de_la_meme_piece(): void
    {
        // Une facture produit plusieurs ecritures sous la meme reference : la
        // cle doit les distinguer, sinon la seconde passerait pour un doublon
        // de la premiere et serait perdue.
        $a = $this->ecrire();
        $cleA = $this->payload()['cle_selflow'];

        Http::fake(['*' => Http::response(['success' => true], 200)]);
        $b = $this->ecrire(['compte_debit' => null, 'compte_credit' => '701000',
                            'debit' => 0, 'credit' => 100000]);

        $this->assertNotSame($cleA, $this->payload()['cle_selflow']);
        $this->assertNotSame($a->id, $b->id);
    }

    public function test_le_point_de_vente_part_comme_section_analytique(): void
    {
        // Les points de vente sont les axes, leurs noms les sections.
        $this->ecrire();

        $this->assertSame('Magasin central', $this->payload()['point_de_vente']);
    }

    public function test_l_exercice_ouvert_chez_nous_est_annonce(): void
    {
        // Comptaflow prenait le sien sans comparer : une piece d'un exercice
        // clos se serait rangee dans l'exercice courant.
        $this->ecrire();

        $this->assertSame('2026-01-01', $this->payload()['exercice_debut']);
        $this->assertSame('2026-12-31', $this->payload()['exercice_fin']);
    }

    // ── Ce qui doit continuer de partir ──────────────────────────────

    public function test_les_champs_d_origine_partent_toujours(): void
    {
        $this->ecrire();
        $payload = $this->payload();

        $this->assertSame('2026-03-10', $payload['date_ecriture']);
        $this->assertSame('FAC-001', $payload['reference_document']);
        $this->assertSame('VTE', $payload['code_journal']);
        $this->assertSame('411000', $payload['compte_debit']);
        $this->assertSame(100000.0, $payload['debit']);
    }

    // ── Le secret ────────────────────────────────────────────────────

    public function test_le_secret_part_avec_la_requete(): void
    {
        $this->ecrire();

        $vu = false;

        Http::recorded(function ($requete) use (&$vu) {
            if (($requete->data()['secret'] ?? null) === 'secret-de-test') {
                $vu = true;
            }

            return true;
        });

        $this->assertTrue($vu, 'Le secret partagé doit accompagner chaque déversement.');
    }

    public function test_sans_secret_configure_rien_ne_part_avec_un_secret_devinable(): void
    {
        // Six sites posaient un repli en dur — « selflow-comptaflow-secret-2026 »
        // — qui annulait la correction du lot 0 : la variable d'environnement
        // pouvait manquer sans que rien ne s'en aperçoive.
        config(['selflow.comptaflow_api_secret' => null]);

        $this->ecrire();

        Http::recorded(function ($requete) {
            $this->assertNull(
                $requete->data()['secret'] ?? null,
                'Aucun secret de repli ne doit être inventé.'
            );

            return true;
        });
    }

    // ── L'entreprise non liée ────────────────────────────────────────

    public function test_une_entreprise_non_liee_n_envoie_rien(): void
    {
        $this->entreprise->update(['comptaflow_sync_status' => 'inactive']);

        $this->ecrire();

        Http::assertNothingSent();
    }

    // ── Le déversement part en arrière-plan ────────────────────────────
    //
    // L'appel HTTP partait en synchrone, dans le hook `created()` du modèle :
    // chaque écriture faisait attendre la caisse la réponse de Comptaflow.
    // Il part désormais par un Job mis en file — la caisse n'attend plus rien.

    public function test_l_ecriture_met_le_deversement_en_file_au_lieu_d_appeler_en_direct(): void
    {
        Queue::fake();

        $ecriture = $this->ecrire();

        Queue::assertPushed(DeverserEcritureComptaflow::class, function ($job) use ($ecriture) {
            return $job->ecriture->id === $ecriture->id;
        });

        // La file étant simulée, le Job ne s'exécute pas : rien ne part
        // encore sur le réseau à cet instant.
        Http::assertNothingSent();
    }

    public function test_une_entreprise_non_liee_ne_met_rien_en_file(): void
    {
        Queue::fake();

        $this->entreprise->update(['comptaflow_sync_status' => 'inactive']);

        $this->ecrire();

        Queue::assertNotPushed(DeverserEcritureComptaflow::class);
    }
}
