<?php
/**
 * MEGA SEED v3 - Selflow (Avec Ecritures Comptables)
 * Phases : PDV + Caissiers, Images produits, Production (FT+OP),
 *          Ventes (Devis/BC/Factures/Avoirs), Achats, B2B, Transferts, Logos
 */

require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Modules\Admin\Modeles\Entreprise;
use App\Modules\Admin\Modeles\PointDeVente;
use App\Modules\Admin\Modeles\Produit;
use App\Modules\Admin\Modeles\Stock;
use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Fournisseur;
use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Modeles\VenteDetail;
use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\AchatDetail;
use App\Modules\Admin\Modeles\B2bNegotiation;
use App\Modules\Admin\Modeles\FicheTechnique;
use App\Modules\Admin\Modeles\FicheTechniqueDetail;
use App\Modules\Admin\Modeles\OrdreProduction;
use App\Modules\Admin\Modeles\MouvementStock;
use App\Modules\Admin\Modeles\TransfertStock;
use App\Modules\Admin\Modeles\CodeJournal;
use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Services\ComptabiliteService;
use App\Modules\Authentification\Modeles\Utilisateur;

// Nettoyer les écritures comptables générées précédemment pour éviter les doublons lors des re-runs du seed
DB::table('ecritures_comptables')->delete();
echo "🧹 Ecritures comptables existantes supprimées.\n";

// ── Helper : calcul TVA & TTC d'une ligne ─────────────────────────
function ligneCalc(array $l): array {
    $ht      = (float)$l['prix_unitaire'] * (int)$l['quantite'];
    $tvaRate = (float)$l['taux_tva'];
    $tva     = round($ht * $tvaRate / 100, 2);
    $ttc     = round($ht + $tva, 2);
    return ['montant_tva' => $tva, 'montant_ttc' => $ttc, 'ht' => $ht];
}

// ── Helper : totaux d'un tableau de lignes ─────────────────────────
function totaux(array $lignes): array {
    $ht = $tva = 0;
    foreach ($lignes as $l) {
        $c = ligneCalc($l);
        $ht += $c['ht'];
        $tva += $c['montant_tva'];
    }
    return ['ht' => round($ht,2), 'tva' => round($tva,2), 'ttc' => round($ht+$tva,2)];
}

// ── Helper : crée les lignes d'un Achat ou Vente ──────────────────
function creerLignesVente(int $venteId, array $lignes): void {
    foreach ($lignes as $l) {
        $c = ligneCalc($l);
        VenteDetail::create([
            'vente_id'        => $venteId,
            'produit_id'      => $l['produit_id'] ?? null,
            'libelle_virtuel' => $l['libelle'],
            'quantite'        => abs((int)$l['quantite']),
            'unite'           => $l['unite'] ?? 'Unité',
            'prix_unitaire'   => $l['prix_unitaire'],
            'montant_tva'     => $c['montant_tva'],
            'montant_ttc'     => $c['montant_ttc'],
        ]);
    }
}

function creerLignesAchat(int $achatId, array $lignes): void {
    foreach ($lignes as $l) {
        $c = ligneCalc($l);
        AchatDetail::create([
            'achat_id'        => $achatId,
            'produit_id'      => $l['produit_id'] ?? null,
            'libelle_virtuel' => $l['libelle'],
            'quantite'        => abs((int)$l['quantite']),
            'unite'           => $l['unite'] ?? 'Unité',
            'prix_unitaire'   => $l['prix_unitaire'],
            'montant_tva'     => $c['montant_tva'],
            'montant_ttc'     => $c['montant_ttc'],
        ]);
    }
}

// ── Helper : Vente::firstOrCreate sans global scope ───────────────
function venteFirstOrCreate(array $search, array $data): Vente {
    $v = Vente::withoutGlobalScopes()->where($search)->first();
    if (!$v) {
        $v = Vente::withoutGlobalScopes()->create(array_merge($search, $data));
    }
    return $v;
}

function achatFirstOrCreate(array $search, array $data): Achat {
    $a = Achat::withoutGlobalScopes()->where($search)->first();
    if (!$a) {
        $a = Achat::withoutGlobalScopes()->create(array_merge($search, $data));
    }
    return $a;
}

function mouvement(int $produitId, int $pdvId, string $type, string $sousType, int $qty, float $avant, string $ref, int $userId): void {
    MouvementStock::create([
        'produit_id'         => $produitId,
        'point_de_vente_id'  => $pdvId,
        'type_mouvement'     => $type,
        'sous_type'          => $sousType,
        'quantite'           => $qty,
        'stock_avant'        => $avant,
        'stock_apres'        => $type === 'Entree' ? $avant + $qty : $avant - $qty,
        'reference_document' => $ref,
        'utilisateur_id'     => $userId,
    ]);
}

// ══════════════════════════════════════════════════════════════════
echo "🚀 MEGA SEED v3 — Démarrage\n";
echo "══════════════════════════════════════════════════════════════\n\n";

$dck   = Entreprise::where('nom', 'DC-KNOWING')->firstOrFail();
$bHome = Entreprise::where('nom', 'B-HOME SARL')->firstOrFail();
$adminDck   = Utilisateur::where('email', 'dcknowing@gmail.com')->first();
$adminBHome = Utilisateur::where('email', 'bhome@gmail.com')->first();

if (!$adminDck || !$adminBHome) {
    die("❌ Admins non trouvés. Vérifiez les emails.\n");
}

$siegeDck   = PointDeVente::firstOrCreate(['entreprise_id' => $dck->id, 'nom' => 'Siège'], ['ville' => 'Abidjan', 'commune' => 'Cocody', 'responsable' => 'Superviseur', 'statut' => 'Ouvert']);
$siegeBHome = PointDeVente::firstOrCreate(['entreprise_id' => $bHome->id, 'nom' => 'Siège'], ['ville' => 'Abidjan', 'commune' => 'Cocody', 'responsable' => 'Superviseur', 'statut' => 'Ouvert']);

echo "✅ Entreprises : DC-KNOWING (ID:{$dck->id}) | B-HOME SARL (ID:{$bHome->id})\n\n";

// ═══════════════════════════════════════════════════════
// PHASE 1 — POINTS DE VENTE SUPPLÉMENTAIRES & CAISSIERS
// ═══════════════════════════════════════════════════════
echo "📍 PHASE 1 — Points de vente & caissiers\n";

