<?php
/**
 * Script de complétion du seed : 
 * - Ajoute les comptes comptables SYSCOHADA de base (401xxx / 411xxx)
 * - Crée des clients pré-enregistrés pour chaque entreprise
 * - Crée des fournisseurs pré-enregistrés pour chaque entreprise
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Fournisseur;
use App\Modules\Admin\Modeles\PlanComptable;
use App\Modules\Admin\Modeles\Entreprise;
use Illuminate\Support\Facades\DB;

$entreprises = Entreprise::whereIn('nom', ['DC-KNOWING', 'B-HOME SARL'])->get();

if ($entreprises->isEmpty()) {
    echo "❌ Aucune entreprise trouvée. Exécutez d'abord seed_full_data.php\n";
    exit(1);
}

echo "📊 Création des comptes comptables de base pour chaque entreprise...\n";

$comptesBase = [
    // Fournisseurs (401xxx)
    ['numero' => '401000', 'libelle' => 'Fournisseurs - Compte collectif général'],
    ['numero' => '401100', 'libelle' => 'Fournisseurs locaux'],
    ['numero' => '401200', 'libelle' => 'Fournisseurs importation'],
    // Clients (411xxx)
    ['numero' => '411000', 'libelle' => 'Clients - Compte collectif général'],
    ['numero' => '411100', 'libelle' => 'Clients locaux'],
    ['numero' => '411200', 'libelle' => 'Clients export'],
    // Comptes de résultat
    ['numero' => '701000', 'libelle' => 'Ventes de marchandises'],
    ['numero' => '706000', 'libelle' => 'Prestations de services'],
    ['numero' => '601000', 'libelle' => 'Achats de marchandises'],
    // TVA
    ['numero' => '443100', 'libelle' => 'TVA collectée sur ventes'],
    ['numero' => '445100', 'libelle' => 'TVA récupérable sur achats'],
    // Caisse / Banque
    ['numero' => '521000', 'libelle' => 'Banque - Compte courant'],
    ['numero' => '571000', 'libelle' => 'Caisse principale'],
];

foreach ($entreprises as $entreprise) {
    foreach ($comptesBase as $compte) {
        PlanComptable::firstOrCreate(
            ['entreprise_id' => $entreprise->id, 'numero' => $compte['numero']],
            ['libelle' => $compte['libelle'], 'entreprise_id' => $entreprise->id]
        );
    }
    echo "   ✅ Comptes comptables créés pour {$entreprise->nom}\n";
}

echo "\n👥 Création des clients pré-enregistrés...\n";

// Helper pour générer un numéro de tiers globalement unique
function nextClientNumero($entrepriseId, $base = '411') {
    // Cherche le dernier numéro dans TOUTE la table (unique globale)
    $max = \App\Modules\Admin\Modeles\Client::where('numero_tiers', 'like', $base . '%')
        ->orderByRaw('CAST(numero_tiers AS UNSIGNED) DESC')
        ->value('numero_tiers');
    return $max ? (string)((int)$max + 1) : $base . '001';
}

function nextFournisseurNumero($entrepriseId, $base = '401') {
    // Cherche le dernier numéro dans TOUTE la table (unique globale)
    $max = \App\Modules\Admin\Modeles\Fournisseur::where('numero_tiers', 'like', $base . '%')
        ->orderByRaw('CAST(numero_tiers AS UNSIGNED) DESC')
        ->value('numero_tiers');
    return $max ? (string)((int)$max + 1) : $base . '001';
}

// Clients DC-KNOWING (secteur technologie/services)
$dcKnowing = Entreprise::where('nom', 'DC-KNOWING')->first();
if ($dcKnowing) {
    $clientsDCK = [
        ['nom' => 'B-HOME SARL',              'telephone' => '0709767690',     'email' => 'bhome.client.dck@gmail.com',  'adresse' => 'Cocody Cité des Cadres, Abidjan', 'ncc' => '1234567 B', 'rccm' => 'CI-ABJ-2022-B-54321', 'regime_imposition' => 'TEE'],
        ['nom' => 'SOTRA SA',                  'telephone' => '27 20 22 33 44', 'email' => 'contact@sotra.ci',            'adresse' => 'Treichville, Abidjan',            'ncc' => '9876543 A', 'rccm' => 'CI-ABJ-2010-B-00125', 'regime_imposition' => 'RNI'],
        ['nom' => 'COLAS CÔTE D\'IVOIRE',     'telephone' => '27 22 50 15 00', 'email' => 'info@colas.ci',               'adresse' => 'Zone 4, Abidjan',                 'ncc' => '5432198 C', 'rccm' => 'CI-ABJ-2005-B-00789', 'regime_imposition' => 'RNI'],
        ['nom' => 'AGENCE AFRICAINE TECH',     'telephone' => '0707777888',     'email' => 'aat@gmail.com',               'adresse' => 'Yopougon, Abidjan',               'ncc' => null,        'rccm' => null,                  'regime_imposition' => 'RS'],
        ['nom' => 'CABINET KOFFI & ASSOCIES',  'telephone' => '0103447812',     'email' => 'koffi.assoc@cabinet.ci',     'adresse' => 'Plateau, Abidjan',                'ncc' => '1111222 D', 'rccm' => 'CI-ABJ-2015-B-03321', 'regime_imposition' => 'RSI'],
    ];

    foreach ($clientsDCK as $c) {
        Client::firstOrCreate(
            ['entreprise_id' => $dcKnowing->id, 'email' => $c['email']],
            array_merge($c, [
                'entreprise_id' => $dcKnowing->id,
                'compte_comptable' => '411100',
                'numero_tiers' => nextClientNumero($dcKnowing->id),
            ])
        );
    }
    echo "   ✅ 5 clients pré-enregistrés pour DC-KNOWING\n";

    // Fournisseurs DC-KNOWING
    $fournisseursDCK = [
        ['nom' => 'SYSCOM TECH CI',        'telephone' => '27 22 42 00 10', 'email' => 'syscom@syscomtech.ci', 'secteur' => 'Technologie', 'adresse' => 'Marcory, Abidjan',          'ncc' => '3344556 A', 'rccm' => 'CI-ABJ-2012-B-01234', 'regime_imposition' => 'TEE'],
        ['nom' => 'GLOBAL OFFICE CI',      'telephone' => '0706050413',     'email' => 'contact@globaloff.ci', 'secteur' => 'Commerce',    'adresse' => 'Cocody, Abidjan',           'ncc' => '7788990 B', 'rccm' => 'CI-ABJ-2018-B-02567', 'regime_imposition' => 'TEE'],
        ['nom' => 'IMPRIMERIE EXCELLENCE', 'telephone' => '0102030405',     'email' => 'excellence@print.ci',  'secteur' => 'Industrie',   'adresse' => 'Adjamé, Abidjan',           'ncc' => null,        'rccm' => null,                  'regime_imposition' => 'RS'],
        ['nom' => 'LOGISTICS GROUP AFRIC', 'telephone' => '27 22 45 12 00', 'email' => 'logistics@lga.ci',    'secteur' => 'Transport',   'adresse' => 'Port Bouët, Abidjan',       'ncc' => '4455667 C', 'rccm' => 'CI-ABJ-2016-B-04456', 'regime_imposition' => 'RNI'],
        ['nom' => 'CARREFOUR FOURNISSEUR', 'telephone' => '0101020304',     'email' => 'pro@carrefour.ci',     'secteur' => 'Commerce',    'adresse' => 'Plateau, Abidjan',          'ncc' => '9900112 D', 'rccm' => 'CI-ABJ-2011-B-07891', 'regime_imposition' => 'RNI'],
    ];

    foreach ($fournisseursDCK as $f) {
        Fournisseur::firstOrCreate(
            ['entreprise_id' => $dcKnowing->id, 'email' => $f['email']],
            array_merge($f, [
                'entreprise_id' => $dcKnowing->id,
                'compte_comptable' => '401100',
                'numero_tiers' => nextFournisseurNumero($dcKnowing->id),
            ])
        );
    }
    echo "   ✅ 5 fournisseurs pré-enregistrés pour DC-KNOWING\n";
}

// Clients B-HOME SARL (secteur immobilier/construction)
$bHome = Entreprise::where('nom', 'B-HOME SARL')->first();
if ($bHome) {
    $clientsBHome = [
        ['nom' => 'DC-KNOWING',             'telephone' => '27 22 42 14 43', 'email' => 'dcknowing.client.bhome@gmail.com', 'adresse' => 'Cocody II Plateaux Angré les Oscars', 'ncc' => '1864699 A', 'rccm' => 'CI-ABJ-2018-B-31734', 'regime_imposition' => 'TEE'],
        ['nom' => 'PROMOTEUR ABIDJANAIS',    'telephone' => '0505060708',     'email' => 'promoteur.abj@mail.ci',           'adresse' => 'Bingerville, Abidjan',                'ncc' => '2211334 E', 'rccm' => 'CI-ABJ-2019-B-11223', 'regime_imposition' => 'RSI'],
        ['nom' => 'SCI LES PALMIERS',        'telephone' => '0102030607',     'email' => 'sci.palmiers@immob.ci',           'adresse' => 'Assinie, Abidjan',                    'ncc' => null,        'rccm' => null,                  'regime_imposition' => 'RS'],
        ['nom' => 'GROUPE HABITAT MODERNE',  'telephone' => '27 22 41 11 22', 'email' => 'info@habitatmod.ci',              'adresse' => 'Riviéra Palmeraie, Abidjan',          'ncc' => '6677889 F', 'rccm' => 'CI-ABJ-2014-B-08901', 'regime_imposition' => 'RNI'],
        ['nom' => 'ARCHITECTE DESIGN & CO',  'telephone' => '0707112233',     'email' => 'design@archi.ci',                 'adresse' => 'Cocody, Abidjan',                     'ncc' => null,        'rccm' => null,                  'regime_imposition' => 'RS'],
    ];

    foreach ($clientsBHome as $c) {
        Client::firstOrCreate(
            ['entreprise_id' => $bHome->id, 'email' => $c['email']],
            array_merge($c, [
                'entreprise_id' => $bHome->id,
                'compte_comptable' => '411100',
                'numero_tiers' => nextClientNumero($bHome->id),
            ])
        );
    }
    echo "   ✅ 5 clients pré-enregistrés pour B-HOME SARL\n";

    // Fournisseurs B-HOME SARL
    $fournisseursBHome = [
        ['nom' => 'CIMENTS IVOIRIENS SA',    'telephone' => '27 22 40 15 00', 'email' => 'cimivoire@ciment.ci',  'secteur' => 'Industrie',  'adresse' => 'Zone Industrielle, Abidjan', 'ncc' => '1122334 G', 'rccm' => 'CI-ABJ-2007-B-00456', 'regime_imposition' => 'RNI'],
        ['nom' => 'QUINCAILLERIE BÂTIR CI',  'telephone' => '0101234567',     'email' => 'batirci@quinca.ci',   'secteur' => 'Commerce',   'adresse' => 'Adjamé, Abidjan',            'ncc' => '5566778 H', 'rccm' => 'CI-ABJ-2013-B-05678', 'regime_imposition' => 'TEE'],
        ['nom' => 'FERRONORD CI',            'telephone' => '27 22 48 30 00', 'email' => 'ferronord@acier.ci',  'secteur' => 'Industrie',  'adresse' => 'Yopougon, Abidjan',          'ncc' => '9900223 I', 'rccm' => 'CI-ABJ-2009-B-02341', 'regime_imposition' => 'RNI'],
        ['nom' => 'PLOMBERIE SERVICES PLUS', 'telephone' => '0506070809',     'email' => 'plomberie@serv.ci',   'secteur' => 'Bâtiment',  'adresse' => 'Marcory, Abidjan',           'ncc' => null,        'rccm' => null,                  'regime_imposition' => 'RS'],
        ['nom' => 'MENUISERIE DU PLATEAU',   'telephone' => '0102030409',     'email' => 'menuiserie@bois.ci',  'secteur' => 'Artisanat',  'adresse' => 'Plateau, Abidjan',           'ncc' => null,        'rccm' => null,                  'regime_imposition' => 'RS'],
    ];

    foreach ($fournisseursBHome as $f) {
        Fournisseur::firstOrCreate(
            ['entreprise_id' => $bHome->id, 'email' => $f['email']],
            array_merge($f, [
                'entreprise_id' => $bHome->id,
                'compte_comptable' => '401100',
                'numero_tiers' => nextFournisseurNumero($bHome->id),
            ])
        );
    }
    echo "   ✅ 5 fournisseurs pré-enregistrés pour B-HOME SARL\n";
}

echo "\n✨ Complétion du seed terminée avec succès !\n";
echo "   - Comptes comptables créés (401xxx, 411xxx, 701xxx, etc.)\n";
echo "   - 5 clients + 5 fournisseurs créés par entreprise\n";
