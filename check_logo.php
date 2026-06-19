<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

foreach (\App\Modules\Admin\Modeles\Entreprise::all() as $e) {
    echo "ID: {$e->id} | Nom: {$e->nom} | Logo Path: {$e->logo_path}\n";
    if ($e->logo_path) {
        $realPath = storage_path('app/public/' . $e->logo_path);
        echo "  Storage Path: {$realPath} | Exists: " . (file_exists($realPath) ? "YES" : "NO") . "\n";
        echo "  Storage URL: " . Storage::url($e->logo_path) . "\n";
    }
}