$pdvDckPlateau   = PointDeVente::firstOrCreate(['entreprise_id' => $dck->id, 'nom' => 'Agence Plateau'],   ['ville' => 'Abidjan', 'commune' => 'Plateau',  'responsable' => 'Chef Agence Plateau',  'telephone' => '27 22 20 10 01', 'statut' => 'Ouvert']);
$pdvDckYopougon  = PointDeVente::firstOrCreate(['entreprise_id' => $dck->id, 'nom' => 'Magasin Yopougon'], ['ville' => 'Abidjan', 'commune' => 'Yopougon', 'responsable' => 'Chef Magasin Yopougon', 'telephone' => '05 10 20 30 40', 'statut' => 'Ouvert']);
$pdvBHomeShowroom= PointDeVente::firstOrCreate(['entreprise_id' => $bHome->id,'nom' => 'Showroom Cocody'], ['ville' => 'Abidjan', 'commune' => 'Cocody',   'responsable' => 'Chef Showroom',        'telephone' => '07 09 50 60 70', 'statut' => 'Ouvert']);
$pdvBHomeEntrepot= PointDeVente::firstOrCreate(['entreprise_id' => $bHome->id,'nom' => 'Entrepôt Marcory'],['ville' => 'Abidjan', 'commune' => 'Marcory',  'responsable' => 'Chef Entrepôt',        'telephone' => '05 60 70 80 90', 'statut' => 'Ouvert']);

$caissierDck1  = Utilisateur::firstOrCreate(['email' => 'caissier1.dck@gmail.com'],   ['nom' => 'KOUAME', 'prenom' => 'Jean',    'password' => Hash::make('CAISSIER@123'), 'role' => 'caissier', 'statut' => 'actif', 'entreprise_id' => $dck->id,   'point_de_vente_id' => $pdvDckPlateau->id,    'doit_changer_password' => false]);
$caissierDck2  = Utilisateur::firstOrCreate(['email' => 'caissier2.dck@gmail.com'],   ['nom' => 'KONATE', 'prenom' => 'Marie',  'password' => Hash::make('CAISSIER@123'), 'role' => 'caissier', 'statut' => 'actif', 'entreprise_id' => $dck->id,   'point_de_vente_id' => $pdvDckYopougon->id,   'doit_changer_password' => false]);
$caissierBHome1= Utilisateur::firstOrCreate(['email' => 'caissier1.bhome@gmail.com'], ['nom' => 'BAMBA',  'prenom' => 'Inès',   'password' => Hash::make('CAISSIER@123'), 'role' => 'caissier', 'statut' => 'actif', 'entreprise_id' => $bHome->id, 'point_de_vente_id' => $pdvBHomeShowroom->id, 'doit_changer_password' => false]);
$caissierBHome2= Utilisateur::firstOrCreate(['email' => 'caissier2.bhome@gmail.com'], ['nom' => 'TRAORE', 'prenom' => 'Mohamed','password' => Hash::make('CAISSIER@123'), 'role' => 'caissier', 'statut' => 'actif', 'entreprise_id' => $bHome->id, 'point_de_vente_id' => $pdvBHomeEntrepot->id, 'doit_changer_password' => false]);

// Initialiser stocks pour les nouveaux PDV
$newPdvs = [$pdvDckPlateau, $pdvDckYopougon];
foreach (Produit::where('entreprise_id', $dck->id)->whereIn('type', ['marchandise','matiere_premiere','produit_fini','consommable_stockable'])->get() as $p) {
    foreach ($newPdvs as $pdv) {
        $st = Stock::firstOrCreate(['produit_id' => $p->id, 'point_de_vente_id' => $pdv->id], ['quantite_disponible' => 50, 'stock_minimum' => 2]);
        if (!$st->wasRecentlyCreated) $st->update(['quantite_disponible' => max($st->quantite_disponible, 50)]);
    }
}
$newPdvsBHome = [$pdvBHomeShowroom, $pdvBHomeEntrepot];
foreach (Produit::where('entreprise_id', $bHome->id)->whereIn('type', ['marchandise','matiere_premiere','produit_fini','consommable_stockable'])->get() as $p) {
    foreach ($newPdvsBHome as $pdv) {
        $st = Stock::firstOrCreate(['produit_id' => $p->id, 'point_de_vente_id' => $pdv->id], ['quantite_disponible' => 50, 'stock_minimum' => 2]);
        if (!$st->wasRecentlyCreated) $st->update(['quantite_disponible' => max($st->quantite_disponible, 50)]);
    }
}
echo "   ✅ 4 PDV + 4 caissiers créés. Stocks initialisés.\n\n";

