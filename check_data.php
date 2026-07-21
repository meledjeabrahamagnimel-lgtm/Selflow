<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->boot();

use App\Modules\Admin\Modeles\Vente;
use App\Modules\Admin\Modeles\Achat;
use App\Modules\Admin\Modeles\EcritureComptable;
use App\Modules\Admin\Modeles\TresorerieJournal;
use App\Modules\Admin\Modeles\OrdreProduction;

echo "=== VENTES ===\n";
foreach(Vente::select('numero_facture','type_facture','etape','statut','montant_ttc')->get() as $v) {
    echo "{$v->type_facture} | {$v->etape} | {$v->numero_facture} | {$v->statut} | " . number_format($v->montant_ttc) . " FCFA\n";
}

echo "\n=== ACHATS ===\n";
foreach(Achat::select('numero_facture','type_facture','etape','statut','montant_ttc')->get() as $a) {
    echo "{$a->type_facture} | {$a->etape} | {$a->numero_facture} | {$a->statut} | " . number_format($a->montant_ttc) . " FCFA\n";
}

echo "\n=== ECRITURES (10 premieres) ===\n";
foreach(EcritureComptable::select('reference_document','code_journal','libelle','compte_debit','compte_credit','debit','credit')->limit(10)->get() as $e) {
    $d = $e->compte_debit ?? '---';
    $c = $e->compte_credit ?? '---';
    echo "[{$e->code_journal}] Ref:{$e->reference_document} | D:{$d} C:{$c} | {$e->libelle}\n";
}

echo "\n=== TRESO ===\n";
foreach(TresorerieJournal::select('reference_document','libelle','type_operation','montant_entree','montant_sortie')->get() as $t) {
    echo "{$t->type_operation} | {$t->reference_document} | {$t->libelle}\n";
}

echo "\n=== ORDRES PRODUCTION ===\n";
foreach(OrdreProduction::select('code_ordre','statut')->get() as $op) {
    echo "{$op->code_ordre} | {$op->statut}\n";
}

echo "\nTotal ecritures: " . EcritureComptable::count() . "\n";
