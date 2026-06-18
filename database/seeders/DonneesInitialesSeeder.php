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
        DB::table('produits')->truncate();
        DB::table('clients')->truncate();
        DB::table('fournisseurs')->truncate();

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

        // Utilisateurs
        Utilisateur::firstOrCreate(
            ['email' => 'superadmin@gmail.com'],
            [
                'nom'                => 'Super Administrateur',
                'password'           => Hash::make('12345678'),
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
            ['numero' => '401100', 'libelle' => 'Fournisseurs d\'exploitation'],
            ['numero' => '411000', 'libelle' => 'Clients - Créances en compte (Général)'],
            ['numero' => '411100', 'libelle' => 'Clients d\'exploitation'],
            ['numero' => '443100', 'libelle' => 'TVA facturée sur ventes (18%)'],
            ['numero' => '445100', 'libelle' => 'TVA récupérable sur achats'],
            ['numero' => '521000', 'libelle' => 'Banques locales (BQE)'],
            ['numero' => '571000', 'libelle' => 'Caisse (CAI)'],
            ['numero' => '601100', 'libelle' => 'Achat de marchandises'],
            ['numero' => '601500', 'libelle' => 'Frais accessoires d\'achat'],
            ['numero' => '605100', 'libelle' => 'Fournitures non stockables (Eau, Électricité)'],
            ['numero' => '701100', 'libelle' => 'Vente de marchandises dans la région'],
            ['numero' => '701200', 'libelle' => 'Vente de marchandises hors région'],
            ['numero' => '810000', 'libelle' => 'Valeurs comptables des cessions d\'actifs'],
            ['numero' => '900000', 'libelle' => 'Comptabilité analytique'],
        ];

        foreach ($syscohada as $compte) {
            \App\Modules\Admin\Modeles\PlanComptable::create($compte);
        }

        // -----------------------------------------------------------------------
        // Seed Produits avec comptes par défaut
        // -----------------------------------------------------------------------
        $produits = [
            ['reference' => 'ART-001', 'nom' => 'Huile Dinor 1L',       'categorie' => 'Épicerie',  'prix_achat' => 900,  'prix_vente' => 1200, 'stock_actuel' => 42, 'stock_minimum' => 10, 'compte_vente' => '701100', 'compte_achat' => '601100'],
            ['reference' => 'ART-002', 'nom' => 'Sucre 1kg',            'categorie' => 'Épicerie',  'prix_achat' => 550,  'prix_vente' => 750,  'stock_actuel' => 80, 'stock_minimum' => 15, 'compte_vente' => '701100', 'compte_achat' => '601100'],
            ['reference' => 'ART-003', 'nom' => 'Lait en poudre 500g',  'categorie' => 'Épicerie',  'prix_achat' => 2000, 'prix_vente' => 2500, 'stock_actuel' => 5,  'stock_minimum' => 8,  'compte_vente' => '701100', 'compte_achat' => '601100'],
            ['reference' => 'ART-004', 'nom' => 'Savon Palmolive',      'categorie' => 'Hygiène',   'prix_achat' => 400,  'prix_vente' => 600,  'stock_actuel' => 34, 'stock_minimum' => 10, 'compte_vente' => '701100', 'compte_achat' => '601100'],
            ['reference' => 'ART-005', 'nom' => 'Eau minérale 1.5L',    'categorie' => 'Boissons',  'prix_achat' => 300,  'prix_vente' => 450,  'stock_actuel' => 120,'stock_minimum' => 20, 'compte_vente' => '701100', 'compte_achat' => '601100'],
            ['reference' => 'ART-006', 'nom' => 'Riz parfumé 5kg',      'categorie' => 'Épicerie',  'prix_achat' => 4000, 'prix_vente' => 5000, 'stock_actuel' => 18, 'stock_minimum' => 5,  'compte_vente' => '701100', 'compte_achat' => '601100'],
        ];

        foreach ($produits as $donnees) {
            Produit::create(array_merge($donnees, ['entreprise_id' => $entreprise->id, 'type' => 'stockable', 'taux_tva' => 18.00]));
        }

        // -----------------------------------------------------------------------
        // Seed Clients avec numéros tiers uniques
        // -----------------------------------------------------------------------
        Client::create(['entreprise_id' => $entreprise->id, 'nom' => 'Koffi Amos',     'telephone' => '+225 07 11 22 33', 'email' => 'koffi@mail.com',  'adresse' => 'Cocody, Abidjan', 'ncc' => '1982341 X', 'regime_imposition' => 'RNI', 'compte_comptable' => '411100', 'numero_tiers' => '411001']);
        Client::create(['entreprise_id' => $entreprise->id, 'nom' => 'Diallo Mariam',  'telephone' => '+225 07 44 55 66', 'email' => 'diallo@mail.com', 'adresse' => 'Plateau, Abidjan', 'ncc' => '1982342 X', 'regime_imposition' => 'RNI', 'compte_comptable' => '411100', 'numero_tiers' => '411002']);
        Client::create(['entreprise_id' => $entreprise->id, 'nom' => 'Coulibaly Jean', 'telephone' => '+225 07 77 88 99', 'email' => 'coul@mail.com',   'adresse' => 'Yopougon, Abidjan', 'ncc' => '1982343 X', 'regime_imposition' => 'RNI', 'compte_comptable' => '411100', 'numero_tiers' => '411003']);
        Client::create(['entreprise_id' => $entreprise->id, 'nom' => 'Koné Fatou',     'telephone' => '+225 07 99 10 11', 'email' => 'kone@mail.com',   'adresse' => 'Adjamé, Abidjan', 'ncc' => '1982344 X', 'regime_imposition' => 'RNI', 'compte_comptable' => '411100', 'numero_tiers' => '411004']);
        Client::create(['entreprise_id' => $entreprise->id, 'nom' => 'Bamba Seydou',   'telephone' => '+225 07 22 33 44', 'email' => 'bamba@mail.com',  'adresse' => 'Marcory, Abidjan', 'ncc' => '1982345 X', 'regime_imposition' => 'RNI', 'compte_comptable' => '411100', 'numero_tiers' => '411005']);

        // -----------------------------------------------------------------------
        // Seed Fournisseurs avec numéros tiers uniques
        // -----------------------------------------------------------------------
        Fournisseur::create(['entreprise_id' => $entreprise->id, 'nom' => 'CDCI Distribution', 'telephone' => '+225 27 20 01 01', 'email' => 'contact@cdci.ci',   'adresse' => 'Abidjan, Treichville', 'ncc' => '2019871 Y', 'regime_imposition' => 'RSI', 'secteur' => 'Alimentation', 'compte_comptable' => '401100', 'numero_tiers' => '401001']);
        Fournisseur::create(['entreprise_id' => $entreprise->id, 'nom' => 'ProFoods CI',        'telephone' => '+225 27 20 02 02', 'email' => 'info@profoods.ci',  'adresse' => 'Abidjan, Zone 3', 'ncc' => '2019872 Y', 'regime_imposition' => 'RSI', 'secteur' => 'Alimentation', 'compte_comptable' => '401100', 'numero_tiers' => '401002']);
        Fournisseur::create(['entreprise_id' => $entreprise->id, 'nom' => 'ElecTech Abidjan',   'telephone' => '+225 27 20 03 03', 'email' => 'pro@electech.ci',   'adresse' => 'Abidjan, Zone 4', 'ncc' => '2019873 Y', 'regime_imposition' => 'RSI', 'secteur' => 'Électronique', 'compte_comptable' => '401100', 'numero_tiers' => '401003']);

        // Réactiver les contraintes de clés étrangères
        Schema::enableForeignKeyConstraints();
    }
}
