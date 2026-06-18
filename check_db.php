<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== VENTES ===\n";
foreach (\App\Modules\Admin\Modeles\Vente::all() as $v) {
    echo "ID: {$v->id} | Facture: {$v->numero_facture} | Mode: {$v->mode_paiement} | TTC: {$v->montant_ttc} | Statut: {$v->statut} | Etape: {$v->etape}\n";
}

echo "\n=== TRESORERIE ===\n";
foreach (\App\Modules\Admin\Modeles\TresorerieJournal::all() as $t) {
    echo "ID: {$t->id} | Ref: {$t->reference_document} | Entree: {$t->montant_entree} | Sortie: {$t->montant_sortie} | Mode: {$t->mode_paiement}\n";
}

echo "\n=== ECRITURES COMPTABLES ===\n";
foreach (\App\Modules\Admin\Modeles\EcritureComptable::all() as $e) {
    echo "ID: {$e->id} | Ref: {$e->reference_document} | Journal: {$e->code_journal} | Debit: {$e->compte_debit} / {$e->debit} | Credit: {$e->compte_credit} / {$e->credit}\n";
}
