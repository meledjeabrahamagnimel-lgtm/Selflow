<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Regles\Appartenance;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Cloisonnement entre entreprises.
 *
 * `exists:points_de_vente,id` vérifie qu'un point de vente existe — pas qu'il
 * vous appartient. La surface d'attaque d'une application multi-entreprise n'est
 * pas d'abord l'URL, qu'on regarde toujours en premier, mais le corps de la
 * requête : envoyer l'identifiant du point de vente d'une autre entreprise
 * suffisait à y faire écrire une pièce.
 *
 * Ces tests fixent la règle : ce qui n'appartient pas à l'entreprise connectée
 * ne passe pas la validation.
 */
class CloisonnementTest extends TestCase
{
    use RefreshDatabase;

    private Entreprise $mienne;
    private Entreprise $autre;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mienne = Entreprise::create(['nom' => 'Mon entreprise']);
        $this->autre  = Entreprise::create(['nom' => 'Entreprise voisine']);

        $utilisateur = Utilisateur::create([
            'nom'           => 'Kouadio',
            'prenom'        => 'Lewis',
            'email'         => 'lewis@exemple.ci',
            'password'      => bcrypt('secret-de-test'),
            'role'          => 'admin',
            'entreprise_id' => $this->mienne->id,
        ]);

        Auth::login($utilisateur);
    }

    /** La valeur passe-t-elle la règle d'appartenance ? */
    private function accepte(string $table, $valeur): bool
    {
        return Validator::make(
            ['champ' => $valeur],
            ['champ' => [Appartenance::a($table)]]
        )->passes();
    }

    public function test_un_point_de_vente_de_mon_entreprise_est_accepte(): void
    {
        $pdv = PointDeVente::create(['nom' => 'Siège', 'ville' => 'Abidjan', 'commune' => 'Cocody', 'entreprise_id' => $this->mienne->id]);

        $this->assertTrue($this->accepte('points_de_vente', $pdv->id));
    }

    public function test_le_point_de_vente_d_une_autre_entreprise_est_refuse(): void
    {
        // Le cas exact : le point de vente existe bel et bien, donc l'ancienne
        // règle `exists:points_de_vente,id` le laissait passer.
        $etranger = PointDeVente::create(['nom' => 'Siège voisin', 'ville' => 'Bouaké', 'commune' => 'Belleville', 'entreprise_id' => $this->autre->id]);

        $this->assertFalse($this->accepte('points_de_vente', $etranger->id));
    }

    public function test_le_client_d_une_autre_entreprise_est_refuse(): void
    {
        $etranger = Client::create(['nom' => 'Client voisin', 'entreprise_id' => $this->autre->id]);
        $mien     = Client::create(['nom' => 'Mon client',    'entreprise_id' => $this->mienne->id]);

        $this->assertFalse($this->accepte('clients', $etranger->id));
        $this->assertTrue($this->accepte('clients', $mien->id));
    }

    public function test_le_produit_d_une_autre_entreprise_est_refuse(): void
    {
        $etranger = Produit::create([
            'nom' => 'Riz voisin', 'reference' => 'REF-V-1', 'type' => 'marchandise',
            'prix_achat' => 100, 'prix_vente' => 150, 'entreprise_id' => $this->autre->id,
        ]);

        $this->assertFalse($this->accepte('produits', $etranger->id));
    }

    public function test_une_piece_rattachee_par_son_point_de_vente_est_cloisonnee(): void
    {
        // Les ventes et les achats n'ont pas de colonne `entreprise_id` : ils
        // se rattachent par leur point de vente. Le cloisonnement doit suivre
        // ce chemin-là aussi.
        $pdvEtranger = PointDeVente::create(['nom' => 'Voisin', 'ville' => 'Bouaké', 'commune' => 'Belleville', 'entreprise_id' => $this->autre->id]);
        $pdvMien     = PointDeVente::create(['nom' => 'Mien', 'ville' => 'Abidjan', 'commune' => 'Cocody', 'entreprise_id' => $this->mienne->id]);

        $venteEtrangere = \App\Modules\Admin\Modeles\Vente::create([
            'numero_facture' => 'VTE-VOISIN-001', 'date_vente' => now()->toDateString(),
            'point_de_vente_id' => $pdvEtranger->id, 'etape' => 'Facture', 'mode_paiement' => 'Espèces',
            'montant_ht' => 1000, 'montant_tva' => 180, 'montant_ttc' => 1180,
        ]);
        $venteMienne = \App\Modules\Admin\Modeles\Vente::create([
            'numero_facture' => 'VTE-MIENNE-001', 'date_vente' => now()->toDateString(),
            'point_de_vente_id' => $pdvMien->id, 'etape' => 'Facture', 'mode_paiement' => 'Espèces',
            'montant_ht' => 1000, 'montant_tva' => 180, 'montant_ttc' => 1180,
        ]);

        $this->assertFalse($this->accepte('ventes', $venteEtrangere->id));
        $this->assertTrue($this->accepte('ventes', $venteMienne->id));
    }

    public function test_sans_entreprise_rattachee_rien_n_est_accepte(): void
    {
        $pdv = PointDeVente::create(['nom' => 'Siège', 'ville' => 'Abidjan', 'commune' => 'Cocody', 'entreprise_id' => $this->mienne->id]);
        Auth::logout();

        $this->assertFalse($this->accepte('points_de_vente', $pdv->id));
    }

    public function test_une_table_au_cloisonnement_inconnu_echoue_bruyamment(): void
    {
        // Mieux vaut une exception au développement qu'une règle silencieusement
        // inopérante en production.
        $this->expectException(\InvalidArgumentException::class);

        Appartenance::a('table_inventee');
    }
}
