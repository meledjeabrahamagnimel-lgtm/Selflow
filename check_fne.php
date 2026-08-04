<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Modules\Admin\Modeles\Vente;

$rows = Vente::where('normalise', true)->orderByDesc('id')->take(20)->get();
foreach ($rows as $v) {
    echo $v->id . ' | ' . ($v->fichier_fne_pdf_url ?: 'NULL') . ' | ' . ($v->numero_fne ?: 'NOFNE') . ' | ' . ($v->normalise ? 'NORMALISE' : 'NON') . PHP_EOL;
}