// ═══════════════════════════════════════════════════════
// IMAGES PRODUITS
// ═══════════════════════════════════════════════════════
echo "🖼️  Images produits (Unsplash)...\n";
$images = [
    'Ordinateur Portable'             => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=400&fit=crop',
    'Écran'                           => 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?w=400&fit=crop',
    'Souris'                          => 'https://images.unsplash.com/photo-1527814050087-3793815479db?w=400&fit=crop',
    'Clavier'                         => 'https://images.unsplash.com/photo-1541140532154-b024d705b90a?w=400&fit=crop',
    'Fauteuil'                        => 'https://images.unsplash.com/photo-1505843513577-22bb7d21e455?w=400&fit=crop',
    'Bureau'                          => 'https://images.unsplash.com/photo-1593642632559-0c6d3fc62b89?w=400&fit=crop',
    'Papier'                          => 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=400&fit=crop',
    'Cartouche'                       => 'https://images.unsplash.com/photo-1585771724684-38269d6639fd?w=400&fit=crop',
    'Eau'                             => 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=400&fit=crop',
    'Résine'                          => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&fit=crop',
    'Acier'                           => 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?w=400&fit=crop',
    'Roulement'                       => 'https://images.unsplash.com/photo-1532186651327-6ac23687d189?w=400&fit=crop',
    'Palette'                         => 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?w=400&fit=crop',
    'Fraiseuse'                       => 'https://images.unsplash.com/photo-1565043666747-69f6646db940?w=400&fit=crop',
    'Profilé'                         => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?w=400&fit=crop',
    'Audit'                           => 'https://images.unsplash.com/photo-1554224154-26032ffc0d07?w=400&fit=crop',
    'Honoraire'                       => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=400&fit=crop',
    'Formation'                       => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=400&fit=crop',
    'Maintenance'                     => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=400&fit=crop',
    'Développement'                   => 'https://images.unsplash.com/photo-1607706189992-eae578626c86?w=400&fit=crop',
    'Saisie'                          => 'https://images.unsplash.com/photo-1553877522-43269d4ea984?w=400&fit=crop',
    'Ciment'                          => 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?w=400&fit=crop',
    'Brique'                          => 'https://images.unsplash.com/photo-1541123437800-1bb1317badc2?w=400&fit=crop',
    'Bois'                            => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&fit=crop',
    'Peinture'                        => 'https://images.unsplash.com/photo-1562259929-b4e1fd3aef09?w=400&fit=crop',
    'Carrelage'                       => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&fit=crop',
    'Plomberie'                       => 'https://images.unsplash.com/photo-1504307651254-35680f356dfd?w=400&fit=crop',
    'Vitre'                           => 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?w=400&fit=crop',
    'Fenêtre'                         => 'https://images.unsplash.com/photo-1504917595217-d4dc5ebe6122?w=400&fit=crop',
];
$updCount = 0;
foreach (Produit::whereNull('photo')->orWhere('photo','')->get() as $prod) {
    foreach ($images as $keyword => $url) {
        if (stripos($prod->nom, $keyword) !== false) {
            $prod->update(['photo' => $url]);
            $updCount++;
            break;
        }
    }
    if (!$prod->fresh()->photo) {
        $generic = match($prod->type) {
            'service'   => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=400&fit=crop',
            'marchandise','consommable_stockable' => 'https://images.unsplash.com/photo-1553877522-43269d4ea984?w=400&fit=crop',
            default     => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=400&fit=crop',
        };
        $prod->update(['photo' => $generic]);
        $updCount++;
    }
}
echo "   ✅ {$updCount} images produits assignées\n\n";

// ═══════════════════════════════════════════════════════
// PHASE 2 — PRODUCTION (FT + OP)
// ═══════════════════════════════════════════════════════
echo "🏭 PHASE 2 — Production (Fiches Techniques + Ordres)\n";

foreach ([['e' => $dck, 's' => $siegeDck, 'admin' => $adminDck], ['e' => $bHome, 's' => $siegeBHome, 'admin' => $adminBHome]] as $cfg) {
    $e = $cfg['e']; $siege = $cfg['s']; $admin = $cfg['admin'];
    $pf = Produit::where('entreprise_id', $e->id)->where('type', 'produit_fini')->first();
    $mp = Produit::where('entreprise_id', $e->id)->where('type', 'matiere_premiere')->get();

    if (!$pf || $mp->isEmpty()) {
        echo "   ⚠️  {$e->nom} : pas de produit_fini ou matière_première — FT ignorée\n";
        continue;
    }

    // S'assurer que le stock MP est suffisant
    foreach ($mp as $mat) {
        $st = Stock::firstOrCreate(['produit_id' => $mat->id, 'point_de_vente_id' => $siege->id], ['quantite_disponible' => 500, 'stock_minimum' => 10]);
        if (!$st->wasRecentlyCreated && $st->quantite_disponible < 200) $st->update(['quantite_disponible' => 500]);
    }

    $fiche = FicheTechnique::firstOrCreate(
        ['entreprise_id' => $e->id, 'produit_fini_id' => $pf->id],
        ['description' => "Recette {$pf->nom} — standard {$e->nom}"]
    );

    if ($fiche->details()->count() === 0) {
        foreach ($mp->take(2) as $idx => $mat) {
            FicheTechniqueDetail::create(['fiche_technique_id' => $fiche->id, 'ingredient_id' => $mat->id, 'quantite' => ($idx+1) * 2.0, 'unite' => $mat->unite]);
        }
    }

    // Ordre 1 — Brouillon
    OrdreProduction::firstOrCreate(
        ['entreprise_id' => $e->id, 'code_ordre' => "OP-{$e->id}-001"],
        ['point_de_vente_id' => $siege->id, 'produit_fini_id' => $pf->id, 'quantite_cible' => 10, 'statut' => 'Brouillon', 'date_production' => now()->subDays(10)->format('Y-m-d')]
    );

    // Ordre 2 — En cours
    OrdreProduction::firstOrCreate(
        ['entreprise_id' => $e->id, 'code_ordre' => "OP-{$e->id}-002"],
        ['point_de_vente_id' => $siege->id, 'produit_fini_id' => $pf->id, 'quantite_cible' => 5, 'statut' => 'En cours', 'date_production' => now()->subDays(3)->format('Y-m-d')]
    );

    // Ordre 3 — Terminé (Génération de mouvements + écritures de production)
    $op3 = OrdreProduction::firstOrCreate(
        ['entreprise_id' => $e->id, 'code_ordre' => "OP-{$e->id}-003"],
        ['point_de_vente_id' => $siege->id, 'produit_fini_id' => $pf->id, 'quantite_cible' => 20, 'statut' => 'Terminé', 'date_production' => now()->subDays(1)->format('Y-m-d')]
    );

    if ($op3->wasRecentlyCreated) {
        $consommationsCompta = [];
        // Consommer les ingrédients
        foreach ($fiche->details as $d) {
            $besoin = $d->quantite * 20;
            $stMat = Stock::where('produit_id', $d->ingredient_id)->where('point_de_vente_id', $siege->id)->first();
            if ($stMat) {
                $av = $stMat->quantite_disponible;
                $stMat->decrement('quantite_disponible', $besoin);
                mouvement($d->ingredient_id, $siege->id, 'Sortie', 'Production', $besoin, $av, $op3->code_ordre, $admin->id);
            }
            $consommationsCompta[] = [
                'produit'         => $d->ingredient,
                'quantite'        => $besoin,
                'valeur_unitaire' => (float)($d->ingredient->prix_achat ?? 0),
            ];
        }
        // Augmenter produit fini
        $stPf = Stock::firstOrCreate(['produit_id' => $pf->id, 'point_de_vente_id' => $siege->id], ['quantite_disponible' => 0, 'stock_minimum' => 2]);
        $avPf = $stPf->quantite_disponible;
        $stPf->increment('quantite_disponible', 20);
        mouvement($pf->id, $siege->id, 'Entree', 'Production', 20, $avPf, $op3->code_ordre, $admin->id);

        // Ecriture production
        $valeurProduitFini = 20 * (float)($pf->prix_achat ?? 0);
        ComptabiliteService::genererEcritureProduction($op3, $consommationsCompta, $valeurProduitFini);
    }

    echo "   ✅ {$e->nom} : FT '{$pf->nom}' + OP-{$e->id}-001 (Brouillon) + OP-{$e->id}-003 (Terminé + Ecriture)\n";
}
echo "\n";

