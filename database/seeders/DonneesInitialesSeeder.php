<?php

namespace Database\Seeders;

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\Fournisseur;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Authentification\Modeles\Utilisateur;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class DonneesInitialesSeeder extends Seeder
{
    /**
     * Peupler la base de données avec les données initiales.
     */
    public function run(): void
    {
        // Désactiver les contraintes de clés étrangères
        Schema::disableForeignKeyConstraints();

        // Nettoyer les tables comptables et opérationnelles
        DB::table('plan_comptable')->truncate();
        DB::table('ecritures_comptables')->truncate();
        DB::table('tresorerie_journal')->truncate();
        DB::table('mouvements_stock')->truncate();
        DB::table('vente_details')->truncate();
        DB::table('ventes')->truncate();
        DB::table('achat_details')->truncate();
        DB::table('achats')->truncate();
        DB::table('stocks')->truncate();
        DB::table('produits')->truncate();
        DB::table('categories')->truncate();
        DB::table('clients')->truncate();
        DB::table('fournisseurs')->truncate();
        DB::table('utilisateurs')->truncate();
        DB::table('points_de_vente')->truncate();
        DB::table('entreprises')->truncate();

        // Récupérer ou créer l'entreprise de démo
        $entreprise = Entreprise::firstOrCreate(
            ['email' => 'contact@maisondupont.ci'],
            [
                'nom'                    => 'Maison Dupont SARL',
                'adresse'                => 'Immeuble Dupont, Boulevard Latrille, Cocody, Abidjan',
                'telephone'              => '+225 27 22 10 00',
                'rccm'                   => 'CI-ABJ-2019-B-12345',
                'compte_contribuable'    => 'CI0123456789',
                'quota_points_de_vente'  => 5,
                'plan_abonnement'        => 'Pro',
                'secteur_activite'       => ['Commercial', 'Services'],
                'modules_actifs'         => ['principal', 'ventes', 'achats', 'stock', 'comptabilite', 'points_de_vente', 'produits', 'tiers', 'rapports', 'b2b', 'fne'],
            ]
        );

        $entreprise2 = Entreprise::firstOrCreate(
            ['email' => 'contact@b2bagro.ci'],
            [
                'nom'                    => 'B2B Agro Fournitures',
                'adresse'                => 'Boulevard des Martyrs, Cocody, Abidjan',
                'telephone'              => '+225 27 22 99 99',
                'rccm'                   => 'CI-ABJ-2026-B-99999',
                'compte_contribuable'    => 'CI9876543210',
                'quota_points_de_vente'  => 5,
                'plan_abonnement'        => 'Pro',
                'secteur_activite'       => ['Commercial'],
                'modules_actifs'         => ['principal', 'ventes', 'achats', 'stock', 'comptabilite', 'points_de_vente', 'produits', 'tiers', 'rapports', 'b2b', 'fne'],
            ]
        );

        // Points de vente
        $pdv1 = PointDeVente::firstOrCreate(
            ['nom' => 'Agence Centrale', 'entreprise_id' => $entreprise->id],
            [
                'ville'         => 'Abidjan',
                'commune'       => 'Plateau',
                'responsable'   => 'Koné Eric',
                'telephone'     => '+225 27 00 01 01',
                'statut'        => 'Ouvert',
            ]
        );

        $pdv2 = PointDeVente::firstOrCreate(
            ['nom' => 'Annexe Cocody', 'entreprise_id' => $entreprise->id],
            [
                'ville'         => 'Abidjan',
                'commune'       => 'Cocody',
                'responsable'   => 'Diallo Awa',
                'telephone'     => '+225 27 00 02 02',
                'statut'        => 'Ouvert',
            ]
        );

        $pdvEntreprise2 = PointDeVente::firstOrCreate(
            ['nom' => 'Boutique Plateau', 'entreprise_id' => $entreprise2->id],
            [
                'ville'         => 'Abidjan',
                'commune'       => 'Plateau',
                'responsable'   => 'Koffi Paul',
                'telephone'     => '+225 27 00 99 99',
                'statut'        => 'Ouvert',
            ]
        );

        // Utilisateurs
        Utilisateur::firstOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'nom'                => 'Super Administrateur',
                'password'           => Hash::make('12345678SUPER'),
                'role'               => 'superadmin',
                'statut'             => 'actif',
            ]
        );

        Utilisateur::firstOrCreate(
            ['email' => 'admin@gmail.com'],
            [
                'entreprise_id'      => $entreprise->id,
                'nom'                => 'Administrateur',
                'password'           => Hash::make('12345678'),
                'role'               => 'admin',
                'statut'             => 'actif',
            ]
        );

        Utilisateur::firstOrCreate(
            ['email' => 'admin3@gmail.com'],
            [
                'entreprise_id'      => $entreprise2->id,
                'point_de_vente_id'  => $pdvEntreprise2->id,
                'nom'                => 'Admin B2B Agro',
                'password'           => Hash::make('12345678'),
                'role'               => 'admin',
                'statut'             => 'actif',
            ]
        );

        Utilisateur::firstOrCreate(
            ['email' => 'vente@gmail.com'],
            [
                'entreprise_id'      => $entreprise->id,
                'point_de_vente_id'  => $pdv1->id,
                'nom'                => 'Koné Fatou',
                'password'           => Hash::make('12345678'),
                'role'               => 'caissier',
                'statut'             => 'actif',
            ]
        );

        // -----------------------------------------------------------------------
        // Seed Plan Comptable SYSCOHADA (Classes 1 à 9)
        // -----------------------------------------------------------------------
        $syscohada = [
            ['numero' => '101000', 'libelle' => 'Capital social'],
            ['numero' => '241100', 'libelle' => 'Matériel de transport'],
            ['numero' => '244000', 'libelle' => 'Matériel et mobilier de bureau'],
            ['numero' => '311000', 'libelle' => 'Marchandises (Stock)'],
            ['numero' => '401000', 'libelle' => 'Fournisseurs - Dettes en compte (Général)'],
            ['numero' => '401000', 'libelle' => 'Fournisseurs d\'exploitation'],
            ['numero' => '411000', 'libelle' => 'Clients - Créances en compte (Général)'],
            ['numero' => '411000', 'libelle' => 'Clients d\'exploitation'],
            ['numero' => '443100', 'libelle' => 'TVA facturée sur ventes (18%)'],
            ['numero' => '445100', 'libelle' => 'TVA récupérable sur achats'],
            ['numero' => '521000', 'libelle' => 'Banques locales (BQE)'],
            ['numero' => '571000', 'libelle' => 'Caisse (CAI)'],
            ['numero' => '601000', 'libelle' => 'Achat de marchandises'],
            ['numero' => '601500', 'libelle' => 'Frais accessoires d\'achat'],
            ['numero' => '605100', 'libelle' => 'Fournitures non stockables (Eau, Électricité)'],
            ['numero' => '701000', 'libelle' => 'Vente de marchandises dans la région'],
            ['numero' => '701200', 'libelle' => 'Vente de marchandises hors région'],
            ['numero' => '810000', 'libelle' => 'Valeurs comptables des cessions d\'actifs'],
            ['numero' => '900000', 'libelle' => 'Comptabilité analytique'],
        ];

        foreach ($syscohada as $compte) {
            \App\Modules\Admin\Modeles\PlanComptable::create(array_merge($compte, ['entreprise_id' => $entreprise->id]));
            \App\Modules\Admin\Modeles\PlanComptable::create(array_merge($compte, ['entreprise_id' => $entreprise2->id]));
        }

        // -----------------------------------------------------------------------
        // Seed Produits avec comptes par défaut pour les deux entreprises
        // -----------------------------------------------------------------------
        $produits = [
            ['reference' => 'ART-001', 'nom' => 'Huile Dinor 1L',       'categorie' => 'Épicerie',  'prix_achat' => 900,  'prix_vente' => 1200, 'stock_actuel' => 42, 'stock_minimum' => 10, 'compte_vente' => '701000', 'compte_achat' => '601000'],
            ['reference' => 'ART-002', 'nom' => 'Sucre 1kg',            'categorie' => 'Épicerie',  'prix_achat' => 550,  'prix_vente' => 750,  'stock_actuel' => 80, 'stock_minimum' => 15, 'compte_vente' => '701000', 'compte_achat' => '601000'],
            ['reference' => 'ART-003', 'nom' => 'Lait en poudre 500g',  'categorie' => 'Épicerie',  'prix_achat' => 2000, 'prix_vente' => 2500, 'stock_actuel' => 5,  'stock_minimum' => 8,  'compte_vente' => '701000', 'compte_achat' => '601000'],
            ['reference' => 'ART-004', 'nom' => 'Savon Palmolive',      'categorie' => 'Hygiène',   'prix_achat' => 400,  'prix_vente' => 600,  'stock_actuel' => 34, 'stock_minimum' => 10, 'compte_vente' => '701000', 'compte_achat' => '601000'],
            ['reference' => 'ART-005', 'nom' => 'Eau minérale 1.5L',    'categorie' => 'Boissons',  'prix_achat' => 300,  'prix_vente' => 450,  'stock_actuel' => 120,'stock_minimum' => 20, 'compte_vente' => '701000', 'compte_achat' => '601000'],
            ['reference' => 'ART-006', 'nom' => 'Riz parfumé 5kg',      'categorie' => 'Épicerie',  'prix_achat' => 4000, 'prix_vente' => 5000, 'stock_actuel' => 18, 'stock_minimum' => 5,  'compte_vente' => '701000', 'compte_achat' => '601000'],
        ];

        foreach ($produits as $donnees) {
            $stockActuel  = $donnees['stock_actuel'];
            $stockMinimum = $donnees['stock_minimum'];
            $nomCategorie = $donnees['categorie'];

            unset($donnees['stock_actuel']);
            unset($donnees['stock_minimum']);
            unset($donnees['categorie']);

            // 1. Pour l'entreprise 1 (Maison Dupont)
            $cat = \App\Modules\Admin\Modeles\Categorie::firstOrCreate([
                'entreprise_id' => $entreprise->id,
                'nom'           => $nomCategorie,
            ], [
                'prefixe'       => strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $nomCategorie), 0, 4)),
            ]);

            $prod = Produit::create(array_merge($donnees, [
                'entreprise_id' => $entreprise->id, 
                'type' => 'marchandise', 
                'taux_tva' => 18.00,
                'categorie_id' => $cat->id
            ]));

            \App\Modules\Admin\Modeles\Stock::create([
                'produit_id'          => $prod->id,
                'point_de_vente_id'   => $pdv1->id,
                'quantite_disponible' => $stockActuel,
                'stock_minimum'       => $stockMinimum,
                'stock_maximum'       => 150,
            ]);

            \App\Modules\Admin\Modeles\Stock::create([
                'produit_id'          => $prod->id,
                'point_de_vente_id'   => $pdv2->id,
                'quantite_disponible' => intval(round($stockActuel / 2)),
                'stock_minimum'       => $stockMinimum,
                'stock_maximum'       => 150,
            ]);

            // 2. Pour l'entreprise 2 (B2B Agro)
            $cat2 = \App\Modules\Admin\Modeles\Categorie::firstOrCreate([
                'entreprise_id' => $entreprise2->id,
                'nom'           => $nomCategorie,
            ], [
                'prefixe'       => strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $nomCategorie), 0, 4)),
            ]);

            $prod2 = Produit::create(array_merge($donnees, [
                'entreprise_id' => $entreprise2->id, 
                'type' => 'marchandise', 
                'taux_tva' => 18.00,
                'categorie_id' => $cat2->id
            ]));

            \App\Modules\Admin\Modeles\Stock::create([
                'produit_id'          => $prod2->id,
                'point_de_vente_id'   => $pdvEntreprise2->id,
                'quantite_disponible' => $stockActuel,
                'stock_minimum'       => $stockMinimum,
                'stock_maximum'       => 150,
            ]);
        }

        // -----------------------------------------------------------------------
        // Seed Clients et Fournisseurs croisés B2B + Clients/Fournisseurs standards
        // -----------------------------------------------------------------------
        // Entreprise 1 (Maison Dupont)
        Client::create(['entreprise_id' => $entreprise->id, 'nom' => 'Koffi Amos',     'telephone' => '+225 07 11 22 33', 'email' => 'koffi@mail.com',  'adresse' => 'Cocody, Abidjan', 'ncc' => '1982341 X', 'regime_imposition' => 'RNI', 'compte_comptable' => '411000', 'numero_tiers' => '411001']);
        Client::create(['entreprise_id' => $entreprise->id, 'nom' => 'Diallo Mariam',  'telephone' => '+225 07 44 55 66', 'email' => 'diallo@mail.com', 'adresse' => 'Plateau, Abidjan', 'ncc' => '1982342 X', 'regime_imposition' => 'RNI', 'compte_comptable' => '411000', 'numero_tiers' => '411002']);
        // Client croisé B2B lié à l'Entreprise 2 (NCC B2B Agro = CI9876543210)
        Client::create(['entreprise_id' => $entreprise->id, 'nom' => 'B2B Agro Fournitures (Client B2B)', 'telephone' => '+225 27 22 99 99', 'email' => 'contact@b2bagro.ci', 'adresse' => 'Abidjan', 'ncc' => 'CI9876543210', 'regime_imposition' => 'RNI', 'compte_comptable' => '411000', 'numero_tiers' => '411003']);

        Fournisseur::create(['entreprise_id' => $entreprise->id, 'nom' => 'CDCI Distribution', 'telephone' => '+225 27 20 01 01', 'email' => 'contact@cdci.ci',   'adresse' => 'Abidjan, Treichville', 'ncc' => '2019871 Y', 'regime_imposition' => 'RSI', 'secteur' => 'Alimentation', 'compte_comptable' => '401000', 'numero_tiers' => '401001']);
        // Fournisseur croisé B2B lié à l'Entreprise 2 (NCC B2B Agro = CI9876543210)
        Fournisseur::create(['entreprise_id' => $entreprise->id, 'nom' => 'B2B Agro Fournitures (Fournisseur B2B)', 'telephone' => '+225 27 22 99 99', 'email' => 'contact@b2bagro.ci', 'adresse' => 'Abidjan', 'ncc' => 'CI9876543210', 'regime_imposition' => 'RSI', 'secteur' => 'Alimentation', 'compte_comptable' => '401000', 'numero_tiers' => '401002']);

        // Entreprise 2 (B2B Agro)
        Client::create(['entreprise_id' => $entreprise2->id, 'nom' => 'Diallo Awa',     'telephone' => '+225 07 55 66 77', 'email' => 'awa@mail.com',  'adresse' => 'Cocody, Abidjan', 'ncc' => '2982341 X', 'regime_imposition' => 'RNI', 'compte_comptable' => '411000', 'numero_tiers' => '411011']);
        // Client croisé B2B lié à l'Entreprise 1 (NCC Maison Dupont = CI0123456789)
        Client::create(['entreprise_id' => $entreprise2->id, 'nom' => 'Maison Dupont SARL (Client B2B)', 'telephone' => '+225 27 22 10 00', 'email' => 'contact@maisondupont.ci', 'adresse' => 'Abidjan', 'ncc' => 'CI0123456789', 'regime_imposition' => 'RNI', 'compte_comptable' => '411000', 'numero_tiers' => '411012']);
 
        Fournisseur::create(['entreprise_id' => $entreprise2->id, 'nom' => 'ProFoods CI',        'telephone' => '+225 27 20 02 02', 'email' => 'info@profoods.ci',  'adresse' => 'Abidjan, Zone 3', 'ncc' => '2019872 Y', 'regime_imposition' => 'RSI', 'secteur' => 'Alimentation', 'compte_comptable' => '401000', 'numero_tiers' => '401011']);
        // Fournisseur croisé B2B lié à l'Entreprise 1 (NCC Maison Dupont = CI0123456789)
        Fournisseur::create(['entreprise_id' => $entreprise2->id, 'nom' => 'Maison Dupont SARL (Fournisseur B2B)', 'telephone' => '+225 27 22 10 00', 'email' => 'contact@maisondupont.ci', 'adresse' => 'Abidjan', 'ncc' => 'CI0123456789', 'regime_imposition' => 'RSI', 'secteur' => 'Alimentation', 'compte_comptable' => '401000', 'numero_tiers' => '401012']);

        // Réactiver les contraintes de clés étrangères
        Schema::enableForeignKeyConstraints();
    }
}
