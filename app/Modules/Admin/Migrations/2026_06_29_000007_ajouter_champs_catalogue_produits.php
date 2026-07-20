<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── Nouvelles colonnes sur produits ──────────────────────────────────
        Schema::table('produits', function (Blueprint $table) {
            $table->string('photo')->nullable()->comment('Chemin relatif image principale');
            $table->date('date_arrivee')->nullable();
            $table->date('date_peremption')->nullable();
            $table->string('provenance', 200)->nullable();
            $table->text('description_inventaire')->nullable();
            $table->enum('statut', ['actif', 'archive'])->default('actif');
        });

        // ─── Table produit_details_libres ─────────────────────────────────────
        Schema::create('produit_details_libres', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produit_id')->index();
            $table->foreign('produit_id')->references('id')->on('produits')->cascadeOnDelete();
            $table->string('titre', 150);
            $table->text('description');
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('produit_details_libres');

        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn([
                'photo', 'date_arrivee', 'date_peremption',
                'provenance', 'description_inventaire', 'statut',
            ]);
        });
    }
};