// ═══════════════════════════════════════════════════════
// CODES JOURNAUX BANQUE
// ═══════════════════════════════════════════════════════
$cjDck   = CodeJournal::firstOrCreate(['entreprise_id' => $dck->id,   'code' => 'BGFI'],   ['intitule' => 'BGFI Bank — Compte Courant', 'type' => 'Banque', 'compte' => '52110000']);
$cjBHome = CodeJournal::firstOrCreate(['entreprise_id' => $bHome->id, 'code' => 'SIB'],    ['intitule' => 'SIB — Compte Commercial',    'type' => 'Banque', 'compte' => '52120000']);
echo "💳 Codes journaux banque : BGFI ({$dck->nom}) + SIB ({$bHome->nom})\n\n";

// ═══════════════════════════════════════════════════════
// PHASE 3 — VENTES
// ═══════════════════════════════════════════════════════
echo "🛒 PHASE 3 — Ventes\n";

// ─── DC-KNOWING ventes ───────────────────────────────
$pdck = Produit::where('entreprise_id', $dck->id)->where('statut','actif')->get();
$p1   = $pdck->firstWhere('type', 'marchandise') ?? $pdck->first();
$p2   = $pdck->where('type','marchandise')->skip(1)->first() ?? $pdck->skip(1)->first();
$p3   = $pdck->firstWhere('type', 'service');
$clientsDck   = Client::where('entreprise_id', $dck->id)->get();
$cliBHome     = $clientsDck->firstWhere('nom', 'B-HOME SARL') ?? $clientsDck->first();
$cliSotra     = $clientsDck->skip(1)->first() ?? $clientsDck->first();

// 1. DEVIS → B-HOME SARL
if ($p1 && $cliBHome) {
    $lg = [['produit_id'=>$p1->id,'libelle'=>$p1->nom,'quantite'=>2,'prix_unitaire'=>$p1->prix_vente,'unite'=>$p1->unite,'taux_tva'=>18]];
    if ($p2) $lg[] = ['produit_id'=>$p2->id,'libelle'=>$p2->nom,'quantite'=>1,'prix_unitaire'=>$p2->prix_vente,'unite'=>$p2->unite,'taux_tva'=>18];
    $t = totaux($lg);
    $doc = venteFirstOrCreate(['numero_facture'=>"DEV-{$dck->id}-001"],['point_de_vente_id'=>$siegeDck->id,'utilisateur_id'=>$adminDck->id,'client_id'=>$cliBHome->id,'date_vente'=>now()->subDays(15)->format('Y-m-d'),'mode_paiement'=>'crédit','statut'=>'Non payé','etape'=>'Devis','type_facture'=>'proformat','montant_ht'=>$t['ht'],'montant_tva'=>$t['tva'],'montant_ttc'=>$t['ttc']]);
    if ($doc->wasRecentlyCreated) creerLignesVente($doc->id, $lg);
    echo "   ✅ DEV-{$dck->id}-001 (Devis proforma)\n";
}

// 2. BON DE COMMANDE → SOTRA SA
if ($p1 && $cliSotra) {
    $lg = [['produit_id'=>$p1->id,'libelle'=>$p1->nom,'quantite'=>3,'prix_unitaire'=>$p1->prix_vente,'unite'=>$p1->unite,'taux_tva'=>18]];
    $t = totaux($lg);
    $doc = venteFirstOrCreate(['numero_facture'=>"BC-{$dck->id}-001"],['point_de_vente_id'=>$siegeDck->id,'utilisateur_id'=>$adminDck->id,'client_id'=>$cliSotra->id,'date_vente'=>now()->subDays(12)->format('Y-m-d'),'mode_paiement'=>'virement','statut'=>'Non payé','etape'=>'Commande','type_facture'=>'normale','montant_ht'=>$t['ht'],'montant_tva'=>$t['tva'],'montant_ttc'=>$t['ttc']]);
    if ($doc->wasRecentlyCreated) creerLignesVente($doc->id, $lg);
    echo "   ✅ BC-{$dck->id}-001 (Bon de Commande)\n";
}

// 3. FACTURE CAISSE (Payée)
if ($p1 && $cliBHome) {
    $lg = [['produit_id'=>$p1->id,'libelle'=>$p1->nom,'quantite'=>1,'prix_unitaire'=>$p1->prix_vente,'unite'=>$p1->unite,'taux_tva'=>18]];
    if ($p2) $lg[] = ['produit_id'=>$p2->id,'libelle'=>$p2->nom,'quantite'=>2,'prix_unitaire'=>$p2->prix_vente,'unite'=>$p2->unite,'taux_tva'=>18];
    $t = totaux($lg);
    $doc = venteFirstOrCreate(['numero_facture'=>"FAC-{$dck->id}-001"],['point_de_vente_id'=>$siegeDck->id,'utilisateur_id'=>$adminDck->id,'client_id'=>$cliBHome->id,'date_vente'=>now()->subDays(8)->format('Y-m-d'),'mode_paiement'=>'espèces','statut'=>'Payé','etape'=>'Facture','type_facture'=>'normale','montant_ht'=>$t['ht'],'montant_tva'=>$t['tva'],'montant_ttc'=>$t['ttc']]);
    if ($doc->wasRecentlyCreated) {
        creerLignesVente($doc->id, $lg);
        // Décrémentation stock
        foreach ($lg as $l) {
            if ($p1->estStockable()) {
                $st = Stock::where('produit_id',$l['produit_id'])->where('point_de_vente_id',$siegeDck->id)->first();
                if ($st && $st->quantite_disponible >= $l['quantite']) {
                    $av = $st->quantite_disponible;
                    $st->decrement('quantite_disponible',$l['quantite']);
                    mouvement($l['produit_id'],$siegeDck->id,'Sortie','Livraison',$l['quantite'],$av,"FAC-{$dck->id}-001",$adminDck->id);
                }
            }
        }
    }
    // Générer écritures comptables
    ComptabiliteService::genererEcritureFactureVente($doc);
    ComptabiliteService::genererEcritureReglementVente($doc, $doc->montant_ttc, 'espèces', $doc->date_vente->toDateString());
    echo "   ✅ FAC-{$dck->id}-001 (CAISSE payée + Ecriture + Règlement)\n";
}

