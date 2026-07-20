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
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('produit_id');
            $table->unsignedBigInteger('point_de_vente_id');
            $table->integer('quantite_disponible')->default(0);
            $table->integer('stock_minimum')->default(5);
            $table->integer('stock_maximum')->default(100);
            $table->timestamps();

            $table->unique(['produit_id', 'point_de_vente_id']);
            
            $table->foreign('produit_id')
                ->references('id')
                ->on('produits')
                ->onDelete('cascade');
                
            $table->foreign('point_de_vente_id')
                ->references('id')
                ->on('points_de_vente')
                ->onDelete('cascade');
        });

        // Migration de données : Transférer les stocks existants vers le point de vente par défaut (Siège)
        try {
            $produits = DB::table('produits')->get();
            foreach ($produits as $p) {
                // Trouver le point de vente "Siège" pour cette entreprise
                $pdv = DB::table('points_de_vente')
                    ->where('entreprise_id', $p->entreprise_id)
                    ->where('nom', 'Siège')
                    ->first();

                if (!$pdv) {
                    $pdv = DB::table('points_de_vente')
                        ->where('entreprise_id', $p->entreprise_id)
                        ->first();
                }

                if ($pdv) {
                    DB::table('stocks')->insert([
                        'produit_id'          => $p->id,
                        'point_de_vente_id'   => $pdv->id,
                        'quantite_disponible' => $p->stock_actuel ?? 0,
                        'stock_minimum'       => $p->stock_minimum ?? 5,
                        'stock_maximum'       => 100,
                        'created_at'          => now(),
                        'updated_at'          => now(),
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Ignorer silencieusement si la table n'a pas encore de données lors d'un fresh install
        }

        // Supprimer les colonnes obsolètes de la table produits
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn(['stock_actuel', 'stock_minimum']);
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        // Recréer les colonnes sur produits
        Schema::table('produits', function (Blueprint $table) {
            $table->integer('stock_actuel')->default(0);
            $table->integer('stock_minimum')->default(5);
        });

        // Transférer à nouveau les stocks consolidés vers produits si possible
        try {
            $stocks = DB::table('stocks')->get();
            foreach ($stocks as $s) {
                DB::table('produits')
                    ->where('id', $s->produit_id)
                    ->update([
                        'stock_actuel'  => $s->quantite_disponible,
                        'stock_minimum' => $s->stock_minimum,
                    ]);
            }
        } catch (\Exception $e) {
        }

        Schema::dropIfExists('stocks');
    }
};
