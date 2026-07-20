<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->string('numero_fne', 100)->nullable()->after('numero_facture')
                ->comment('Numéro de Facture Normalisé Électronique retourné par la DGI');
            $table->text('signature_dgi')->nullable()->after('qr_code_data')
                ->comment('Signature cryptographique de la DGI');
        });
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn(['numero_fne', 'signature_dgi']);
        });
    }
};