// 4. FACTURE BANQUE (Payée)
if ($p1 && $cliSotra) {
    $p4 = $pdck->skip(2)->first() ?? $p1;
    $lg = [['produit_id'=>$p4->id,'libelle'=>$p4->nom,'quantite'=>4,'prix_unitaire'=>$p4->prix_vente,'unite'=>$p4->unite,'taux_tva'=>18]];
    $t = totaux($lg);
    $doc = venteFirstOrCreate(['numero_facture'=>"FAC-{$dck->id}-002"],['point_de_vente_id'=>$siegeDck->id,'utilisateur_id'=>$adminDck->id,'client_id'=>$cliSotra->id,'date_vente'=>now()->subDays(5)->format('Y-m-d'),'mode_paiement'=>'virement','moyen_bancaire'=>$cjDck->code,'statut'=>'Payé','etape'=>'Facture','type_facture'=>'normale','montant_ht'=>$t['ht'],'montant_tva'=>$t['tva'],'montant_ttc'=>$t['ttc']]);
    if ($doc->wasRecentlyCreated) creerLignesVente($doc->id, $lg);
    
    // Générer écritures comptables
    ComptabiliteService::genererEcritureFactureVente($doc);
    ComptabiliteService::genererEcritureReglementVente($doc, $doc->montant_ttc, 'virement', $doc->date_vente->toDateString());
    echo "   ✅ FAC-{$dck->id}-002 (BANQUE payée + Ecriture + Règlement)\n";
}

// 5. FACTURE CRÉDIT (Non payée)
if ($p1 && $cliBHome) {
    $lg = [['produit_id'=>$p1->id,'libelle'=>$p1->nom,'quantite'=>1,'prix_unitaire'=>$p1->prix_vente,'unite'=>$p1->unite,'taux_tva'=>18]];
    $t = totaux($lg);
    $doc = venteFirstOrCreate(['numero_facture'=>"FAC-{$dck->id}-003"],['point_de_vente_id'=>$siegeDck->id,'utilisateur_id'=>$adminDck->id,'client_id'=>$cliBHome->id,'date_vente'=>now()->subDays(3)->format('Y-m-d'),'mode_paiement'=>'crédit','statut'=>'Non payé','etape'=>'Facture','type_facture'=>'normale','montant_ht'=>$t['ht'],'montant_tva'=>$t['tva'],'montant_ttc'=>$t['ttc']]);
    if ($doc->wasRecentlyCreated) creerLignesVente($doc->id, $lg);
    
    // Générer écritures
    ComptabiliteService::genererEcritureFactureVente($doc);
    echo "   ✅ FAC-{$dck->id}-003 (CRÉDIT 60j + Ecriture)\n";
}

// 6. AVOIR VENTE DC-KNOWING
$pAvoir = $p1 ?? $pdck->first();
if ($pAvoir && $cliBHome) {
    $pxAvoir = max((float)$pAvoir->prix_vente, 50000);
    $tvaAv   = round($pxAvoir * 0.18, 2);
    $ttcAv   = round($pxAvoir + $tvaAv, 2);
    $doc = venteFirstOrCreate(['numero_facture'=>"AVO-{$dck->id}-001"],['point_de_vente_id'=>$siegeDck->id,'utilisateur_id'=>$adminDck->id,'client_id'=>$cliBHome->id,'date_vente'=>now()->subDays(6)->format('Y-m-d'),'mode_paiement'=>'espèces','statut'=>'Payé','etape'=>'Facture','type_facture'=>'avoir','raison_avoir'=>'Retour marchandise défectueuse','montant_ht'=>-$pxAvoir,'montant_tva'=>-$tvaAv,'montant_ttc'=>-$ttcAv]);
    if ($doc->wasRecentlyCreated) creerLignesVente($doc->id, [['produit_id'=>$pAvoir->id,'libelle'=>$pAvoir->nom.' (retour)','quantite'=>1,'prix_unitaire'=>-$pxAvoir,'unite'=>$pAvoir->unite,'taux_tva'=>18]]);
    
    // Générer écritures d'avoir
    ComptabiliteService::genererEcritureAvoirVente($doc);
    echo "   ✅ AVO-{$dck->id}-001 (Avoir vente + Ecriture)\n";
}

// ─── B-HOME SARL ventes ──────────────────────────────
$pbh  = Produit::where('entreprise_id', $bHome->id)->where('statut','actif')->get();
$pb1  = $pbh->firstWhere('type','marchandise') ?? $pbh->first();
$pb2  = $pbh->where('type','marchandise')->skip(1)->first() ?? $pbh->skip(1)->first();
$clientsBHome = Client::where('entreprise_id', $bHome->id)->get();
$cliDck       = $clientsBHome->firstWhere('nom', 'DC-KNOWING') ?? $clientsBHome->first();
$cliProm      = $clientsBHome->skip(1)->first() ?? $clientsBHome->first();

