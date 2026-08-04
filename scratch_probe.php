<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Admin\Modeles\Vente;

$v = Vente::with('parent.details', 'details')->find(83);
$parent = Vente::with('details')->find(78);
if (!$v) {
    echo "vente_not_found\n";
    exit(1);
}

echo 'vente83_type=' . ($v->type_facture ?? 'null') . PHP_EOL;
echo 'vente83_statut=' . ($v->statut ?? 'null') . PHP_EOL;
echo 'normalise=' . ($v->normalise ? '1' : '0') . PHP_EOL;
echo 'parent_id=' . ($v->parent_id ?? 'null') . PHP_EOL;
echo 'fne_invoice_id=' . ($v->fne_invoice_id ?? 'null') . PHP_EOL;
echo 'parent_fne=' . ($v->parent?->fne_invoice_id ?? 'null') . PHP_EOL;
foreach (($v->details ?? collect()) as $d) {
    echo 'child_detail_' . $d->id . '->' . ($d->fne_invoice_item_id ?? 'null') . PHP_EOL;
}
foreach (($v->parent?->details ?? collect()) as $d) {
    echo 'parent_detail_' . $d->id . '->' . ($d->fne_invoice_item_id ?? 'null') . PHP_EOL;
}

echo 'parent78_statut=' . ($parent?->statut ?? 'null') . PHP_EOL;
echo 'parent78_mode=' . ($parent?->mode_paiement ?? 'null') . PHP_EOL;
echo 'parent78_normalise=' . ($parent?->normalise ? '1' : '0') . PHP_EOL;
