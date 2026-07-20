<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journal_audit', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('utilisateur_id')->nullable()->index();
            $table->string('action', 100);                  // ex: connexion, creation_vente, modification_role
            $table->string('entite', 100)->nullable();       // ex: Vente, Produit, Utilisateur
            $table->unsignedBigInteger('entite_id')->nullable()->index();
            $table->json('ancienne_valeur')->nullable();     // snapshot avant modification
            $table->json('nouvelle_valeur')->nullable();     // snapshot après modification
            $table->string('adresse_ip', 45)->nullable();   // IPv4 + IPv6
            $table->unsignedBigInteger('point_de_vente_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();   // jamais de updated_at (insert only)

            // Clé étrangère optionnelle (on ne bloque pas la création si l'utilisateur est supprimé)
            $table->foreign('utilisateur_id')->references('id')->on('utilisateurs')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_audit');
    }
};
