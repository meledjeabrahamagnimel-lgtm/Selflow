<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Categorie;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Stock;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

echo "🧹 Nettoyage de la base de données locale...\n";

DB::statement('SET FOREIGN_KEY_CHECKS=0;');

$tablesToTruncate = [
    'achat_details', 'achats', 'b2b_negotiations', 'banques',
    'bon_livraison_details', 'bons_livraison', 'categories',
    'clients', 'codes_journaux', 'ecritures_comptables', 'entreprises',
    'fiche_technique_details', 'fiches_techniques', 'fournisseurs',
    'journal_audit', 'mouvements_stock', 'ordres_production', 'periodes',
    'plan_comptable', 'points_de_vente', 'produit_details_libres', 'produits',
    'sous_categories', 'stocks', 'transferts_stock', 'tresorerie_journal',
    'utilisateurs', 'vente_details', 'ventes'
];

foreach ($tablesToTruncate as $table) {
    DB::table($table)->truncate();
    echo "   - Table $table vidée.\n";
}

DB::statement('SET FOREIGN_KEY_CHECKS=1;');

echo "✅ Base de données nettoyée.\n\n";

// 1. Créer le compte SuperAdmin
echo "👤 Création du SuperAdmin global...\n";
$superadmin = Utilisateur::create([
    'nom'                   => 'Super',
    'prenom'                => 'Admin',
    'email'                 => 'superadmin@gmail.com',
    'password'              => Hash::make('SUPER123@'),
    'role'                  => 'superadmin',
    'statut'                => 'actif',
    'entreprise_id'         => null,
    'point_de_vente_id'     => null,
    'doit_changer_password' => false,
]);
echo "✅ SuperAdmin créé avec ID : " . $superadmin->id . "\n\n";

// 2. Définir les modules actifs complets pour nos entreprises
$modules = ['principal', 'ventes', 'achats', 'stock', 'production', 'comptabilite', 'points_de_vente', 'produits', 'tiers', 'rapports', 'b2b', 'fne'];

// 3. Données des deux entreprises à créer
$entreprisesInfos = [
    [
        'entreprise' => [
            'nom'                 => 'DC-KNOWING',
            'forme_juridique'     => 'SARL',
            'gerant_nom'          => 'AGNIMEL',
            'gerant_prenom'       => 'AB',
            'gerant_fonction'     => 'Gérant',
            'adresse'             => 'Cocody II Plateaux Angré les Oscars, Abidjan, Côte d\'Ivoire',
            'telephone'           => '27 22 42 14 43',
            'email'               => 'dcknowing@gmail.com',
            'rccm'                => 'CI-ABJ-2018-B-31734',
            'ncc'                 => '1864699 A',
            'compte_contribuable' => '1864699 A',
            'regime_imposition'   => 'TEE',
            'centre_impots'       => '2 PLATEAUX 3',
            'quota_points_de_vente' => 10,
            'plan_abonnement'     => 'Pro',
            'secteur_activite'    => ['Commercial', 'Industriel', 'Services'],
            'modules_actifs'      => $modules,
            'statut'              => 'actif'
        ],
        'admin' => [
            'nom' => 'AGNIMEL',
            'prenom' => 'AB',
            'email' => 'dcknowing@gmail.com',
            'password' => 'ADMIN@@@###123',
            'role' => 'admin',
            'statut' => 'actif'
        ]
    ],
    [
        'entreprise' => [
            'nom'                 => 'B-HOME SARL',
            'forme_juridique'     => 'SARL',
            'gerant_nom'          => 'KOFFI',
            'gerant_prenom'       => 'KONE',
            'gerant_fonction'     => 'Gérant',
            'adresse'             => 'COCODY CITE DES CADRES, Abidjan, Côte d\'Ivoire',
            'telephone'           => '0709767690',
            'email'               => 'bhome@gmail.com',
            'rccm'                => 'CI-ABJ-2022-B-54321', // Généré
            'ncc'                 => '1234567 B', // Généré
            'compte_contribuable' => '1234567 B',
            'regime_imposition'   => 'TEE',
            'centre_impots'       => 'COCODY 1',
            'quota_points_de_vente' => 10,
            'plan_abonnement'     => 'Pro',
            'secteur_activite'    => ['Commercial', 'Industriel', 'Services'],
            'modules_actifs'      => $modules,
            'statut'              => 'actif'
        ],
        'admin' => [
            'nom' => 'KOFFI',
            'prenom' => 'KONE',
            'email' => 'bhome@gmail.com',
            'password' => 'ADMIN@@@###123',
            'role' => 'admin',
            'statut' => 'actif'
        ]
    ]
];

