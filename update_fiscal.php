<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Modules\Admin\Modeles\Client;
use App\Modules\Admin\Modeles\Fournisseur;

foreach (Client::all() as $c) {
    $c->update([
        'ncc' => '198234' . $c->id . ' X',
        'regime_imposition' => 'RNI'
    ]);
}

foreach (Fournisseur::all() as $f) {
    $f->update([
        'ncc' => '201987' . $f->id . ' Y',
        'regime_imposition' => 'RSI',
        'adresse' => $f->adresse ?: 'Abidjan, Zone ' . $f->id
    ]);
}

echo "Mise à jour des données fiscales terminée avec succès !";