if ($pb1 && $cliDck) {
    // FAC CAISSE B-HOME
    $lg = [['produit_id'=>$pb1->id,'libelle'=>$pb1->nom,'quantite'=>2,'prix_unitaire'=>$pb1->prix_vente,'unite'=>$pb1->unite,'taux_tva'=>18]];
    if ($pb2) $lg[] = ['produit_id'=>$pb2->id,'libelle'=>$pb2->nom,'quantite'=>3,'prix_unitaire'=>$pb2->prix_vente,'unite'=>$pb2->unite,'taux_tva'=>18];
    $t = totaux($lg);
    $doc = venteFirstOrCreate(['numero_facture'=>"FAC-{$bHome->id}-001"],['point_de_vente_id'=>$siegeBHome->id,'utilisateur_id'=>$adminBHome->id,'client_id'=>$cliDck->id,'date_vente'=>now()->subDays(10)->format('Y-m-d'),'mode_paiement'=>'espèces','statut'=>'Payé','etape'=>'Facture','type_facture'=>'normale','montant_ht'=>$t['ht'],'montant_tva'=>$t['tva'],'montant_ttc'=>$t['ttc']]);
    if ($doc->wasRecentlyCreated) {
        creerLignesVente($doc->id,$lg);
    }
    ComptabiliteService::genererEcritureFactureVente($doc);
    ComptabiliteService::genererEcritureReglementVente($doc, $doc->montant_ttc, 'espèces', $doc->date_vente->toDateString());
    echo "   ✅ FAC-{$bHome->id}-001 B-HOME (CAISSE payée + Ecriture + Règlement)\n";

    // FAC BANQUE B-HOME
    $t2 = totaux($lg);
    $doc2 = venteFirstOrCreate(['numero_facture'=>"FAC-{$bHome->id}-002"],['point_de_vente_id'=>$siegeBHome->id,'utilisateur_id'=>$adminBHome->id,'client_id'=>$cliDck->id,'date_vente'=>now()->subDays(4)->format('Y-m-d'),'mode_paiement'=>'virement','moyen_bancaire'=>$cjBHome->code,'statut'=>'Payé','etape'=>'Facture','type_facture'=>'normale','montant_ht'=>$t2['ht'],'montant_tva'=>$t2['tva'],'montant_ttc'=>$t2['ttc']]);
    if ($doc2->wasRecentlyCreated) {
        creerLignesVente($doc2->id,$lg);
    }
    ComptabiliteService::genererEcritureFactureVente($doc2);
    ComptabiliteService::genererEcritureReglementVente($doc2, $doc2->montant_ttc, 'virement', $doc2->date_vente->toDateString());
    echo "   ✅ FAC-{$bHome->id}-002 B-HOME (BANQUE payée + Ecriture + Règlement)\n";

    // FAC CRÉDIT B-HOME
    $t3 = totaux($lg);
    $doc3 = venteFirstOrCreate(['numero_facture'=>"FAC-{$bHome->id}-003"],['point_de_vente_id'=>$siegeBHome->id,'utilisateur_id'=>$adminBHome->id,'client_id'=>$cliProm?->id ?? $cliDck->id,'date_vente'=>now()->subDays(2)->format('Y-m-d'),'mode_paiement'=>'crédit','statut'=>'Non payé','etape'=>'Facture','type_facture'=>'normale','montant_ht'=>$t3['ht'],'montant_tva'=>$t3['tva'],'montant_ttc'=>$t3['ttc']]);
    if ($doc3->wasRecentlyCreated) {
        creerLignesVente($doc3->id,$lg);
    }
    ComptabiliteService::genererEcritureFactureVente($doc3);
    echo "   ✅ FAC-{$bHome->id}-003 B-HOME (CRÉDIT + Ecriture)\n";

    // AVOIR B-HOME
    $pxAvBH = max((float)$pb1->prix_vente, 50000);
    $tvaAvBH = round($pxAvBH * 0.18, 2);
    $docAv = venteFirstOrCreate(['numero_facture'=>"AVO-{$bHome->id}-001"],['point_de_vente_id'=>$siegeBHome->id,'utilisateur_id'=>$adminBHome->id,'client_id'=>$cliDck->id,'date_vente'=>now()->subDays(3)->format('Y-m-d'),'mode_paiement'=>'espèces','statut'=>'Payé','etape'=>'Facture','type_facture'=>'avoir','raison_avoir'=>'Remise accordée — erreur prix','montant_ht'=>-$pxAvBH,'montant_tva'=>-$tvaAvBH,'montant_ttc'=>-round($pxAvBH+$tvaAvBH,2)]);
    if ($docAv->wasRecentlyCreated) {
        creerLignesVente($docAv->id,[['produit_id'=>$pb1->id,'libelle'=>$pb1->nom.' (avoir)','quantite'=>1,'prix_unitaire'=>-$pxAvBH,'unite'=>$pb1->unite,'taux_tva'=>18]]);
    }
    ComptabiliteService::genererEcritureAvoirVente($docAv);
    echo "   ✅ AVO-{$bHome->id}-001 B-HOME (Avoir + Ecriture)\n";
}
echo "\n";

// ═══════════════════════════════════════════════════════
// PHASE 4 — ACHATS
// ═══════════════════════════════════════════════════════
echo "📦 PHASE 4 — Achats\n";

