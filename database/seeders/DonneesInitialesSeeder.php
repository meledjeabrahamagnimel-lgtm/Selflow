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

class DonneesInitialesSeeder extends Seeder
{
    /**
     * Peupler la base de données avec les données initiales.
     */
    public function run(): void
    {
        // -----------------------------------------------------------------------
        // 1. Créer l'entreprise principale (démo)
        // -----------------------------------------------------------------------
        $entreprise = Entreprise::create([
            'nom'                    => 'Maison Dupont SARL',
            'adresse'                => 'Immeuble Dupont, Boulevard Latrille, Cocody, Abidjan',
            'telephone'              => '+225 27 22 10 00',
            'email'                  => 'contact@maisondupont.ci',
            'rccm'                   => 'CI-ABJ-2019-B-12345',
            'compte_contribuable'    => 'CI0123456789',
            'quota_points_de_vente'  => 5,
            'plan_abonnement'        => 'Pro',
        ]);

        // -----------------------------------------------------------------------
        // 2. Créer les points de vente
        // -----------------------------------------------------------------------
        $pdv1 = PointDeVente::create([
            'entreprise_id' => $entreprise->id,
            'nom'           => 'Agence Centrale',
            'ville'         => 'Abidjan',
            'commune'       => 'Plateau',
            'responsable'   => 'Koné Eric',
            'telephone'     => '+225 27 00 01 01',
            'statut'        => 'Ouvert',
        ]);

        $pdv2 = PointDeVente::create([
            'entreprise_id' => $entreprise->id,
            'nom'           => 'Annexe Cocody',
            'ville'         => 'Abidjan',
            'commune'       => 'Cocody',
            'responsable'   => 'Diallo Awa',
            'telephone'     => '+225 27 00 02 02',
            'statut'        => 'Ouvert',
        ]);

        PointDeVente::create([
            'entreprise_id' => $entreprise->id,
            'nom'           => 'Boutique Yopougon',
            'ville'         => 'Abidjan',
            'commune'       => 'Yopougon',
            'responsable'   => 'Traoré Marc',
            'telephone'     => '+225 27 00 03 03',
            'statut'        => 'Ouvert',
        ]);

        PointDeVente::create([
            'entreprise_id' => $entreprise->id,
            'nom'           => 'Dépôt Adjamé',
            'ville'         => 'Abidjan',
            'commune'       => 'Adjamé',
            'responsable'   => 'Koné Fatou',
            'telephone'     => '+225 27 00 04 04',
            'statut'        => 'Fermé',
        ]);

        // -----------------------------------------------------------------------
        // 3. Créer les utilisateurs
        // -----------------------------------------------------------------------

        // Super Admin (pas d'entreprise liée)
        Utilisateur::create([
            'entreprise_id'      => null,
            'point_de_vente_id'  => null,
            'nom'                => 'Super Administrateur',
            'email'              => 'superadmin@gmail.com',
            'password'           => Hash::make('12345678'),
            'role'               => 'superadmin',
            'statut'             => 'actif',
        ]);

        // Admin (lié à l'entreprise, pas à un point de vente spécifique)
        Utilisateur::create([
            'entreprise_id'      => $entreprise->id,
            'point_de_vente_id'  => null,
            'nom'                => 'Administrateur',
            'email'              => 'admin@gmail.com',
            'password'           => Hash::make('12345678'),
            'role'               => 'admin',
            'statut'             => 'actif',
        ]);

        // Caissier (lié à l'entreprise ET au point de vente 1)
        Utilisateur::create([
            'entreprise_id'      => $entreprise->id,
            'point_de_vente_id'  => $pdv1->id,
            'nom'                => 'Koné Fatou',
            'email'              => 'vente@gmail.com',
            'password'           => Hash::make('12345678'),
            'role'               => 'caissier',
            'statut'             => 'actif',
        ]);

        // -----------------------------------------------------------------------
        // 4. Créer les produits (catalogue)
        // -----------------------------------------------------------------------
        $produits = [
            ['reference' => 'ART-001', 'nom' => 'Huile Dinor 1L',       'categorie' => 'Épicerie',  'prix_achat' => 900,  'prix_vente' => 1200, 'stock_actuel' => 42, 'stock_minimum' => 10],
            ['reference' => 'ART-002', 'nom' => 'Sucre 1kg',            'categorie' => 'Épicerie',  'prix_achat' => 550,  'prix_vente' => 750,  'stock_actuel' => 80, 'stock_minimum' => 15],
            ['reference' => 'ART-003', 'nom' => 'Lait en poudre 500g',  'categorie' => 'Épicerie',  'prix_achat' => 2000, 'prix_vente' => 2500, 'stock_actuel' => 5,  'stock_minimum' => 8],
            ['reference' => 'ART-004', 'nom' => 'Savon Palmolive',      'categorie' => 'Hygiène',   'prix_achat' => 400,  'prix_vente' => 600,  'stock_actuel' => 34, 'stock_minimum' => 10],
            ['reference' => 'ART-005', 'nom' => 'Eau minérale 1.5L',    'categorie' => 'Boissons',  'prix_achat' => 300,  'prix_vente' => 450,  'stock_actuel' => 120,'stock_minimum' => 20],
            ['reference' => 'ART-006', 'nom' => 'Riz parfumé 5kg',      'categorie' => 'Épicerie',  'prix_achat' => 4000, 'prix_vente' => 5000, 'stock_actuel' => 18, 'stock_minimum' => 5],
        ];

        foreach ($produits as $donnees) {
            Produit::create(array_merge($donnees, ['entreprise_id' => $entreprise->id]));
        }

        // -----------------------------------------------------------------------
        // 5. Créer les clients
        // -----------------------------------------------------------------------
        Client::create(['entreprise_id' => $entreprise->id, 'nom' => 'Koffi Amos',     'telephone' => '+225 07 11 22 33', 'email' => 'koffi@mail.com',  'adresse' => 'Cocody, Abidjan']);
        Client::create(['entreprise_id' => $entreprise->id, 'nom' => 'Diallo Mariam',  'telephone' => '+225 07 44 55 66', 'email' => 'diallo@mail.com', 'adresse' => 'Plateau, Abidjan']);
        Client::create(['entreprise_id' => $entreprise->id, 'nom' => 'Coulibaly Jean', 'telephone' => '+225 07 77 88 99', 'email' => 'coul@mail.com',   'adresse' => 'Yopougon, Abidjan']);
        Client::create(['entreprise_id' => $entreprise->id, 'nom' => 'Koné Fatou',     'telephone' => '+225 07 99 10 11', 'email' => 'kone@mail.com',   'adresse' => 'Adjamé, Abidjan']);
        Client::create(['entreprise_id' => $entreprise->id, 'nom' => 'Bamba Seydou',   'telephone' => '+225 07 22 33 44', 'email' => 'bamba@mail.com',  'adresse' => 'Marcory, Abidjan']);

        // -----------------------------------------------------------------------
        // 6. Créer les fournisseurs
        // -----------------------------------------------------------------------
        Fournisseur::create(['entreprise_id' => $entreprise->id, 'nom' => 'CDCI Distribution', 'telephone' => '+225 27 20 01 01', 'email' => 'contact@cdci.ci',   'secteur' => 'Alimentation']);
        Fournisseur::create(['entreprise_id' => $entreprise->id, 'nom' => 'ProFoods CI',        'telephone' => '+225 27 20 02 02', 'email' => 'info@profoods.ci',  'secteur' => 'Alimentation']);
        Fournisseur::create(['entreprise_id' => $entreprise->id, 'nom' => 'ElecTech Abidjan',   'telephone' => '+225 27 20 03 03', 'email' => 'pro@electech.ci',   'secteur' => 'Électronique']);
    }
}