// Catégories à créer pour chaque secteur
$structureCategories = [
    'Commercial' => [
        ['nom' => 'Équipements Électroniques', 'prefix' => 'EE'],
        ['nom' => 'Accessoires Informatiques', 'prefix' => 'AI'],
        ['nom' => 'Mobilier de Bureau', 'prefix' => 'MB'],
        ['nom' => 'Fournitures & Consommables', 'prefix' => 'FC'],
        ['nom' => 'Produits Emballés', 'prefix' => 'PE'],
    ],
    'Industriel' => [
        ['nom' => 'Matières Premières', 'prefix' => 'MP'],
        ['nom' => 'Composants Mécaniques', 'prefix' => 'CM'],
        ['nom' => 'Emballages Industriels', 'prefix' => 'EI'],
        ['nom' => 'Outils de Production', 'prefix' => 'OP'],
        ['nom' => 'Produits Semi-Finis', 'prefix' => 'PS'],
    ],
    'Services' => [
        ['nom' => 'Conseil & Audit', 'prefix' => 'CA'],
        ['nom' => 'Formations Techniques', 'prefix' => 'FT'],
        ['nom' => 'Maintenance & Support', 'prefix' => 'MS'],
        ['nom' => 'Développement Logiciel', 'prefix' => 'DL'],
        ['nom' => 'Assistance Administrative', 'prefix' => 'AA'],
    ]
];

// Produits types par catégorie
$produitsTypes = [
    'Équipements Électroniques' => [
        ['nom' => 'Ordinateur Portable Pro 15"', 'type' => 'marchandise', 'prix_achat' => 350000, 'prix_vente' => 480000, 'unite' => 'Unité'],
        ['nom' => 'Écran LED UltraWide 29"', 'type' => 'marchandise', 'prix_achat' => 120000, 'prix_vente' => 175000, 'unite' => 'Unité'],
    ],
    'Accessoires Informatiques' => [
        ['nom' => 'Souris Optique Sans Fil', 'type' => 'marchandise', 'prix_achat' => 5000, 'prix_vente' => 9500, 'unite' => 'Unité'],
        ['nom' => 'Clavier Mécanique Rétroéclairé', 'type' => 'marchandise', 'prix_achat' => 15000, 'prix_vente' => 25000, 'unite' => 'Unité'],
    ],
    'Mobilier de Bureau' => [
        ['nom' => 'Fauteuil de Bureau Ergonomique', 'type' => 'marchandise', 'prix_achat' => 65000, 'prix_vente' => 95000, 'unite' => 'Unité'],
        ['nom' => 'Bureau d\'Angle Modulaire', 'type' => 'marchandise', 'prix_achat' => 110000, 'prix_vente' => 165000, 'unite' => 'Unité'],
    ],
    'Fournitures & Consommables' => [
        ['nom' => 'Rame de papier A4 80g (Carton)', 'type' => 'consommable_stockable', 'prix_achat' => 12000, 'prix_vente' => 18000, 'unite' => 'Carton'],
        ['nom' => 'Cartouche d\'encre LaserJet Noire', 'type' => 'consommable_stockable', 'prix_achat' => 22000, 'prix_vente' => 35000, 'unite' => 'Unité'],
    ],
    'Produits Emballés' => [
        ['nom' => 'Bouteille Eau Minérale 1.5L (Pack de 6)', 'type' => 'marchandise', 'prix_achat' => 1200, 'prix_vente' => 2000, 'unite' => 'Pack'],
    ],
    'Matières Premières' => [
        ['nom' => 'Résine Plastique Haute Densité', 'type' => 'matiere_premiere', 'prix_achat' => 850, 'prix_vente' => 1300, 'unite' => 'Kg'],
        ['nom' => 'Tôle d\'Acier Galvanisé 2mm', 'type' => 'matiere_premiere', 'prix_achat' => 4500, 'prix_vente' => 6800, 'unite' => 'Feuille'],
    ],
    'Composants Mécaniques' => [
        ['nom' => 'Roulement à Billes Renforcé', 'type' => 'matiere_premiere', 'prix_achat' => 1200, 'prix_vente' => 2200, 'unite' => 'Unité'],
    ],
    'Emballages Industriels' => [
        ['nom' => 'Palette Europe Bois', 'type' => 'consommable_stockable', 'prix_achat' => 4500, 'prix_vente' => 7500, 'unite' => 'Unité'],
    ],
    'Outils de Production' => [
        ['nom' => 'Fraiseuse Numérique Standard', 'type' => 'consommable_non_stockable', 'prix_achat' => 1250000, 'prix_vente' => 1850000, 'unite' => 'Unité'],
    ],
    'Produits Semi-Finis' => [
        ['nom' => 'Profilé Aluminium Découpé 3m', 'type' => 'produit_fini', 'prix_achat' => 8000, 'prix_vente' => 14000, 'unite' => 'Barre'],
    ],
    'Conseil & Audit' => [
        ['nom' => 'Audit Comptable & Fiscal Annuel', 'type' => 'service', 'prix_achat' => 0, 'prix_vente' => 450000, 'unite' => 'Forfait'],
        ['nom' => 'Honoraire Comptable', 'type' => 'service', 'prix_achat' => 0, 'prix_vente' => 204082, 'unite' => 'Mois'],
    ],
    'Formations Techniques' => [
        ['nom' => 'Formation Logiciel Comptable', 'type' => 'service', 'prix_achat' => 0, 'prix_vente' => 75000, 'unite' => 'Participant'],
    ],
    'Maintenance & Support' => [
        ['nom' => 'Contrat de Maintenance Informatique', 'type' => 'service', 'prix_achat' => 0, 'prix_vente' => 120000, 'unite' => 'Mois'],
    ],
    'Développement Logiciel' => [
        ['nom' => 'Développement Module Selflow Custom', 'type' => 'service', 'prix_achat' => 0, 'prix_vente' => 850000, 'unite' => 'Forfait'],
    ],
    'Assistance Administrative' => [
        ['nom' => 'Saisie & Archivage Numérique', 'type' => 'service', 'prix_achat' => 0, 'prix_vente' => 15000, 'unite' => 'Heure'],
    ]
];

