<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$vente = App\Modules\Admin\Modeles\Vente::with(['pointDeVente', 'client', 'pointDeVente.entreprise', 'parent'])->find(68);
if (! $vente) {
    echo 'VENTE NOT FOUND\n';
    exit(1);
}

echo 'vente id: ' . $vente->id . "\n";
echo 'pdv_id: ' . $vente->point_de_vente_id . "\n";
echo 'pdv_nom: ' . ($vente->pointDeVente ? $vente->pointDeVente->nom : 'NULL') . "\n";
echo 'pdv_entreprise: ' . ($vente->pointDeVente && $vente->pointDeVente->entreprise ? $vente->pointDeVente->entreprise->nom : 'NULL') . "\n";
echo 'mode_paiement: ' . ($vente->mode_paiement ?? 'NULL') . "\n";
echo 'moyen_bancaire: ' . ($vente->moyen_bancaire ?? 'NULL') . "\n";
$payload = App\Modules\Admin\Services\FneService::normaliserFacture($vente, false);
print_r($payload);
