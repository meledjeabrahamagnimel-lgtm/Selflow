<?php

namespace Database\Seeders;

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Categorie;
use App\Modules\Admin\Modeles\Stock;
use App\Modules\Admin\Modeles\FicheTechnique;
use App\Modules\Admin\Modeles\FicheTechniqueDetail;
use App\Modules\Admin\Modeles\OrdreProduction;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class DonneesProductionTestSeeder extends Seeder
{
    /**
     * Peupler la base avec des données fictives de production pour les tests.
     */
    public function run(): void
    {
        // 1. Récupérer l'entreprise de démo
        $entreprise = Entreprise::where('email', 'contact@maisondupont.ci')->first();
        if (!$entreprise) {
            $this->command->error("L'entreprise de démo Maison Dupont n'existe pas. Veuillez exécuter le DonneesInitialesSeeder d'abord.");
            return;
        }

        // S'assurer que le secteur Industriel est actif pour afficher le module
        if (!in_array('Industriel', $entreprise->secteur_activite)) {
            $secteurs = $entreprise->secteur_activite;
            $secteurs[] = 'Industriel';
            $entreprise->update([
                'secteur_activite' => $secteurs,
                'modules_actifs'   => array_unique(array_merge($entreprise->modules_actifs, ['production'])),
            ]);
        }

        $pdv = PointDeVente::where('entreprise_id', $entreprise->id)->first();
        if (!$pdv) {
            $this->command->error("Aucun point de vente trouvé pour cette entreprise.");
            return;
        }

        // 2. Créer une catégorie de production boulangerie
        $cat = Categorie::firstOrCreate([
            'entreprise_id' => $entreprise->id,
            'nom'           => 'Boulangerie Industrielle',
        ], [
            'prefixe'       => 'BOUL',
        ]);

        // 3. Créer des matières premières (ingredients)
        $farine = Produit::firstOrCreate([
            'entreprise_id' => $entreprise->id,
            'nom'           => 'Farine de Blé T55 (Sac 25kg)',
        ], [
            'reference'     => 'MAT-FAR55',
            'type'          => 'matiere_premiere',
            'prix_achat'    => 12500,
            'prix_vente'    => 0, // Les matières ne se vendent pas directement
            'taux_tva'      => 0,
            'categorie_id'  => $cat->id,
            'unite'         => 'Kg',
            'compte_achat'  => '602100', // Achat de MP
        ]);

        $levure = Produit::firstOrCreate([
            'entreprise_id' => $entreprise->id,
            'nom'           => 'Levure Boulangère Active',
        ], [
            'reference'     => 'MAT-LEV50',
            'type'          => 'matiere_premiere',
            'prix_achat'    => 450,
            'prix_vente'    => 0,
            'taux_tva'      => 0,
            'categorie_id'  => $cat->id,
            'unite'         => 'g',
            'compte_achat'  => '602100',
        ]);

        $sucre = Produit::firstOrCreate([
            'entreprise_id' => $entreprise->id,
            'nom'           => 'Sucre semoule roux',
        ], [
            'reference'     => 'MAT-SUC20',
            'type'          => 'matiere_premiere',
            'prix_achat'    => 850,
            'prix_vente'    => 0,
            'taux_tva'      => 0,
            'categorie_id'  => $cat->id,
            'unite'         => 'Kg',
            'compte_achat'  => '602100',
        ]);

        // Assigner du stock initial pour ces matières premières
        Stock::updateOrCreate(
            ['produit_id' => $farine->id, 'point_de_vente_id' => $pdv->id],
            ['quantite_disponible' => 500, 'stock_minimum' => 50, 'stock_maximum' => 2000]
        );

        Stock::updateOrCreate(
            ['produit_id' => $levure->id, 'point_de_vente_id' => $pdv->id],
            ['quantite_disponible' => 10000, 'stock_minimum' => 1000, 'stock_maximum' => 50000]
        );

        Stock::updateOrCreate(
            ['produit_id' => $sucre->id, 'point_de_vente_id' => $pdv->id],
            ['quantite_disponible' => 200, 'stock_minimum' => 20, 'stock_maximum' => 1000]
        );

        // 4. Créer un produit fini
        $pain = Produit::firstOrCreate([
            'entreprise_id' => $entreprise->id,
            'nom'           => 'Pain Brioché Maison',
        ], [
            'reference'     => 'PF-BRIOCH',
            'type'          => 'produit_fini',
            'prix_achat'    => 180, // Coût estimé de production
            'prix_vente'    => 350,
            'taux_tva'      => 18.00,
            'categorie_id'  => $cat->id,
            'unite'         => 'Unité',
            'compte_vente'  => '702100', // Vente de PF
        ]);

        Stock::updateOrCreate(
            ['produit_id' => $pain->id, 'point_de_vente_id' => $pdv->id],
            ['quantite_disponible' => 25, 'stock_minimum' => 10, 'stock_maximum' => 500]
        );

        // 5. Créer la Fiche Technique / Recette
        $fiche = FicheTechnique::firstOrCreate([
            'entreprise_id'   => $entreprise->id,
            'produit_fini_id' => $pain->id,
        ], [
            'description'     => 'Fiche recette standard pour la fabrication du Pain Brioché Maison.',
        ]);

        FicheTechniqueDetail::updateOrCreate(
            ['fiche_technique_id' => $fiche->id, 'ingredient_id' => $farine->id],
            ['quantite' => 0.35, 'unite' => 'Kg'] // 350g par pain
        );

        FicheTechniqueDetail::updateOrCreate(
            ['fiche_technique_id' => $fiche->id, 'ingredient_id' => $levure->id],
            ['quantite' => 8, 'unite' => 'g'] // 8g par pain
        );

        FicheTechniqueDetail::updateOrCreate(
            ['fiche_technique_id' => $fiche->id, 'ingredient_id' => $sucre->id],
            ['quantite' => 0.05, 'unite' => 'Kg'] // 50g par pain
        );

        // 6. Créer des Ordres de Production fictifs (un Brouillon et un Terminé)
        OrdreProduction::firstOrCreate([
            'entreprise_id'     => $entreprise->id,
            'code_ordre'        => 'OP-2026-0001',
        ], [
            'point_de_vente_id' => $pdv->id,
            'produit_fini_id'   => $pain->id,
            'quantite_cible'    => 100,
            'statut'            => 'Brouillon',
            'date_production'   => now()->toDateString(),
        ]);

        OrdreProduction::firstOrCreate([
            'entreprise_id'     => $entreprise->id,
            'code_ordre'        => 'OP-2026-0002',
        ], [
            'point_de_vente_id' => $pdv->id,
            'produit_fini_id'   => $pain->id,
            'quantite_cible'    => 50,
            'statut'            => 'Terminé',
            'date_production'   => now()->subDays(2)->toDateString(),
        ]);
    }
}
