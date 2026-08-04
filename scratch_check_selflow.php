<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$updated = App\Modules\Admin\Modeles\Vente::where('numero_facture', 'like', 'AVO-%')
    ->where('type_facture', '!=', 'avoir')
    ->get();

foreach ($updated as $v) {
    $v->update(['type_facture' => 'avoir']);
    echo "Restored type_facture of Vente #{$v->id} ({$v->numero_facture}) to 'avoir'\n";
}

echo "Avoir type_facture restoration complete.\n";
