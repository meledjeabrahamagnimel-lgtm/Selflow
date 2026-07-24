<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SeedMassiveCommand extends Command
{
    protected $signature = 'selflow:seed-massive';
    protected $description = 'Vide completement la base de donnees et la peuple de donnees massives de test ultra-realistes pour les deux entreprises.';

    public function handle(): int
    {
        $this->info('🚀 Demarrage de la purge et de la repopulation massive de la base de donnees...');

        // 1. Purge complète de la base de données
        Schema::disableForeignKeyConstraints();
        
        $tables = [
            'b2b_negotiations',
            'bon_livraison_details',
            'bons_livraison',
            'vente_details',
            'ventes',
            'achat_details',
            'achats',
            'ordres_production',
            'fiche_technique_details',
            'fiches_techniques',
            'stocks',
            'mouvements_stock',
            'tresorerie_journal',
            'ecritures_comptables',
            'operations',
            'plan_comptable',
            'codes_journaux',
            'produits',
            'categories',
            'clients',
            'fournisseurs',
            'utilisateurs',
            'points_de_vente',
            'periodes',
            'entreprises',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table)) {
                $this->info("🧹 Vidage de la table : {$table}");
                DB::table($table)->truncate();
            }
        }
        
        Schema::enableForeignKeyConstraints();
        $this->info('✅ Purge terminee avec succes !');

        // 2. Creation des 2 entreprises
        $this->info('🏢 Creation des deux entreprises...');
        
        $ent1 = DB::table('entreprises')->insertGetId([
            'nom' => 'Maison Dupont SARL',
            'adresse' => 'Immeuble Dupont, Boulevard Latrille, Cocody, Abidjan',
            'telephone' => '+225 27 22 10 00',
            'email' => 'contact@maisondupont.ci',
            'rccm' => 'CI-ABJ-2019-B-12345',
            'compte_contribuable' => 'CI0123456789',
            'ncc' => '1234567 B',
            'forme_juridique' => 'SARL',
            'gerant_nom' => 'Dupont',
            'gerant_prenom' => 'Jean-Marc',
            'gerant_fonction' => 'Directeur General',
            'regime_imposition' => 'RNI',
            'centre_impots' => 'Cocody 1',
            'ref_bancaire' => 'SGBCI CI083 01001 12345678901 23',
            'logo_path' => 'https://images.unsplash.com/photo-1572021335469-31706a17aaef?w=400',
            'quota_points_de_vente' => 10,
            'plan_abonnement' => 'Pro',
            'secteur_activite' => json_encode(['Commercial', 'Services', 'Industriel']),
            'modules_actifs' => json_encode(['principal', 'ventes', 'achats', 'stock', 'comptabilite', 'points_de_vente', 'produits', 'tiers', 'rapports', 'b2b', 'fne', 'production']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $ent2 = DB::table('entreprises')->insertGetId([
            'nom' => 'B2B Agro Fournitures',
            'adresse' => 'Boulevard des Martyrs, Cocody, Abidjan',
            'telephone' => '+225 27 22 99 99',
            'email' => 'contact@b2bagro.ci',
            'rccm' => 'CI-ABJ-2026-B-99999',
            'compte_contribuable' => 'CI9876543210',
            'ncc' => '9876543 A',
            'forme_juridique' => 'SA',
            'gerant_nom' => 'Koffi',
            'gerant_prenom' => 'Kouame Pierre',
            'gerant_fonction' => 'President du Conseil',
            'regime_imposition' => 'RNI',
            'centre_impots' => 'Plateau 2',
            'ref_bancaire' => 'BNI CI092 01002 98765432109 87',
            'logo_path' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=400',
            'quota_points_de_vente' => 10,
            'plan_abonnement' => 'Pro',
            'secteur_activite' => json_encode(['Commercial', 'Services', 'Industriel']),
            'modules_actifs' => json_encode(['principal', 'ventes', 'achats', 'stock', 'comptabilite', 'points_de_vente', 'produits', 'tiers', 'rapports', 'b2b', 'fne', 'production']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Creation des periodes comptables actives (Exercice 2026)
        DB::table('periodes')->insert([
            [
                'entreprise_id' => $ent1,
                'nom' => 'Exercice 2026',
                'date_debut' => '2026-01-01',
                'date_fin' => '2026-12-31',
                'est_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'entreprise_id' => $ent2,
                'nom' => 'Exercice 2026',
                'date_debut' => '2026-01-01',
                'date_fin' => '2026-12-31',
                'est_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);

        // 4. Creation de 4 points de vente par entreprise
        $this->info('🏪 Creation des points de vente...');
        $pdvIds1 = [];
        $pdvIds2 = [];

        $pdvNames1 = ['Agence Centrale', 'Annexe Cocody', 'Showroom Zone 4', 'Boutique Yopougon'];
        foreach ($pdvNames1 as $name) {
            $pdvIds1[] = DB::table('points_de_vente')->insertGetId([
                'entreprise_id' => $ent1,
                'nom' => $name,
                'ville' => 'Abidjan',
                'commune' => 'Cocody',
                'responsable' => 'Responsable ' . $name,
                'telephone' => '+225 27 00 00 ' . rand(10, 99),
                'statut' => 'Ouvert',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $pdvNames2 = ['Boutique Plateau', 'Depot San Pedro', 'Agence Bouake', 'Point Vente Yamoussoukro'];
        foreach ($pdvNames2 as $name) {
            $pdvIds2[] = DB::table('points_de_vente')->insertGetId([
                'entreprise_id' => $ent2,
                'nom' => $name,
                'ville' => 'Abidjan',
                'commune' => 'Plateau',
                'responsable' => 'Responsable ' . $name,
                'telephone' => '+225 27 00 00 ' . rand(10, 99),
                'statut' => 'Ouvert',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 5. Creation des utilisateurs (Superadmin, Admins, Caissiers)
        $this->info('👥 Creation des utilisateurs...');
        $passwordHash = Hash::make('12345678');
        $superHash = Hash::make('12345678SUPER');

        // Superadmin
        DB::table('utilisateurs')->insert([
            'nom' => 'Super Administrateur',
            'email' => 'superadmin@gmail.com',
            'password' => $superHash,
            'role' => 'superadmin',
            'statut' => 'actif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Admins des entreprises
        $admin1 = DB::table('utilisateurs')->insertGetId([
            'entreprise_id' => $ent1,
            'nom' => 'Dupont',
            'prenom' => 'Jean-Marc',
            'email' => 'admin@gmail.com',
            'password' => $passwordHash,
            'role' => 'admin',
            'statut' => 'actif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $admin2 = DB::table('utilisateurs')->insertGetId([
            'entreprise_id' => $ent2,
            'nom' => 'Agro',
            'prenom' => 'Admin',
            'email' => 'admin3@gmail.com',
            'password' => $passwordHash,
            'role' => 'admin',
            'statut' => 'actif',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Caissiers et responsables par PDV
        $caissiersPdv1 = [];
        $caissiersPdv2 = [];

        foreach ($pdvIds1 as $pdvId) {
            // Responsable
            DB::table('utilisateurs')->insert([
                'entreprise_id' => $ent1,
                'point_de_vente_id' => $pdvId,
                'nom' => 'Resp_' . $pdvId,
                'prenom' => 'PDV',
                'email' => "resp_pdv_{$pdvId}@maisondupont.ci",
                'password' => $passwordHash,
                'role' => 'responsable_pdv',
                'statut' => 'actif',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Caissier 1
            $c1 = DB::table('utilisateurs')->insertGetId([
                'entreprise_id' => $ent1,
                'point_de_vente_id' => $pdvId,
                'nom' => 'Caissier1_' . $pdvId,
                'prenom' => 'Fatou',
                'email' => "caissier1_{$pdvId}@maisondupont.ci",
                'password' => $passwordHash,
                'role' => 'caissier',
                'statut' => 'actif',
                'habilitations' => json_encode(['saisie_vente', 'gestion_caisse', 'annulation_ticket']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $caissiersPdv1[] = $c1;
        }

        foreach ($pdvIds2 as $pdvId) {
            // Responsable
            DB::table('utilisateurs')->insert([
                'entreprise_id' => $ent2,
                'point_de_vente_id' => $pdvId,
                'nom' => 'Resp_' . $pdvId,
                'prenom' => 'PDV',
                'email' => "resp_pdv_{$pdvId}@b2bagro.ci",
                'password' => $passwordHash,
                'role' => 'responsable_pdv',
                'statut' => 'actif',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Caissier 1
            $c2 = DB::table('utilisateurs')->insertGetId([
                'entreprise_id' => $ent2,
                'point_de_vente_id' => $pdvId,
                'nom' => 'Caissier1_' . $pdvId,
                'prenom' => 'Awa',
                'email' => "caissier1_{$pdvId}@b2bagro.ci",
                'password' => $passwordHash,
                'role' => 'caissier',
                'statut' => 'actif',
                'habilitations' => json_encode(['saisie_vente', 'gestion_caisse', 'annulation_ticket']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $caissiersPdv2[] = $c2;
        }

        // 6. Plan Comptable SYSCOHADA (16 comptes reels standards)
        $this->info('📊 Peuplement du Plan Comptable...');
        $syscohada = [
            ['numero' => '101000', 'libelle' => 'Capital social'],
            ['numero' => '218200', 'libelle' => 'Materiel de transport'],
            ['numero' => '311000', 'libelle' => 'Marchandises (Stock)'],
            ['numero' => '312000', 'libelle' => 'Produits Finis (Stock)'],
            ['numero' => '401000', 'libelle' => 'Fournisseurs - Dettes en compte'],
            ['numero' => '411000', 'libelle' => 'Clients - Creances en compte'],
            ['numero' => '443100', 'libelle' => 'TVA facturee sur ventes (18%)'],
            ['numero' => '445200', 'libelle' => 'TVA recuperable sur achats'],
            ['numero' => '445500', 'libelle' => 'TVA a decaisser'],
            ['numero' => '521000', 'libelle' => 'Banques locales (BNI/SGBCI)'],
            ['numero' => '571000', 'libelle' => 'Caisse Centrale'],
            ['numero' => '601000', 'libelle' => 'Achat de marchandises'],
            ['numero' => '602100', 'libelle' => 'Achat de matieres premieres'],
            ['numero' => '603200', 'libelle' => 'Variation de stock de matieres premieres'],
            ['numero' => '701000', 'libelle' => 'Vente de marchandises'],
            ['numero' => '731100', 'libelle' => 'Variation de stock de produits finis'],
        ];

        foreach ($syscohada as $compte) {
            DB::table('plan_comptable')->insert([
                array_merge($compte, ['entreprise_id' => $ent1, 'source' => 'comptaflow', 'created_at' => now(), 'updated_at' => now()]),
                array_merge($compte, ['entreprise_id' => $ent2, 'source' => 'comptaflow', 'created_at' => now(), 'updated_at' => now()]),
            ]);
        }

        // 7. Journaux de tresorerie et comptabilite
        $this->info('📓 Peuplement des codes journaux...');
        $journaux = [
            ['code' => 'VTE', 'type' => 'Vente', 'intitule' => 'Journal des Ventes', 'compte' => '411000'],
            ['code' => 'ACH', 'type' => 'Achat', 'intitule' => 'Journal des Achats', 'compte' => '401000'],
            ['code' => 'OD', 'type' => 'Autre', 'intitule' => 'Journal des Operations Diverses', 'compte' => '101000'],
            ['code' => 'CAI', 'type' => 'Trésorerie', 'intitule' => 'Caisse Centrale', 'compte' => '571000'],
            ['code' => 'BQ_BNI', 'type' => 'Trésorerie', 'intitule' => 'Banque BNI', 'compte' => '521000'],
            ['code' => 'BQ_SGB', 'type' => 'Trésorerie', 'intitule' => 'Banque SGBCI', 'compte' => '521000'],
            ['code' => 'OM', 'type' => 'Trésorerie', 'intitule' => 'Orange Money', 'compte' => '571000'],
            ['code' => 'MTN', 'type' => 'Trésorerie', 'intitule' => 'MTN Mobile Money', 'compte' => '571000'],
            ['code' => 'MOOV', 'type' => 'Trésorerie', 'intitule' => 'Moov Money', 'compte' => '571000'],
            ['code' => 'WAVE', 'type' => 'Trésorerie', 'intitule' => 'Wave Money', 'compte' => '571000'],
        ];

        foreach ($journaux as $j) {
            DB::table('codes_journaux')->insert([
                array_merge($j, ['entreprise_id' => $ent1, 'source' => 'comptaflow', 'created_at' => now(), 'updated_at' => now()]),
                array_merge($j, ['entreprise_id' => $ent2, 'source' => 'comptaflow', 'created_at' => now(), 'updated_at' => now()]),
            ]);
        }

        // 8. Tiers (100 Clients & 100 Fournisseurs par entreprise)
        $this->info('👥 Generation de 100 Clients et 100 Fournisseurs par entreprise...');
        $clients1 = [];
        $clients2 = [];
        $fourn1 = [];
        $fourn2 = [];

        // Generation Clients Ent 1
        for ($i = 1; $i <= 100; $i++) {
            $numTiers = 411000 + $i;
            $clients1[] = DB::table('clients')->insertGetId([
                'entreprise_id' => $ent1,
                'nom' => "Client Dupont #{$i}",
                'telephone' => "+225 070000" . str_pad($i, 4, '0', STR_PAD_LEFT),
                'email' => "client{$i}@maisondupont.ci",
                'adresse' => "Abidjan, Cocody Rue {$i}",
                'ncc' => "NCC-" . str_pad($i, 7, '0', STR_PAD_LEFT) . " X",
                'rccm' => "CI-ABJ-2026-B-" . str_pad($i, 5, '0', STR_PAD_LEFT),
                'regime_imposition' => 'RNI',
                'compte_comptable' => '411000',
                'numero_tiers' => (string)$numTiers,
                'source' => 'comptaflow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Generation Clients Ent 2
        for ($i = 1; $i <= 100; $i++) {
            $numTiers = 411000 + $i;
            $clients2[] = DB::table('clients')->insertGetId([
                'entreprise_id' => $ent2,
                'nom' => "Client Agro #{$i}",
                'telephone' => "+225 050000" . str_pad($i, 4, '0', STR_PAD_LEFT),
                'email' => "client{$i}@b2bagro.ci",
                'adresse' => "Abidjan, Plateau Rue {$i}",
                'ncc' => "NCC-" . str_pad($i, 7, '0', STR_PAD_LEFT) . " A",
                'rccm' => "CI-ABJ-2026-B-" . str_pad($i, 5, '0', STR_PAD_LEFT),
                'regime_imposition' => 'RNI',
                'compte_comptable' => '411000',
                'numero_tiers' => (string)$numTiers,
                'source' => 'comptaflow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Generation Fournisseurs Ent 1
        for ($i = 1; $i <= 100; $i++) {
            $numTiers = 401000 + $i;
            $fourn1[] = DB::table('fournisseurs')->insertGetId([
                'entreprise_id' => $ent1,
                'nom' => "Fournisseur Dupont #{$i}",
                'telephone' => "+225 010000" . str_pad($i, 4, '0', STR_PAD_LEFT),
                'email' => "fournisseur{$i}@maisondupont.ci",
                'adresse' => "Abidjan, Zone 3 Rue {$i}",
                'ncc' => "NCC-" . str_pad($i, 7, '1', STR_PAD_LEFT) . " Y",
                'rccm' => "CI-ABJ-2026-B-" . str_pad($i + 100, 5, '0', STR_PAD_LEFT),
                'regime_imposition' => 'RSI',
                'compte_comptable' => '401000',
                'numero_tiers' => (string)$numTiers,
                'source' => 'comptaflow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Generation Fournisseurs Ent 2
        for ($i = 1; $i <= 100; $i++) {
            $numTiers = 401000 + $i;
            $fourn2[] = DB::table('fournisseurs')->insertGetId([
                'entreprise_id' => $ent2,
                'nom' => "Fournisseur Agro #{$i}",
                'telephone' => "+225 090000" . str_pad($i, 4, '0', STR_PAD_LEFT),
                'email' => "fournisseur{$i}@b2bagro.ci",
                'adresse' => "Abidjan, Zone 4 Rue {$i}",
                'ncc' => "NCC-" . str_pad($i, 7, '2', STR_PAD_LEFT) . " Z",
                'rccm' => "CI-ABJ-2026-B-" . str_pad($i + 100, 5, '0', STR_PAD_LEFT),
                'regime_imposition' => 'RSI',
                'compte_comptable' => '401000',
                'numero_tiers' => (string)$numTiers,
                'source' => 'comptaflow',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 9. Categories et Produits (100 categories & 100 produits par categorie = 10 000 articles)
        $this->info('📦 Generation massive des articles (10 000 par entreprise)...');
        
        $typesProduits = ['marchandise', 'matiere_premiere', 'produit_fini', 'service'];
        $unites = ['Kg', 'Litre', 'Piece', 'Heure', 'Carton', 'Lot'];

        // Entreprise 1
        $catIds1 = [];
        $catsInsert1 = [];
        for ($c = 1; $c <= 100; $c++) {
            $catsInsert1[] = [
                'entreprise_id' => $ent1,
                'nom' => "Categorie Dupont #{$c}",
                'prefixe' => "CAT" . str_pad($c, 3, '0', STR_PAD_LEFT),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('categories')->insert($catsInsert1);
        $catIds1 = DB::table('categories')->where('entreprise_id', $ent1)->pluck('id')->toArray();

        $this->info('  -> Insertion des produits pour l\'entreprise 1 (10 000 produits en cours)...');
        foreach ($catIds1 as $indexCat => $catId) {
            $productsBatch = [];
            for ($p = 1; $p <= 100; $p++) {
                $refIdx = ($indexCat * 100) + $p;
                $type = $typesProduits[rand(0, 3)];
                
                $prixAchat = rand(5, 100) * 100;
                $prixVente = $prixAchat * 1.35;
                
                $productsBatch[] = [
                    'entreprise_id' => $ent1,
                    'reference' => "REF-" . str_pad($refIdx, 6, '0', STR_PAD_LEFT),
                    'nom' => "Produit Dupont #{$refIdx}",
                    'type' => $type,
                    'categorie_id' => $catId,
                    'unite' => $unites[rand(0, 5)],
                    'prix_achat' => $prixAchat,
                    'prix_vente' => $prixVente,
                    'taux_tva' => 18.00,
                    'compte_vente' => '701000',
                    'compte_achat' => $type === 'matiere_premiere' ? '602100' : '601000',
                    'photo' => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('produits')->insert($productsBatch);
        }
        $prodIds1 = DB::table('produits')->where('entreprise_id', $ent1)->pluck('id')->toArray();
        $this->info('  -> Produits de l\'entreprise 1 generes !');

        // Entreprise 2
        $catIds2 = [];
        $catsInsert2 = [];
        for ($c = 1; $c <= 100; $c++) {
            $catsInsert2[] = [
                'entreprise_id' => $ent2,
                'nom' => "Categorie Agro #{$c}",
                'prefixe' => "CAT" . str_pad($c, 3, '0', STR_PAD_LEFT),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('categories')->insert($catsInsert2);
        $catIds2 = DB::table('categories')->where('entreprise_id', $ent2)->pluck('id')->toArray();

        $this->info('  -> Insertion des produits pour l\'entreprise 2 (10 000 produits en cours)...');
        foreach ($catIds2 as $indexCat => $catId) {
            $productsBatch = [];
            for ($p = 1; $p <= 100; $p++) {
                $refIdx = ($indexCat * 100) + $p;
                $type = $typesProduits[rand(0, 3)];
                
                $prixAchat = rand(5, 100) * 100;
                $prixVente = $prixAchat * 1.4;
                
                $productsBatch[] = [
                    'entreprise_id' => $ent2,
                    'reference' => "REF-" . str_pad($refIdx + 10000, 6, '0', STR_PAD_LEFT),
                    'nom' => "Produit Agro #{$refIdx}",
                    'type' => $type,
                    'categorie_id' => $catId,
                    'unite' => $unites[rand(0, 5)],
                    'prix_achat' => $prixAchat,
                    'prix_vente' => $prixVente,
                    'taux_tva' => 18.00,
                    'compte_vente' => '701000',
                    'compte_achat' => $type === 'matiere_premiere' ? '602100' : '601000',
                    'photo' => 'https://images.unsplash.com/photo-1542838132-92c53300491e?w=400',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('produits')->insert($productsBatch);
        }
        $prodIds2 = DB::table('produits')->where('entreprise_id', $ent2)->pluck('id')->toArray();
        $this->info('  -> Produits de l\'entreprise 2 generes !');

        // Attribution des stocks initiaux
        $this->info('📦 Generation des stocks physiques...');
        $stocksToInsert = [];
        
        foreach ($pdvIds1 as $pdvId) {
            $selectedProds = array_rand(array_flip($prodIds1), 300);
            foreach ($selectedProds as $pId) {
                $qte = rand(0, 200);
                $stocksToInsert[] = [
                    'produit_id' => $pId,
                    'point_de_vente_id' => $pdvId,
                    'quantite_disponible' => $qte,
                    'stock_minimum' => 10,
                    'stock_maximum' => 500,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        foreach ($pdvIds2 as $pdvId) {
            $selectedProds = array_rand(array_flip($prodIds2), 300);
            foreach ($selectedProds as $pId) {
                $qte = rand(0, 200);
                $stocksToInsert[] = [
                    'produit_id' => $pId,
                    'point_de_vente_id' => $pdvId,
                    'quantite_disponible' => $qte,
                    'stock_minimum' => 10,
                    'stock_maximum' => 500,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        DB::table('stocks')->insert($stocksToInsert);

        // 10. Module Production (100 Recettes et Ordres de production)
        $this->info('🏭 Generation du module Production (100 FT et 100 ordres)...');
        
        $matieresE1 = DB::table('produits')->where('entreprise_id', $ent1)->where('type', 'matiere_premiere')->pluck('id')->toArray();
        $finisE1 = DB::table('produits')->where('entreprise_id', $ent1)->where('type', 'produit_fini')->pluck('id')->toArray();
        
        $matieresE2 = DB::table('produits')->where('entreprise_id', $ent2)->where('type', 'matiere_premiere')->pluck('id')->toArray();
        $finisE2 = DB::table('produits')->where('entreprise_id', $ent2)->where('type', 'produit_fini')->pluck('id')->toArray();

        // 100 FT + OP pour l'entreprise 1
        for ($i = 0; $i < min(100, count($finisE1)); $i++) {
            $pfId = $finisE1[$i];
            
            $ftId = DB::table('fiches_techniques')->insertGetId([
                'entreprise_id' => $ent1,
                'produit_fini_id' => $pfId,
                'description' => "Recette de production de l'article fini #{$i}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Ajouter 2 ingredients
            if (isset($matieresE1[$i])) {
                DB::table('fiche_technique_details')->insert([
                    [
                        'fiche_technique_id' => $ftId,
                        'ingredient_id' => $matieresE1[$i],
                        'quantite' => 2.50,
                        'unite' => 'Kg',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                ]);
            }
            if (isset($matieresE1[$i + 1])) {
                DB::table('fiche_technique_details')->insert([
                    [
                        'fiche_technique_id' => $ftId,
                        'ingredient_id' => $matieresE1[$i + 1],
                        'quantite' => 1.25,
                        'unite' => 'Litre',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                ]);
            }

            // Creer l'ordre de production
            DB::table('ordres_production')->insert([
                'entreprise_id' => $ent1,
                'point_de_vente_id' => $pdvIds1[rand(0, 3)],
                'produit_fini_id' => $pfId,
                'code_ordre' => "OP-" . date('ymd') . "-" . str_pad($i + 1, 4, '0', STR_PAD_LEFT),
                'quantite_cible' => rand(10, 100),
                'statut' => 'Terminé',
                'date_production' => now()->subDays(rand(1, 180))->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 100 FT + OP pour l'entreprise 2
        for ($i = 0; $i < min(100, count($finisE2)); $i++) {
            $pfId = $finisE2[$i];
            
            $ftId = DB::table('fiches_techniques')->insertGetId([
                'entreprise_id' => $ent2,
                'produit_fini_id' => $pfId,
                'description' => "Recette de production de l'article fini #{$i}",
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Ajouter 2 ingredients
            if (isset($matieresE2[$i])) {
                DB::table('fiche_technique_details')->insert([
                    [
                        'fiche_technique_id' => $ftId,
                        'ingredient_id' => $matieresE2[$i],
                        'quantite' => 3.00,
                        'unite' => 'Kg',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                ]);
            }
            if (isset($matieresE2[$i + 1])) {
                DB::table('fiche_technique_details')->insert([
                    [
                        'fiche_technique_id' => $ftId,
                        'ingredient_id' => $matieresE2[$i + 1],
                        'quantite' => 1.50,
                        'unite' => 'Litre',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                ]);
            }

            // Creer l'ordre de production
            DB::table('ordres_production')->insert([
                'entreprise_id' => $ent2,
                'point_de_vente_id' => $pdvIds2[rand(0, 3)],
                'produit_fini_id' => $pfId,
                'code_ordre' => "OP-" . date('ymd') . "-" . str_pad($i + 101, 4, '0', STR_PAD_LEFT),
                'quantite_cible' => rand(10, 100),
                'statut' => 'Terminé',
                'date_production' => now()->subDays(rand(1, 180))->toDateString(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 11. Ventes (100 transactions par entreprise avec ecritures, operations et cycle de documents)
        $this->info('💰 Generation des ventes et achats massifs (100 de chaque par entreprise)...');
        
        $marchandises1 = DB::table('produits')->where('entreprise_id', $ent1)->where('type', 'marchandise')->pluck('id')->toArray();
        $marchandises2 = DB::table('produits')->where('entreprise_id', $ent2)->where('type', 'marchandise')->pluck('id')->toArray();

        $modes = ['espèces', 'virement', 'crédit', 'mobile_money'];
        $moyens = ['WAVE', 'OM', 'MTN', 'MOOV', 'BNI', 'SGBCI'];

        // Ventes Entreprise 1
        for ($i = 1; $i <= 100; $i++) {
            $clientId = $clients1[rand(0, 99)];
            $pdvId = $pdvIds1[rand(0, 3)];
            $caissierId = $caissiersPdv1[rand(0, 3)];
            $ref = "VTE-" . date('ymd') . "-" . str_pad($i, 4, '0', STR_PAD_LEFT);
            $mode = $modes[rand(0, 3)];
            
            $qte = rand(1, 10);
            $px = rand(10, 100) * 100;
            $ht = $qte * $px;
            $tva = $ht * 0.18;
            $ttc = $ht + $tva;
            
            $date = now()->subDays(rand(1, 180));
            $etape = $i <= 25 ? 'Devis' : ($i <= 50 ? 'Bon de Commande' : ($i <= 75 ? 'Bon de Livraison' : 'Facture'));
            $statut = $mode === 'crédit' ? 'Non payé' : 'Payé';

            $vId = DB::table('ventes')->insertGetId([
                'point_de_vente_id' => $pdvId,
                'utilisateur_id' => $caissierId,
                'client_id' => $clientId,
                'numero_facture' => $ref,
                'date_vente' => $date,
                'mode_paiement' => $mode,
                'moyen_bancaire' => $mode !== 'espèces' && $mode !== 'crédit' ? $moyens[rand(0, 5)] : null,
                'montant_ht' => $ht,
                'montant_tva' => $tva,
                'montant_ttc' => $ttc,
                'statut' => $statut,
                'type_facture' => 'normale',
                'normalise' => true,
                'etape' => $etape,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('vente_details')->insert([
                'vente_id' => $vId,
                'produit_id' => $marchandises1[rand(0, 99)],
                'quantite' => $qte,
                'prix_unitaire' => $px,
                'montant_tva' => $tva,
                'montant_ttc' => $ttc,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($etape === 'Facture') {
                $opId = DB::table('operations')->insertGetId([
                    'entreprise_id' => $ent1,
                    'point_de_vente_id' => $pdvId,
                    'date_operation' => $date,
                    'type_operation' => 'FactureVente',
                    'code_journal' => 'VTE',
                    'numero_saisie' => (string)$i,
                    'reference_document' => $ref,
                    'libelle_general' => "Vente de marchandises - Facture {$ref}",
                    'solde_equilibre' => 0.00,
                    'est_equilibree' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Debit client 411
                DB::table('ecritures_comptables')->insert([
                    'operation_id' => $opId,
                    'entreprise_id' => $ent1,
                    'point_de_vente_id' => $pdvId,
                    'date_ecriture' => $date,
                    'libelle' => "Facturation client - {$ref}",
                    'reference_document' => $ref,
                    'code_journal' => 'VTE',
                    'compte_debit' => '411000',
                    'debit' => $ttc,
                    'credit' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Credit vente 701
                DB::table('ecritures_comptables')->insert([
                    'operation_id' => $opId,
                    'entreprise_id' => $ent1,
                    'point_de_vente_id' => $pdvId,
                    'date_ecriture' => $date,
                    'libelle' => "Produit des ventes - {$ref}",
                    'reference_document' => $ref,
                    'code_journal' => 'VTE',
                    'compte_credit' => '701000',
                    'debit' => 0,
                    'credit' => $ht,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Credit TVA 4431
                if ($tva > 0) {
                    DB::table('ecritures_comptables')->insert([
                        'operation_id' => $opId,
                        'entreprise_id' => $ent1,
                        'point_de_vente_id' => $pdvId,
                        'date_ecriture' => $date,
                        'libelle' => "TVA facturée - {$ref}",
                        'reference_document' => $ref,
                        'code_journal' => 'VTE',
                        'compte_credit' => '443100',
                        'debit' => 0,
                        'credit' => $tva,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if ($statut === 'Payé') {
                    DB::table('tresorerie_journal')->insert([
                        'point_de_vente_id' => $pdvId,
                        'utilisateur_id' => $caissierId,
                        'date_operation' => $date,
                        'type_operation' => 'recette',
                        'libelle' => "Encaissement Facture {$ref}",
                        'mode_paiement' => $mode,
                        'reference_paiement' => "PAI-{$ref}",
                        'montant_entree' => $ttc,
                        'montant_sortie' => 0.00,
                        'solde_resultat' => $ttc,
                        'reference_document' => $ref,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Ventes Entreprise 2
        for ($i = 1; $i <= 100; $i++) {
            $clientId = $clients2[rand(0, 99)];
            $pdvId = $pdvIds2[rand(0, 3)];
            $caissierId = $caissiersPdv2[rand(0, 3)];
            $ref = "VTE-" . date('ymd') . "-" . str_pad($i + 100, 4, '0', STR_PAD_LEFT);
            $mode = $modes[rand(0, 3)];
            
            $qte = rand(1, 10);
            $px = rand(10, 100) * 100;
            $ht = $qte * $px;
            $tva = $ht * 0.18;
            $ttc = $ht + $tva;
            
            $date = now()->subDays(rand(1, 180));
            $etape = $i <= 25 ? 'Devis' : ($i <= 50 ? 'Bon de Commande' : ($i <= 75 ? 'Bon de Livraison' : 'Facture'));
            $statut = $mode === 'crédit' ? 'Non payé' : 'Payé';

            $vId = DB::table('ventes')->insertGetId([
                'point_de_vente_id' => $pdvId,
                'utilisateur_id' => $caissierId,
                'client_id' => $clientId,
                'numero_facture' => $ref,
                'date_vente' => $date,
                'mode_paiement' => $mode,
                'moyen_bancaire' => $mode !== 'espèces' && $mode !== 'crédit' ? $moyens[rand(0, 5)] : null,
                'montant_ht' => $ht,
                'montant_tva' => $tva,
                'montant_ttc' => $ttc,
                'statut' => $statut,
                'type_facture' => 'normale',
                'normalise' => true,
                'etape' => $etape,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('vente_details')->insert([
                'vente_id' => $vId,
                'produit_id' => $marchandises2[rand(0, 99)],
                'quantite' => $qte,
                'prix_unitaire' => $px,
                'montant_tva' => $tva,
                'montant_ttc' => $ttc,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($etape === 'Facture') {
                $opId = DB::table('operations')->insertGetId([
                    'entreprise_id' => $ent2,
                    'point_de_vente_id' => $pdvId,
                    'date_operation' => $date,
                    'type_operation' => 'FactureVente',
                    'code_journal' => 'VTE',
                    'numero_saisie' => (string)$i,
                    'reference_document' => $ref,
                    'libelle_general' => "Vente de marchandises - Facture {$ref}",
                    'solde_equilibre' => 0.00,
                    'est_equilibree' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Debit client 411
                DB::table('ecritures_comptables')->insert([
                    'operation_id' => $opId,
                    'entreprise_id' => $ent2,
                    'point_de_vente_id' => $pdvId,
                    'date_ecriture' => $date,
                    'libelle' => "Facturation client - {$ref}",
                    'reference_document' => $ref,
                    'code_journal' => 'VTE',
                    'compte_debit' => '411000',
                    'debit' => $ttc,
                    'credit' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Credit vente 701
                DB::table('ecritures_comptables')->insert([
                    'operation_id' => $opId,
                    'entreprise_id' => $ent2,
                    'point_de_vente_id' => $pdvId,
                    'date_ecriture' => $date,
                    'libelle' => "Produit des ventes - {$ref}",
                    'reference_document' => $ref,
                    'code_journal' => 'VTE',
                    'compte_credit' => '701000',
                    'debit' => 0,
                    'credit' => $ht,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Credit TVA 4431
                if ($tva > 0) {
                    DB::table('ecritures_comptables')->insert([
                        'operation_id' => $opId,
                        'entreprise_id' => $ent2,
                        'point_de_vente_id' => $pdvId,
                        'date_ecriture' => $date,
                        'libelle' => "TVA facturée - {$ref}",
                        'reference_document' => $ref,
                        'code_journal' => 'VTE',
                        'compte_credit' => '443100',
                        'debit' => 0,
                        'credit' => $tva,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                if ($statut === 'Payé') {
                    DB::table('tresorerie_journal')->insert([
                        'point_de_vente_id' => $pdvId,
                        'utilisateur_id' => $caissierId,
                        'date_operation' => $date,
                        'type_operation' => 'recette',
                        'libelle' => "Encaissement Facture {$ref}",
                        'mode_paiement' => $mode,
                        'reference_paiement' => "PAI-{$ref}",
                        'montant_entree' => $ttc,
                        'montant_sortie' => 0.00,
                        'solde_resultat' => $ttc,
                        'reference_document' => $ref,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Achats Entreprise 1
        for ($i = 1; $i <= 100; $i++) {
            $fournId = $fourn1[rand(0, 99)];
            $pdvId = $pdvIds1[rand(0, 3)];
            $adminUser = $admin1;
            $ref = "ACH-" . date('ymd') . "-" . str_pad($i, 4, '0', STR_PAD_LEFT);
            $mode = $modes[rand(0, 3)];
            
            $qte = rand(10, 50);
            $px = rand(5, 50) * 100;
            $ht = $qte * $px;
            $tva = $ht * 0.18;
            $ttc = $ht + $tva;
            
            $date = now()->subDays(rand(1, 180));
            $etape = $i <= 25 ? 'Demande de prix' : ($i <= 50 ? 'Bon de commande' : 'Facture');
            $statut = $mode === 'crédit' ? 'Non payé' : 'Payé';

            $aId = DB::table('achats')->insertGetId([
                'point_de_vente_id' => $pdvId,
                'utilisateur_id' => $adminUser,
                'fournisseur_id' => $fournId,
                'numero_facture' => $ref,
                'date_achat' => $date,
                'mode_paiement' => $mode,
                'montant_ht' => $ht,
                'montant_tva' => $tva,
                'montant_ttc' => $ttc,
                'statut' => $statut,
                'type_facture' => 'normale',
                'etape' => $etape,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('achat_details')->insert([
                'achat_id' => $aId,
                'produit_id' => $marchandises1[rand(0, 99)],
                'quantite' => $qte,
                'prix_unitaire' => $px,
                'montant_tva' => $tva,
                'montant_ttc' => $ttc,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($etape === 'Facture') {
                $opId = DB::table('operations')->insertGetId([
                    'entreprise_id' => $ent1,
                    'point_de_vente_id' => $pdvId,
                    'date_operation' => $date,
                    'type_operation' => 'FactureAchat',
                    'code_journal' => 'ACH',
                    'numero_saisie' => (string)($i + 200),
                    'reference_document' => $ref,
                    'libelle_general' => "Achat marchandises - Facture {$ref}",
                    'solde_equilibre' => 0.00,
                    'est_equilibree' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Debit achat 601
                DB::table('ecritures_comptables')->insert([
                    'operation_id' => $opId,
                    'entreprise_id' => $ent1,
                    'point_de_vente_id' => $pdvId,
                    'date_ecriture' => $date,
                    'libelle' => "Achat de marchandises - {$ref}",
                    'reference_document' => $ref,
                    'code_journal' => 'ACH',
                    'compte_debit' => '601000',
                    'debit' => $ht,
                    'credit' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Debit TVA 4452
                if ($tva > 0) {
                    DB::table('ecritures_comptables')->insert([
                        'operation_id' => $opId,
                        'entreprise_id' => $ent1,
                        'point_de_vente_id' => $pdvId,
                        'date_ecriture' => $date,
                        'libelle' => "TVA déductible - {$ref}",
                        'reference_document' => $ref,
                        'code_journal' => 'ACH',
                        'compte_debit' => '445200',
                        'debit' => $tva,
                        'credit' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Credit fournisseur 401
                DB::table('ecritures_comptables')->insert([
                    'operation_id' => $opId,
                    'entreprise_id' => $ent1,
                    'point_de_vente_id' => $pdvId,
                    'date_ecriture' => $date,
                    'libelle' => "Dette Fournisseur - {$ref}",
                    'reference_document' => $ref,
                    'code_journal' => 'ACH',
                    'compte_credit' => '401000',
                    'debit' => 0,
                    'credit' => $ttc,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($statut === 'Payé') {
                    DB::table('tresorerie_journal')->insert([
                        'point_de_vente_id' => $pdvId,
                        'utilisateur_id' => $adminUser,
                        'date_operation' => $date,
                        'type_operation' => 'dépense',
                        'libelle' => "Decaissement Achat {$ref}",
                        'mode_paiement' => $mode,
                        'reference_paiement' => "PAI-ACH-{$ref}",
                        'montant_entree' => 0.00,
                        'montant_sortie' => $ttc,
                        'solde_resultat' => -$ttc,
                        'reference_document' => $ref,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // Achats Entreprise 2
        for ($i = 1; $i <= 100; $i++) {
            $fournId = $fourn2[rand(0, 99)];
            $pdvId = $pdvIds2[rand(0, 3)];
            $adminUser = $admin2;
            $ref = "ACH-" . date('ymd') . "-" . str_pad($i + 100, 4, '0', STR_PAD_LEFT);
            $mode = $modes[rand(0, 3)];
            
            $qte = rand(10, 50);
            $px = rand(5, 50) * 100;
            $ht = $qte * $px;
            $tva = $ht * 0.18;
            $ttc = $ht + $tva;
            
            $date = now()->subDays(rand(1, 180));
            $etape = $i <= 25 ? 'Demande de prix' : ($i <= 50 ? 'Bon de commande' : 'Facture');
            $statut = $mode === 'crédit' ? 'Non payé' : 'Payé';

            $aId = DB::table('achats')->insertGetId([
                'point_de_vente_id' => $pdvId,
                'utilisateur_id' => $adminUser,
                'fournisseur_id' => $fournId,
                'numero_facture' => $ref,
                'date_achat' => $date,
                'mode_paiement' => $mode,
                'montant_ht' => $ht,
                'montant_tva' => $tva,
                'montant_ttc' => $ttc,
                'statut' => $statut,
                'type_facture' => 'normale',
                'etape' => $etape,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('achat_details')->insert([
                'achat_id' => $aId,
                'produit_id' => $marchandises2[rand(0, 99)],
                'quantite' => $qte,
                'prix_unitaire' => $px,
                'montant_tva' => $tva,
                'montant_ttc' => $ttc,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($etape === 'Facture') {
                $opId = DB::table('operations')->insertGetId([
                    'entreprise_id' => $ent2,
                    'point_de_vente_id' => $pdvId,
                    'date_operation' => $date,
                    'type_operation' => 'FactureAchat',
                    'code_journal' => 'ACH',
                    'numero_saisie' => (string)($i + 200),
                    'reference_document' => $ref,
                    'libelle_general' => "Achat marchandises - Facture {$ref}",
                    'solde_equilibre' => 0.00,
                    'est_equilibree' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Debit achat 601
                DB::table('ecritures_comptables')->insert([
                    'operation_id' => $opId,
                    'entreprise_id' => $ent2,
                    'point_de_vente_id' => $pdvId,
                    'date_ecriture' => $date,
                    'libelle' => "Achat de marchandises - {$ref}",
                    'reference_document' => $ref,
                    'code_journal' => 'ACH',
                    'compte_debit' => '601000',
                    'debit' => $ht,
                    'credit' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // Debit TVA 4452
                if ($tva > 0) {
                    DB::table('ecritures_comptables')->insert([
                        'operation_id' => $opId,
                        'entreprise_id' => $ent2,
                        'point_de_vente_id' => $pdvId,
                        'date_ecriture' => $date,
                        'libelle' => "TVA déductible - {$ref}",
                        'reference_document' => $ref,
                        'code_journal' => 'ACH',
                        'compte_debit' => '445200',
                        'debit' => $tva,
                        'credit' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Credit fournisseur 401
                DB::table('ecritures_comptables')->insert([
                    'operation_id' => $opId,
                    'entreprise_id' => $ent2,
                    'point_de_vente_id' => $pdvId,
                    'date_ecriture' => $date,
                    'libelle' => "Dette Fournisseur - {$ref}",
                    'reference_document' => $ref,
                    'code_journal' => 'ACH',
                    'compte_credit' => '401000',
                    'debit' => 0,
                    'credit' => $ttc,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($statut === 'Payé') {
                    DB::table('tresorerie_journal')->insert([
                        'point_de_vente_id' => $pdvId,
                        'utilisateur_id' => $adminUser,
                        'date_operation' => $date,
                        'type_operation' => 'dépense',
                        'libelle' => "Decaissement Achat {$ref}",
                        'mode_paiement' => $mode,
                        'reference_paiement' => "PAI-ACH-{$ref}",
                        'montant_entree' => 0.00,
                        'montant_sortie' => $ttc,
                        'solde_resultat' => -$ttc,
                        'reference_document' => $ref,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        // 12. Avoirs (5 Avoirs Clients et 5 Avoirs Fournisseurs par entreprise)
        $this->info('📝 Generation des avoirs...');
        for ($i = 1; $i <= 5; $i++) {
            $ref = "AV-VTE-" . date('ymd') . "-" . str_pad($i, 4, '0', STR_PAD_LEFT);
            $date = now()->subDays(rand(1, 10));
            $ht = rand(10, 50) * 100;
            $tva = $ht * 0.18;
            $ttc = $ht + $tva;
            
            DB::table('ventes')->insert([
                'point_de_vente_id' => $pdvIds1[rand(0, 3)],
                'utilisateur_id' => $admin1,
                'client_id' => $clients1[rand(0, 99)],
                'numero_facture' => $ref,
                'date_vente' => $date,
                'mode_paiement' => 'crédit',
                'montant_ht' => -$ht,
                'montant_tva' => -$tva,
                'montant_ttc' => -$ttc,
                'statut' => 'Non payé',
                'type_facture' => 'avoir',
                'normalise' => true,
                'etape' => 'Facture',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $refAch = "AV-ACH-" . date('ymd') . "-" . str_pad($i, 4, '0', STR_PAD_LEFT);
            DB::table('achats')->insert([
                'point_de_vente_id' => $pdvIds1[rand(0, 3)],
                'utilisateur_id' => $admin1,
                'fournisseur_id' => $fourn1[rand(0, 99)],
                'numero_facture' => $refAch,
                'date_achat' => $date,
                'mode_paiement' => 'crédit',
                'montant_ht' => -$ht,
                'montant_tva' => -$tva,
                'montant_ttc' => -$ttc,
                'statut' => 'Non payé',
                'type_facture' => 'avoir',
                'etape' => 'Facture',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 13. Mouvements de stock
        $this->info('📦 Generation des transferts et mouvements de stock...');
        for ($i = 1; $i <= 50; $i++) {
            $pdvSrc = $pdvIds1[rand(0, 1)];
            $pdvDst = $pdvIds1[rand(2, 3)];
            $prodId = $prodIds1[rand(0, 100)];
            $qte = rand(5, 20);
            
            DB::table('transferts_stock')->insert([
                'point_de_vente_source_id'      => $pdvSrc,
                'point_de_vente_destination_id' => $pdvDst,
                'produit_id'                    => $prodId,
                'quantite'                      => $qte,
                'statut'                        => 'approuve',
                'demandeur_id'                  => $admin1,
                'approbateur_id'                => $admin1,
                'note'                          => "Transfert interne #{$i}",
                'approuve_le'                   => now()->subDays(rand(1, 60)),
                'created_at'                    => now(),
                'updated_at'                    => now(),
            ]);

            DB::table('mouvements_stock')->insert([
                [
                    'produit_id'               => $prodId,
                    'point_de_vente_id'        => $pdvSrc,
                    'type_mouvement'           => 'sortie',
                    'sous_type'                => 'Transfert',
                    'point_de_vente_source_id' => $pdvSrc,
                    'utilisateur_id'           => $admin1,
                    'quantite'                 => $qte,
                    'stock_avant'              => 100,
                    'stock_apres'              => 100 - $qte,
                    'reference_document'       => "TRF-E1-{$i}",
                    'created_at'               => now(),
                    'updated_at'               => now(),
                ],
                [
                    'produit_id'               => $prodId,
                    'point_de_vente_id'        => $pdvDst,
                    'type_mouvement'           => 'entrée',
                    'sous_type'                => 'Transfert',
                    'point_de_vente_source_id' => $pdvSrc,
                    'utilisateur_id'           => $admin1,
                    'quantite'                 => $qte,
                    'stock_avant'              => 50,
                    'stock_apres'              => 50 + $qte,
                    'reference_document'       => "TRF-E1-{$i}",
                    'created_at'               => now(),
                    'updated_at'               => now(),
                ]
            ]);
        }

        // Transferts pour entreprise 2
        for ($i = 1; $i <= 50; $i++) {
            $pdvSrc = $pdvIds2[rand(0, 1)];
            $pdvDst = $pdvIds2[rand(2, 3)];
            $prodId = $prodIds2[rand(0, 100)];
            $qte = rand(5, 20);
            
            DB::table('transferts_stock')->insert([
                'point_de_vente_source_id'      => $pdvSrc,
                'point_de_vente_destination_id' => $pdvDst,
                'produit_id'                    => $prodId,
                'quantite'                      => $qte,
                'statut'                        => 'approuve',
                'demandeur_id'                  => $admin2,
                'approbateur_id'                => $admin2,
                'note'                          => "Transfert interne B2B #{$i}",
                'approuve_le'                   => now()->subDays(rand(1, 60)),
                'created_at'                    => now(),
                'updated_at'                    => now(),
            ]);

            DB::table('mouvements_stock')->insert([
                [
                    'produit_id'               => $prodId,
                    'point_de_vente_id'        => $pdvSrc,
                    'type_mouvement'           => 'sortie',
                    'sous_type'                => 'Transfert',
                    'point_de_vente_source_id' => $pdvSrc,
                    'utilisateur_id'           => $admin2,
                    'quantite'                 => $qte,
                    'stock_avant'              => 100,
                    'stock_apres'              => 100 - $qte,
                    'reference_document'       => "TRF-E2-{$i}",
                    'created_at'               => now(),
                    'updated_at'               => now(),
                ],
                [
                    'produit_id'               => $prodId,
                    'point_de_vente_id'        => $pdvDst,
                    'type_mouvement'           => 'entrée',
                    'sous_type'                => 'Transfert',
                    'point_de_vente_source_id' => $pdvSrc,
                    'utilisateur_id'           => $admin2,
                    'quantite'                 => $qte,
                    'stock_avant'              => 50,
                    'stock_apres'              => 50 + $qte,
                    'reference_document'       => "TRF-E2-{$i}",
                    'created_at'               => now(),
                    'updated_at'               => now(),
                ]
            ]);
        }


        $this->info('🎉 Base de donnees Selflow massive et realiste peuplee avec succes !');
        return 0;
    }
}
