<?php

namespace Tests\Feature;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le tableau de bord général (`/admin/general`).
 *
 * Le contrôleur calculait `$totalVentesHTPeriode` pour en tirer la marge
 * brute et le taux de marge, mais oubliait de le transmettre à la vue dans
 * le `compact(...)` final : la carte « CA HT » de l'écran levait une
 * variable non définie dès le premier chargement, sur toute entreprise.
 */
class TableauDeBordGeneralTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_tableau_de_bord_general_s_affiche_sans_variable_manquante(): void
    {
        $entreprise = Entreprise::create([
            'nom'               => 'Quincaillerie du Plateau',
            'regime_imposition' => 'RNI',
            'adresse'           => 'Plateau, Abidjan',
            'rccm'              => 'CI-ABJ-2026-B-00099',
            'ncc'               => '2609999A',
            'gerant_fonction'   => 'Gérant',
            'secteur_activite'  => ['Commerce'],
            'modules_actifs'    => ['principal', 'ventes', 'achats', 'stock', 'produits', 'tiers'],
        ]);

        $magasin = PointDeVente::create([
            'entreprise_id' => $entreprise->id,
            'nom' => 'Magasin central', 'ville' => 'Abidjan', 'commune' => 'Plateau',
        ]);

        $admin = Utilisateur::create([
            'nom' => 'Bamba', 'prenom' => 'Salif', 'email' => 'salif-tdb@exemple.ci',
            'password' => bcrypt('secret-de-test'), 'role' => 'admin',
            'entreprise_id' => $entreprise->id,
            'point_de_vente_id' => $magasin->id,
        ]);

        $this->actingAs($admin)
            ->withSession(['point_de_vente_actif_id' => $magasin->id])
            ->get(route('admin.tableau_de_bord_general'))
            ->assertOk()
            ->assertViewHas('totalVentesHTPeriode', 0)
            ->assertSee('CA HT');
    }
}
