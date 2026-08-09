<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\Fournisseur;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Vente;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ce qui n'est pas à vous n'existe pas pour vous.
 *
 * ## L'oracle de volume
 *
 * Le cloisonnement tenait : aucune donnée d'autrui n'était rendue, et les
 * soixante-cinq gardes d'appartenance faisaient leur travail. **Mais elles
 * répondaient 403 là où une pièce inexistante répond 404**, et la différence se
 * lit.
 *
 * Les identifiants étant séquentiels, il suffisait de demander
 * `/admin/ventes/facture/1`, `/2`, `/3` … et de compter les 403 pour connaître
 * **le nombre de factures de toute la plateforme** — puis, en recommençant une
 * semaine plus tard, son rythme de croissance. Ce n'était pas une faille
 * d'autorisation, c'était une **fuite de volume** ; et pour une plateforme
 * vendue à des entreprises concurrentes, le volume est une information
 * commerciale.
 *
 * Une pièce qui n'est pas la vôtre répond désormais comme tout ce qui n'existe
 * pas.
 *
 * ## Ce que le 404 ne remplace pas
 *
 * Il supprime l'oracle, non le besoin d'identifiants opaques : une adresse qui
 * porte `4213` dit encore quelque chose à qui la voit passer — dans un courriel
 * transféré, une capture d'écran, un billet d'assistance. Voir le journal, lot
 * 8.3, pour ce qui reste à faire et pourquoi cela n'a pas été fait ici.
 */
class CloisonnementTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $mienne;
    private Entreprise $sienne;
    private PointDeVente $monMagasin;
    private PointDeVente $sonMagasin;
    private Utilisateur $moi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mienne = $this->uneEntreprise('Quincaillerie du Bandama', 'CI-ABJ-2026-B-01111');
        $this->sienne = $this->uneEntreprise('Quincaillerie rivale', 'CI-ABJ-2026-B-02222');

        $this->monMagasin = PointDeVente::create([
            'entreprise_id' => $this->mienne->id,
            'nom' => 'Magasin central', 'ville' => 'Abidjan', 'commune' => 'Cocody',
        ]);

        $this->sonMagasin = PointDeVente::create([
            'entreprise_id' => $this->sienne->id,
            'nom' => 'Magasin rival', 'ville' => 'Abidjan', 'commune' => 'Yopougon',
        ]);

        $this->moi = Utilisateur::create([
            'nom' => 'Yao', 'prenom' => 'Adjoua', 'email' => 'adjoua@exemple.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $this->mienne->id,
            'point_de_vente_id' => $this->monMagasin->id,
        ]);

        $this->actingAs($this->moi)
            ->withSession(['point_de_vente_actif_id' => $this->monMagasin->id]);
    }

    private function uneEntreprise(string $nom, string $rccm): Entreprise
    {
        return Entreprise::create([
            'nom' => $nom, 'regime_imposition' => 'RNI', 'adresse' => 'Abidjan',
            'rccm' => $rccm, 'ncc' => '2601234A', 'gerant_fonction' => 'Gérant',
            'secteur_activite' => ['Commerce'],
            'modules_actifs' => ['principal', 'ventes', 'achats', 'stock', 'produits',
                                 'tiers', 'comptabilite', 'production'],
        ]);
    }

    private function saVente(): Vente
    {
        return Vente::create([
            'point_de_vente_id' => $this->sonMagasin->id,
            'numero_facture'    => 'VTE-RIVALE-001',
            'date_vente'        => now()->toDateString(),
            'mode_paiement'     => 'Espèces',
            'montant_ht'        => 100000, 'montant_tva' => 18000, 'montant_ttc' => 118000,
            'statut'            => 'Payé', 'etape' => 'Facture',
        ]);
    }

    // ══════════════ L'oracle est fermé ══════════════

    public function test_la_facture_d_un_concurrent_repond_comme_une_facture_inexistante(): void
    {
        // **C'est tout l'objet du lot.** Répondre 403 sur l'une et 404 sur
        // l'autre distinguait les deux, et compter les 403 sur des identifiants
        // séquentiels donnait le volume de toute la plateforme.
        $sienne = $this->saVente();

        $surLaSienne  = $this->get(route('admin.ventes.imprimer', $sienne));
        $surLeNeant   = $this->get(route('admin.ventes.imprimer', 999999));

        $this->assertSame(404, $surLaSienne->status());
        $this->assertSame($surLeNeant->status(), $surLaSienne->status(),
            'La pièce d\'un autre et la pièce inexistante doivent être indiscernables.');
    }

    public function test_le_comptage_des_identifiants_ne_dit_plus_rien(): void
    {
        // La méthode de l'attaquant, jouée : parcourir les identifiants et
        // compter les réponses qui se distinguent. Il n'en reste aucune.
        $this->saVente();
        $this->saVente()->update(['numero_facture' => 'VTE-RIVALE-002']);

        $reponses = [];

        for ($id = 1; $id <= 12; $id++) {
            $reponses[] = $this->get(route('admin.ventes.imprimer', $id))->status();
        }

        $this->assertSame([404], array_values(array_unique($reponses)),
            'Aucune réponse ne doit distinguer ce qui existe de ce qui n\'existe pas.');
    }

    // ══════════════ Le cloisonnement lui-même ══════════════

    /**
     * Les pièces d'un concurrent, et la route qui les demande.
     *
     * @return array<int, array<int, string>>
     */
    public static function lesPieces(): array
    {
        return [
            ['vente',        'admin.ventes.imprimer'],
            ['produit',      'admin.produits.fiche'],
            ['client',       'admin.clients.modifier'],
            ['fournisseur',  'admin.fournisseurs.modifier'],
        ];
    }

    public function test_la_fiche_article_d_un_concurrent_est_introuvable(): void
    {
        $sien = Produit::create([
            'entreprise_id' => $this->sienne->id, 'reference' => 'RIVAL-001',
            'nom' => 'Article rival', 'type' => 'marchandise',
            'prix_achat' => 1000, 'prix_vente' => 1500,
        ]);

        $this->get(route('admin.produits.fiche', $sien))->assertNotFound();
    }

    public function test_le_client_d_un_concurrent_est_introuvable(): void
    {
        // Le fichier client d'un concurrent est ce qu'il a de plus précieux.
        $sien = Client::create([
            'entreprise_id' => $this->sienne->id, 'nom' => 'Grand compte rival',
        ]);

        $this->put(route('admin.clients.modifier', $sien), ['nom' => 'Détourné'])
            ->assertNotFound();

        $this->assertSame('Grand compte rival', $sien->fresh()->nom);
    }

    public function test_le_fournisseur_d_un_concurrent_est_introuvable(): void
    {
        $sien = Fournisseur::create([
            'entreprise_id' => $this->sienne->id, 'nom' => 'Grossiste rival',
        ]);

        $this->put(route('admin.fournisseurs.modifier', $sien), ['nom' => 'Détourné'])
            ->assertNotFound();

        $this->assertSame('Grossiste rival', $sien->fresh()->nom);
    }

    public function test_la_facture_d_un_concurrent_ne_se_modifie_pas(): void
    {
        $sienne = $this->saVente();

        $this->get(route('admin.ventes.modifier', $sienne))->assertNotFound();
        $this->delete(route('admin.ventes.supprimer', $sienne))->assertNotFound();

        $this->assertNotNull(Vente::find($sienne->id));
    }

    public function test_la_facture_d_un_concurrent_ne_se_normalise_pas(): void
    {
        // Normaliser la pièce d'un autre l'enverrait à la DGI **sous notre
        // clé** : la certification porterait le NCC de la mauvaise entreprise.
        $sienne = $this->saVente();

        $this->post(route('admin.ventes.normaliser', $sienne))->assertNotFound();

        $this->assertFalse((bool) $sienne->fresh()->normalise);
    }

    // ══════════════ Ce qui doit rester distinct ══════════════

    public function test_le_refus_d_habilitation_reste_un_403(): void
    {
        // **Le 404 vaut pour l'appartenance, non pour le droit.** Dire à un
        // caissier « introuvable » sur un écran qui existe et que son
        // administrateur peut lui ouvrir le laisserait chercher une panne là où
        // il n'y a qu'un droit manquant.
        $caissier = Utilisateur::create([
            'nom' => 'Koffi', 'prenom' => 'Amos', 'email' => 'amos@exemple.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'caissier',
            'entreprise_id' => $this->mienne->id,
            'point_de_vente_id' => $this->monMagasin->id,
            'habilitations' => ['nouvelle_vente'],
        ]);

        $this->actingAs($caissier);

        $this->get(route('caissier.stock.index'))->assertForbidden();
    }

    public function test_ma_propre_piece_reste_accessible(): void
    {
        // Le verrouillage ne doit pas gagner le travail ordinaire.
        $mienne = Vente::create([
            'point_de_vente_id' => $this->monMagasin->id,
            'numero_facture'    => 'VTE-000001',
            'date_vente'        => now()->toDateString(),
            'mode_paiement'     => 'Espèces',
            'montant_ht'        => 10000, 'montant_tva' => 1800, 'montant_ttc' => 11800,
            'statut'            => 'Payé', 'etape' => 'Facture',
        ]);

        $this->get(route('admin.ventes.imprimer', $mienne))->assertOk();
    }

    // ══════════════ La règle partagée ══════════════

    public function test_la_regle_reconnait_l_entreprise_portee_par_le_point_de_vente(): void
    {
        // Une vente appartient à un magasin, qui appartient à une entreprise :
        // elle ne porte pas `entreprise_id` elle-même.
        $mienne = Vente::create([
            'point_de_vente_id' => $this->monMagasin->id,
            'numero_facture'    => 'VTE-000002',
            'date_vente'        => now()->toDateString(),
            'mode_paiement'     => 'Espèces',
            'montant_ht'        => 1000, 'montant_tva' => 180, 'montant_ttc' => 1180,
            'statut'            => 'Payé', 'etape' => 'Facture',
        ]);

        $this->assertTrue(\App\Modules\Admin\Regles\Cloisonnement::appartient($mienne));
        $this->assertFalse(\App\Modules\Admin\Regles\Cloisonnement::appartient($this->saVente()));
    }

    public function test_aucune_garde_d_appartenance_ne_repond_plus_403(): void
    {
        // Le test qui empêche la dérive : une garde neuve écrite avec 403
        // rouvrirait l'oracle sans que personne ne s'en aperçoive.
        $fautives = [];

        foreach (glob(app_path('Modules/*/Controleurs/*.php')) as $chemin) {
            $src = file_get_contents($chemin);

            foreach (preg_split('/\babort_(?:unless|if)\s*\(/', $src) as $index => $morceau) {
                if ($index === 0) {
                    continue;
                }

                $args = substr($morceau, 0, (int) strpos($morceau, ');'));

                if (str_contains($args, 'entreprise_id') && preg_match('/(?<![\w.])403(?![\w.])/', $args)) {
                    $fautives[] = basename($chemin);
                }
            }
        }

        $this->assertSame([], array_values(array_unique($fautives)),
            'Ces contrôleurs refusent encore une pièce d\'autrui par un 403, ce qui rouvre '
            . 'l\'oracle de volume : ' . implode(', ', array_unique($fautives)));
    }
}
