<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->unique()->constrained('entreprises')->cascadeOnDelete();
            $table->enum('regime', ['RNI', 'RSI', 'TEE', 'RNE'])->nullable();
            $table->enum('categorie', ['CAS_A', 'CAS_B'])->nullable();
            // TVA
            $table->boolean('tva_active')->default(false);
            $table->decimal('tva_taux', 5, 2)->default(18.00);
            // TSE — 0,1% du CA HT (CAS A uniquement)
            $table->boolean('tse_active')->default(false);
            $table->decimal('tse_taux', 5, 2)->default(0.10);
            // TDT — 1,5% des paiements espèces > seuil
            $table->boolean('tdt_active')->default(false);
            $table->decimal('tdt_taux', 5, 2)->default(1.50);
            $table->decimal('tdt_seuil', 12, 2)->default(5000.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_configurations');
    }
};