foreach ([
    ['e'=>$dck,'siege'=>$siegeDck,'admin'=>$adminDck,'cj'=>$cjDck],
    ['e'=>$bHome,'siege'=>$siegeBHome,'admin'=>$adminBHome,'cj'=>$cjBHome],
] as $cfg) {
    $e = $cfg['e']; $siege = $cfg['s'] ?? $cfg['siege']; $admin = $cfg['admin']; $cj = $cfg['cj'];
    $fourn1 = Fournisseur::where('entreprise_id',$e->id)->first();
    $fourn2 = Fournisseur::where('entreprise_id',$e->id)->skip(1)->first() ?? $fourn1;
    if (!$fourn1) { echo "   ⚠️  {$e->nom} : aucun fournisseur — achats ignorés\n"; continue; }

    $prods  = Produit::where('entreprise_id',$e->id)->where('statut','actif')->get();
    $pa1    = $prods->firstWhere('type','marchandise') ?? $prods->first();
    if (!$pa1) { echo "   ⚠️  {$e->nom} : aucun produit — achats ignorés\n"; continue; }

    // Achat 1 — CRÉDIT
    $lg1 = [['produit_id'=>$pa1->id,'libelle'=>$pa1->nom,'quantite'=>10,'prix_unitaire'=>$pa1->prix_achat,'unite'=>$pa1->unite,'taux_tva'=>18]];
    $t1  = totaux($lg1);
    $a1  = achatFirstOrCreate(['numero_facture'=>"ACH-{$e->id}-001"],['point_de_vente_id'=>$siege->id,'utilisateur_id'=>$admin->id,'fournisseur_id'=>$fourn1->id,'date_achat'=>now()->subDays(20)->format('Y-m-d'),'mode_paiement'=>'crédit','statut'=>'Non payé','etape'=>'Facture','type_facture'=>'normale','montant_ht'=>$t1['ht'],'montant_tva'=>$t1['tva'],'montant_ttc'=>$t1['ttc']]);
    if ($a1->wasRecentlyCreated) {
        creerLignesAchat($a1->id,$lg1);
    }
    ComptabiliteService::genererEcritureFactureAchat($a1);
    echo "   ✅ ACH-{$e->id}-001 ({$e->nom}) CRÉDIT + Ecriture\n";

    // Achat 2 — BANQUE
    $lg2 = [['produit_id'=>$pa1->id,'libelle'=>$pa1->nom,'quantite'=>5,'prix_unitaire'=>$pa1->prix_achat,'unite'=>$pa1->unite,'taux_tva'=>18]];
    $t2  = totaux($lg2);
    $a2  = achatFirstOrCreate(['numero_facture'=>"ACH-{$e->id}-002"],['point_de_vente_id'=>$siege->id,'utilisateur_id'=>$admin->id,'fournisseur_id'=>$fourn1->id,'numero_facture_fournisseur'=>"FOURN-".date('Ymd')."-001",'date_achat'=>now()->subDays(14)->format('Y-m-d'),'mode_paiement'=>'virement','moyen_bancaire'=>$cj->code,'statut'=>'Payé','etape'=>'Facture','type_facture'=>'normale','montant_ht'=>$t2['ht'],'montant_tva'=>$t2['tva'],'montant_ttc'=>$t2['ttc']]);
    if ($a2->wasRecentlyCreated) {
        creerLignesAchat($a2->id,$lg2);
        if ($pa1->estStockable()) {
            $st = Stock::where('produit_id',$pa1->id)->where('point_de_vente_id',$siege->id)->first();
            if ($st) { 
                $av=$st->quantite_disponible; 
                $st->increment('quantite_disponible',5); 
                mouvement($pa1->id,$siege->id,'Entree','Reception',5,$av,"ACH-{$e->id}-002",$admin->id); 
            }
        }
    }
    ComptabiliteService::genererEcritureFactureAchat($a2);
    ComptabiliteService::genererEcritureReglementAchat($a2, $a2->montant_ttc, 'virement', $a2->date_achat->toDateString());
    echo "   ✅ ACH-{$e->id}-002 ({$e->nom}) BANQUE + Ecriture + Règlement\n";

    // Achat 3 — CAISSE
    $pa2 = $prods->skip(1)->first() ?? $pa1;
    $lg3 = [['produit_id'=>$pa2->id,'libelle'=>$pa2->nom,'quantite'=>3,'prix_unitaire'=>$pa2->prix_achat,'unite'=>$pa2->unite,'taux_tva'=>18]];
    $t3  = totaux($lg3);
    $a3  = achatFirstOrCreate(['numero_facture'=>"ACH-{$e->id}-003"],['point_de_vente_id'=>$siege->id,'utilisateur_id'=>$admin->id,'fournisseur_id'=>$fourn2->id,'date_achat'=>now()->subDays(6)->format('Y-m-d'),'mode_paiement'=>'espèces','statut'=>'Payé','etape'=>'Facture','type_facture'=>'normale','montant_ht'=>$t3['ht'],'montant_tva'=>$t3['tva'],'montant_ttc'=>$t3['ttc']]);
    if ($a3->wasRecentlyCreated) {
        creerLignesAchat($a3->id,$lg3);
    }
    ComptabiliteService::genererEcritureFactureAchat($a3);
    ComptabiliteService::genererEcritureReglementAchat($a3, $a3->montant_ttc, 'espèces', $a3->date_achat->toDateString());
    echo "   ✅ ACH-{$e->id}-003 ({$e->nom}) CAISSE + Ecriture + Règlement\n";

    // Avoir Achat
    $pxAv = max((float)$pa1->prix_achat, 10000);
    $tvaAv = round($pxAv * 0.18,2);
    $av1 = achatFirstOrCreate(['numero_facture'=>"AVO-ACH-{$e->id}-001"],['point_de_vente_id'=>$siege->id,'utilisateur_id'=>$admin->id,'fournisseur_id'=>$fourn1->id,'date_achat'=>now()->subDays(5)->format('Y-m-d'),'mode_paiement'=>'espèces','statut'=>'Payé','etape'=>'Facture','type_facture'=>'avoir','raison_avoir'=>'Retour fournisseur — non conforme','montant_ht'=>-$pxAv,'montant_tva'=>-$tvaAv,'montant_ttc'=>-round($pxAv+$tvaAv,2)]);
    if ($av1->wasRecentlyCreated) {
        creerLignesAchat($av1->id,[['produit_id'=>$pa1->id,'libelle'=>$pa1->nom.' (retour fourn.)','quantite'=>2,'prix_unitaire'=>-($pxAv/2),'unite'=>$pa1->unite,'taux_tva'=>18]]);
    }
    ComptabiliteService::genererEcritureAvoirAchat($av1);
    echo "   ✅ AVO-ACH-{$e->id}-001 ({$e->nom}) Avoir achat + Ecriture\n";
}
echo "\n";

// ═══════════════════════════════════════════════════════
// PHASE 5 — NÉGOCIATION B2B
// ═══════════════════════════════════════════════════════
echo "🤝 PHASE 5 — Communication B2B inter-entreprises\n";

$prodB2b1 = Produit::where('entreprise_id',$bHome->id)->whereIn('type',['marchandise','service'])->first();
$prodB2b2 = Produit::where('entreprise_id',$dck->id)->where('type','service')->first()
         ?? Produit::where('entreprise_id',$dck->id)->where('statut','actif')->first();

if ($prodB2b1) {
    $neg1 = B2bNegotiation::firstOrCreate(
        ['entreprise_client_id'=>$dck->id,'entreprise_fournisseur_id'=>$bHome->id],
        [
            'statut'            => 'Devis Envoyé',
            'type_facturation'  => 'commande',
            'produits_demandes' => json_encode([[
                'produit_id'   => $prodB2b1->id,
                'nom'          => $prodB2b1->nom,
                'quantite'     => 5,
                'unite'        => $prodB2b1->unite,
                'prix_propose' => (float)$prodB2b1->prix_vente,
                'prix_negocie' => round((float)$prodB2b1->prix_vente * 0.95, 2),
            ]]),
            'prix_final'        => round((float)$prodB2b1->prix_vente * 5 * 0.95, 2),
            'historique_discussions' => json_encode([
                ['auteur'=>'DC-KNOWING',  'role'=>'client',      'date'=>now()->subDays(8)->toDateTimeString(), 'message'=>"Bonjour B-HOME, nous souhaitons commander 5 {$prodB2b1->unite} de «{$prodB2b1->nom}». Quelle est votre meilleure offre ?"],
                ['auteur'=>'B-HOME SARL', 'role'=>'fournisseur', 'date'=>now()->subDays(7)->toDateTimeString(), 'message'=>"Bonjour DC-KNOWING ! Nous vous proposons 5 {$prodB2b1->unite} à ".number_format((float)$prodB2b1->prix_vente*0.95,0,',',' ')." FCFA/unité (remise 5%). Livraison : 3 jours."],
                ['auteur'=>'DC-KNOWING',  'role'=>'client',      'date'=>now()->subDays(6)->toDateTimeString(), 'message'=>"Parfait, offre acceptée. Veuillez établir la facture."],
            ]),
        ]
    );
    echo "   ✅ B2B #1 : DC-KNOWING → B-HOME SARL\n";
}