foreach ($entreprisesInfos as $info) {
    $entData = $info['entreprise'];
    $adminData = $info['admin'];
    
    echo "🏢 Création de l'entreprise « {$entData['nom']} »...\n";
    $entreprise = Entreprise::create($entData);
    
    echo "📍 Création du Point de Vente « Siège » pour {$entreprise->nom}...\n";
    $pdv = PointDeVente::create([
        'entreprise_id' => $entreprise->id,
        'nom'           => 'Siège',
        'ville'         => 'Abidjan',
        'commune'       => 'Cocody',
        'responsable'   => "Responsable Général {$entData['gerant_nom']}",
        'telephone'     => $entData['telephone'],
        'statut'        => 'Ouvert',
    ]);
    
    echo "👤 Création de l'Administrateur associé...\n";
    $user = Utilisateur::create([
        'entreprise_id'         => $entreprise->id,
        'point_de_vente_id'     => $pdv->id,
        'nom'                   => $adminData['nom'],
        'prenom'                => $adminData['prenom'],
        'email'                 => $adminData['email'],
        'password'              => Hash::make($adminData['password']),
        'role'                  => $adminData['role'],
        'statut'                => $adminData['statut'],
        'doit_changer_password' => false,
    ]);
    
    echo "📚 Création des 15 catégories et des produits/services...\n";
    foreach ($structureCategories as $secteur => $cats) {
        foreach ($cats as $c) {
            $category = Categorie::create([
                'entreprise_id' => $entreprise->id,
                'nom'           => $c['nom'],
                'prefixe'       => $c['prefix'],
            ]);
            
            // Ajouter les produits correspondants
            if (isset($produitsTypes[$c['nom']])) {
                foreach ($produitsTypes[$c['nom']] as $p) {
                    $prod = Produit::create([
                        'entreprise_id' => $entreprise->id,
                        'nom'           => $p['nom'],
                        'type'          => $p['type'],
                        'categorie_id'  => $category->id,
                        'unite'         => $p['unite'],
                        'prix_achat'    => $p['prix_achat'],
                        'prix_vente'    => $p['prix_vente'],
                        'taux_tva'      => 18.00,
                        'statut'        => 'actif',
                    ]);
                    
                    // Stock initial pour les produits stockables
                    if ($prod->estStockable()) {
                        Stock::create([
                            'produit_id'        => $prod->id,
                            'point_de_vente_id' => $pdv->id,
                            'quantite_disponible' => 50,
                            'stock_minimum'       => 5,
                            'stock_maximum'       => 200,
                        ]);
                    }
                }
            }
        }
    }
    
    echo "✅ Entreprise « {$entreprise->nom} » entièrement configurée !\n\n";
}

echo "✨ Script de peuplement exécuté avec succès en local !\n";
