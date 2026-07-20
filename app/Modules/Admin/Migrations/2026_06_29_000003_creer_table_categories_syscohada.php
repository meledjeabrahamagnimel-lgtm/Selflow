<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Exécuter la migration.
     */
    public function up(): void
    {
        Schema::create('categories_syscohada', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id')->nullable();
            $table->string('libelle_affiche');
            $table->string('compte_comptable_reel', 20);
            $table->enum('type_lie', ['Achat', 'Vente']);
            $table->string('type_produit_lie');
            $table->timestamps();

            $table->foreign('entreprise_id')
                ->references('id')
                ->on('entreprises')
                ->onDelete('cascade');
        });

        // Insertion des mots-clés standards par défaut
        $defauts = [
            [
                'libelle_affiche'       => 'Vente de marchandises dans la région',
                'compte_comptable_reel' => '701100',
                'type_lie'              => 'Vente',
                'type_produit_lie'      => 'Marchandise',
                'created_at'            => now(),
                'updated_at'            => now(),
            ],
            [
                'libelle_affiche'       => 'Vente de marchandises hors région',
                'compte_comptable_reel' => '701200',
                'type_lie'              => 'Vente',
                'type_produit_lie'      => 'Marchandise',
                'created_at'            => now(),
                'updated_at'            => now(),
            ],
            [
                'libelle_affiche'       => 'Achat de marchandises',
                'compte_comptable_reel' => '601100',
                'type_lie'              => 'Achat',
                'type_produit_lie'      => 'Marchandise',
                'created_at'            => now(),
                'updated_at'            => now(),
            ],
            [
                'libelle_affiche'       => 'Frais accessoires d\'achat',
                'compte_comptable_reel' => '601500',
                'type_lie'              => 'Achat',
                'type_produit_lie'      => 'Marchandise',
                'created_at'            => now(),
                'updated_at'            => now(),
            ],
            [
                'libelle_affiche'       => 'Fournitures non stockables (Eau, Électricité)',
                'compte_comptable_reel' => '605100',
                'type_lie'              => 'Achat',
                'type_produit_lie'      => 'Consommable',
                'created_at'            => now(),
                'updated_at'            => now(),
            ],
        ];

        DB::table('categories_syscohada')->insert($defauts);
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories_syscohada');
    }
};