if ($prodB2b2) {
    $neg2 = B2bNegotiation::firstOrCreate(
        ['entreprise_client_id'=>$bHome->id,'entreprise_fournisseur_id'=>$dck->id],
        [
            'statut'            => 'Devis Envoyé',
            'type_facturation'  => 'commande',
            'produits_demandes' => json_encode([[
                'produit_id'   => $prodB2b2->id,
                'nom'          => $prodB2b2->nom,
                'quantite'     => 1,
                'unite'        => $prodB2b2->unite,
                'prix_propose' => (float)$prodB2b2->prix_vente,
                'prix_negocie' => (float)$prodB2b2->prix_vente,
            ]]),
            'prix_final'        => (float)$prodB2b2->prix_vente,
            'historique_discussions' => json_encode([
                ['auteur'=>'B-HOME SARL', 'role'=>'client',      'date'=>now()->subDays(5)->toDateTimeString(), 'message'=>"Bonjour DC-KNOWING, nous avons besoin de «{$prodB2b2->nom}» pour notre exercice 2025. Quelle est votre tarification ?"],
                ['auteur'=>'DC-KNOWING',  'role'=>'fournisseur', 'date'=>now()->subDays(4)->toDateTimeString(), 'message'=>"Bonjour B-HOME, notre tarif est ".number_format((float)$prodB2b2->prix_vente,0,',',' ')." FCFA HT/{$prodB2b2->unite}. Disponible dès la semaine prochaine."],
                ['auteur'=>'B-HOME SARL', 'role'=>'client',      'date'=>now()->subDays(3)->toDateTimeString(), 'message'=>"Validé ! Envoyez-nous la facture proforma s'il vous plaît."],
            ]),
        ]
    );
    echo "   ✅ B2B #2 : B-HOME SARL → DC-KNOWING\n";
}
echo "\n";

// ═══════════════════════════════════════════════════════
// PHASE 6 — TRANSFERTS INTERNES
// ═══════════════════════════════════════════════════════
echo "🔄 PHASE 6 — Transferts internes de stock\n";

foreach ([
    ['e'=>$dck,'src'=>$siegeDck,'dst'=>$pdvDckPlateau,'admin'=>$adminDck,'ref'=>"TRF-{$dck->id}-001",'note'=>'Réapprovisionnement Agence Plateau'],
    ['e'=>$bHome,'src'=>$siegeBHome,'dst'=>$pdvBHomeShowroom,'admin'=>$adminBHome,'ref'=>"TRF-{$bHome->id}-001",'note'=>'Réapprovisionnement Showroom Cocody'],
] as $cfg) {
    $e=$cfg['e']; $src=$cfg['src']; $dst=$cfg['dst']; $admin=$cfg['admin']; $ref=$cfg['ref']; $note=$cfg['note'];
    $prodT = Produit::where('entreprise_id',$e->id)->whereIn('type',['marchandise','consommable_stockable'])->first();
    if (!$prodT) { echo "   ⚠️  {$e->nom} : aucun produit stockable pour transfert\n"; continue; }

    $existing = TransfertStock::where('point_de_vente_source_id',$src->id)->where('point_de_vente_destination_id',$dst->id)->where('produit_id',$prodT->id)->first();
    if (!$existing) {
        $trf = TransfertStock::create([
            'produit_id'                  => $prodT->id,
            'point_de_vente_source_id'    => $src->id,
            'point_de_vente_destination_id'=> $dst->id,
            'quantite'                    => 10,
            'statut'                      => 'approuve',
            'demandeur_id'                => $admin->id,
            'approbateur_id'              => $admin->id,
            'note'                        => $note,
            'approuve_le'                 => now(),
        ]);
        // Mouvements stock
        $stSrc = Stock::where('produit_id',$prodT->id)->where('point_de_vente_id',$src->id)->first();
        $stDst = Stock::firstOrCreate(['produit_id'=>$prodT->id,'point_de_vente_id'=>$dst->id],['quantite_disponible'=>0,'stock_minimum'=>2]);
        if ($stSrc) {
            $avSrc = $stSrc->quantite_disponible;
            $avDst = $stDst->quantite_disponible;
            if ($avSrc >= 10) {
                $stSrc->decrement('quantite_disponible',10);
                $stDst->increment('quantite_disponible',10);
                mouvement($prodT->id,$src->id,'Sortie','Transfert',10,$avSrc,$ref,$admin->id);
                mouvement($prodT->id,$dst->id,'Entree','Transfert',10,$avDst,$ref,$admin->id);
            }
        }
        echo "   ✅ {$ref} : {$src->nom} → {$dst->nom} — 10×{$prodT->nom}\n";
    } else {
        echo "   ℹ️  Transfert {$e->nom} déjà existant\n";
    }
}
echo "\n";

// ═══════════════════════════════════════════════════════
// LOGOS ENTREPRISES (pour factures)
// ═══════════════════════════════════════════════════════
echo "🏢 Logos entreprises pour factures...\n";
Entreprise::where('id',$dck->id)->update([
    'logo_path' => 'https://images.unsplash.com/photo-1568515387631-8b650bbcdb90?w=200&h=200&fit=crop'
]);
Entreprise::where('id',$bHome->id)->update([
    'logo_path' => 'https://images.unsplash.com/photo-1560472355-536de3962603?w=200&h=200&fit=crop'
]);
echo "   ✅ Logos mis à jour\n\n";

echo "══════════════════════════════════════════════════════\n";
echo "✨  MEGA SEED v3 (Avec écritures comptables) terminé !\n";
echo "══════════════════════════════════════════════════════\n";
