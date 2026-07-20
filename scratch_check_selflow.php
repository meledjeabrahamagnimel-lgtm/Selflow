<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "SELFLOW CLIENTS:\n";
print_r(DB::table('clients')->get()->toArray());

echo "\nSELFLOW FOURNISSEURS:\n";
print_r(DB::table('fournisseurs')->get()->toArray());
