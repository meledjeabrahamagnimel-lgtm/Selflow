<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sticker_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->string('id_transaction_externe', 100)->nullable();
            $table->enum('operateur', ['ORANGE', 'MOOV', 'MTN']);
            $table->string('telephone', 30);
            $table->unsignedInteger('nombre_stickers');
            $table->decimal('montant', 12, 2);
            $table->enum('statut', ['succes', 'en_attente', 'echec'])->default('en_attente');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sticker_transactions');
    }
};
