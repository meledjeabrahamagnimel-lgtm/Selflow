<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le point de vente, tel que la DGI l'exige.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Ce que le point de vente est vraiment
 * ─────────────────────────────────────────────────────────────────────────
 *
 * **Son nom part tel quel à la plateforme de la DGI**, avec chaque facture.
 * Elle refuse la pièce si ce nom ne correspond à aucun site déclaré sur
 * l'espace FNE. Ce n'est donc pas une commodité d'organisation : c'est une
 * donnée fiscale, et elle appartient à l'entreprise.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Ce que l'application en faisait
 * ─────────────────────────────────────────────────────────────────────────
 *
 * **Elle en inventait un.** Quatre endroits créaient d'office un « Siège » :
 * la création d'entreprise par le superadministrateur, la passerelle
 * entrante, et la caisse — deux fois, dans `nouvelle()` et dans
 * `enregistrer()`. Ville devinée en coupant l'adresse à la première virgule,
 * commune « Cocody » ou « Plateau », responsable « Superviseur ».
 *
 * Trois informations inventées, sous un nom qui n'a aucune raison d'être
 * celui de l'espace FNE. **La première facture partait à ce nom-là**, et la
 * plateforme la refusait — ou pire, la certifiait sous un magasin qui
 * n'existe pas.
 *
 * Et le point de vente ne figurait pas parmi les éléments réclamés avant de
 * vendre : l'entreprise découvrait le problème au premier encaissement.
 */
class PointDeVenteObligatoireTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $entreprise;
    private Utilisateur $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Une entreprise complète sur tout, sauf le point de vente.
        $this->entreprise = Entreprise::create([
            'nom' => 'DC-Knowing CGA', 'regime_imposition' => 'RNI',
            'adresse' => 'Cocody, Abidjan', 'rccm' => 'CI-ABJ-2026-B-00042',
            'ncc' => '2601234A', 'gerant_fonction' => 'Gérant',
            'secteur_activite' => ['Commerce'],
            'modules_actifs' => ['principal', 'ventes', 'points_de_vente'],
        ]);

        $this->admin = Utilisateur::create([
            'nom' => 'Kouadio', 'prenom' => 'Lewis', 'email' => 'lewis-pdv@dc-knowing.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $this->entreprise->id,
        ]);
    }

    private function unPointDeVente(string $nom = 'Boutique Angré'): PointDeVente
    {
        return PointDeVente::create([
            'entreprise_id' => $this->entreprise->id,
            'nom' => $nom, 'ville' => 'Abidjan', 'commune' => 'Cocody',
        ]);
    }

    // ── Le point de vente est réclamé ────────────────────────────────

    public function test_sans_point_de_vente_l_inscription_n_est_pas_complete(): void
    {
        $this->assertFalse($this->entreprise->estInscriptionComplete());

        $cles = collect($this->entreprise->elementsInscriptionManquants())->pluck('cle')->all();
        $this->assertSame(['point_de_vente'], $cles);
    }

    public function test_un_point_de_vente_suffit_a_completer_l_inscription(): void
    {
        $this->unPointDeVente();

        $this->assertTrue($this->entreprise->fresh()->estInscriptionComplete());
    }

    public function test_ce_qui_manque_est_nomme_et_non_devine(): void
    {
        // L'écran disait « renseigner toutes les informations réglementaires »
        // sans jamais dire lesquelles : l'utilisateur arrivait sur une page de
        // trois écrans de haut et cherchait.
        $manquants = $this->entreprise->elementsInscriptionManquants();

        $this->assertStringContainsString('point de vente', $manquants[0]['libelle']);
        $this->assertSame('points_de_vente', $manquants[0]['ou']);
    }

    public function test_l_ecran_de_blocage_liste_ce_qui_manque(): void
    {
        $corps = $this->actingAs($this->admin)
            ->get(route('admin.tableau_de_bord'))->assertOk()->getContent();

        $this->assertStringContainsString('Au moins un point de vente', $corps);
        $this->assertStringContainsString('Créer mon point de vente', $corps);
    }

    public function test_la_caisse_reste_fermee_sans_point_de_vente(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.ventes.nouvelle'))
            ->assertRedirect(route('admin.tableau_de_bord'));
    }

    // ── Rien ne se crée tout seul ────────────────────────────────────

    public function test_ouvrir_la_caisse_ne_cree_aucun_point_de_vente(): void
    {
        $this->actingAs($this->admin)->get(route('admin.ventes.nouvelle'));

        // La caisse posait un « Siège » à Abidjan, commune Cocody, responsable
        // « Superviseur ». La première facture partait à ce nom-là.
        $this->assertSame(0, PointDeVente::where('entreprise_id', $this->entreprise->id)->count());
        $this->assertDatabaseMissing('points_de_vente', ['nom' => 'Siège']);
    }

    public function test_la_passerelle_ne_cree_plus_de_siege(): void
    {
        config(['selflow.comptaflow_api_secret' => 'secret-serveur-de-test']);

        $this->postJson(route('api.external.register-enterprise'), [
            'secret'         => 'secret-serveur-de-test',
            'nom'            => 'Entreprise venue de Comptaflow',
            'email'          => 'venue@comptaflow.ci',
            'adresse'        => 'Marcory, Abidjan',
            'admin_password' => 'un-mot-de-passe-long',
        ])->assertOk();

        $creee = Entreprise::where('nom', 'Entreprise venue de Comptaflow')->firstOrFail();

        $this->assertSame(0, PointDeVente::where('entreprise_id', $creee->id)->count());
    }

    public function test_l_entreprise_cree_son_point_de_vente_et_la_caisse_s_ouvre(): void
    {
        $this->actingAs($this->admin)->post(route('admin.pdv.creer'), [
            'nom' => 'Boutique Angré', 'ville' => 'Abidjan', 'commune' => 'Cocody',
        ]);

        $this->assertDatabaseHas('points_de_vente', [
            'entreprise_id' => $this->entreprise->id,
            'nom'           => 'Boutique Angré',
        ]);

        $this->actingAs($this->admin)->get(route('admin.ventes.nouvelle'))->assertOk();
    }

    // ── Le point de vente actif survit à la déconnexion ──────────────

    public function test_le_point_de_vente_choisi_est_retenu_au_dela_de_la_session(): void
    {
        $premier = $this->unPointDeVente('Boutique Angré');
        $second  = $this->unPointDeVente('Boutique Yopougon');

        $this->actingAs($this->admin)->post(route('admin.pdv.activer', $second));

        $this->assertSame($second->id, $this->admin->fresh()->point_de_vente_actif_id);
    }

    public function test_la_connexion_rouvre_sur_le_point_de_vente_d_hier(): void
    {
        $premier = $this->unPointDeVente('Boutique Angré');
        $second  = $this->unPointDeVente('Boutique Yopougon');

        $this->admin->retenirLePointDeVente($second->id);

        // `session()->invalidate()` à la déconnexion effaçait le choix : un
        // responsable de trois magasins repartait chaque matin sur le premier
        // venu, et pouvait encaisser au nom d'un magasin où il n'était pas.
        $this->post(route('connexion.traitement'), [
            'email' => 'lewis-pdv@dc-knowing.ci', 'password' => 'secret-de-test',
        ]);

        $this->assertSame($second->id, session('point_de_vente_actif_id'));
        $this->assertSame('Boutique Yopougon', session('point_de_vente_actif_nom'));
    }

    public function test_un_point_de_vente_supprime_n_est_pas_rouvert(): void
    {
        $pdv = $this->unPointDeVente('Boutique fermée');
        $this->admin->retenirLePointDeVente($pdv->id);
        $pdv->delete();

        $this->post(route('connexion.traitement'), [
            'email' => 'lewis-pdv@dc-knowing.ci', 'password' => 'secret-de-test',
        ]);

        $this->assertNull(session('point_de_vente_actif_id'));
    }

    public function test_le_point_de_vente_retenu_ne_traverse_pas_les_entreprises(): void
    {
        // Simulation d'attaque : la valeur est retenue en base et relue à la
        // connexion. Si elle n'était pas revérifiée, un identifiant écrit à la
        // main ouvrirait la caisse d'une autre entreprise.
        $voisine = Entreprise::create(['nom' => 'Voisine SARL']);
        $chezElle = PointDeVente::create([
            'entreprise_id' => $voisine->id,
            'nom' => 'Magasin du voisin', 'ville' => 'Abidjan', 'commune' => 'Treichville',
        ]);

        $this->admin->retenirLePointDeVente($chezElle->id);

        $this->post(route('connexion.traitement'), [
            'email' => 'lewis-pdv@dc-knowing.ci', 'password' => 'secret-de-test',
        ]);

        $this->assertNull(session('point_de_vente_actif_id'));
    }

    public function test_l_affectation_d_un_caissier_n_est_pas_une_preference(): void
    {
        $affecte = $this->unPointDeVente('Boutique Angré');
        $autre   = $this->unPointDeVente('Boutique Yopougon');

        $caissier = Utilisateur::create([
            'nom' => 'Yao', 'prenom' => 'Ama', 'email' => 'ama-pdv@dc-knowing.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'caissier',
            'entreprise_id' => $this->entreprise->id,
            'point_de_vente_id' => $affecte->id,
        ]);

        // Son affectation prime, même s'il a un jour regardé un autre magasin :
        // sans quoi il encaisserait au nom d'un site où il n'est pas.
        $caissier->retenirLePointDeVente($autre->id);

        $this->assertSame($affecte->id, $caissier->fresh()->pointDeVenteDOuverture());
    }
}
