<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Jobs\NormaliserFactureFne;
use App\Modules\Admin\Modeles\Vente;

$v = Vente::find(78);
if (!$v) {
    echo "vente_not_found\n";
    exit(1);
}

NormaliserFactureFne::dispatchSync($v);

$v->refresh();

echo 'normalized=' . ($v->normalise ? '1' : '0') . PHP_EOL;
echo 'fne_invoice_id=' . ($v->fne_invoice_id ?? 'null') . PHP_EOL;
foreach ($v->details as $d) {
    echo 'detail_' . $d->id . '->' . ($d->fne_invoice_item_id ?? 'null') . PHP_EOL;
}
