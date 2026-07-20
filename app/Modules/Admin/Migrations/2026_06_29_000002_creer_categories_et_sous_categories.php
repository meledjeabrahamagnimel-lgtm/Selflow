<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Exécuter la migration.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->string('nom');
            $table->string('prefixe', 10);
            $table->timestamps();

            $table->unique(['entreprise_id', 'nom']);
            $table->unique(['entreprise_id', 'prefixe']);
            $table->foreign('entreprise_id')
                ->references('id')
                ->on('entreprises')
                ->onDelete('cascade');
        });

        Schema::create('sous_categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('categorie_id');
            $table->string('nom');
            $table->timestamps();

            $table->unique(['categorie_id', 'nom']);
            $table->foreign('categorie_id')
                ->references('id')
                ->on('categories')
                ->onDelete('cascade');
        });

        // Ajouter les colonnes de clé étrangère sur la table produits
        Schema::table('produits', function (Blueprint $table) {
            $table->unsignedBigInteger('categorie_id')->nullable()->after('nom');
            $table->unsignedBigInteger('sous_categorie_id')->nullable()->after('categorie_id');

            $table->foreign('categorie_id')
                ->references('id')
                ->on('categories')
                ->onDelete('set null');

            $table->foreign('sous_categorie_id')
                ->references('id')
                ->on('sous_categories')
                ->onDelete('set null');
        });

        // Migration de données : Convertir la catégorie texte libre en relation structurée
        try {
            $produits = DB::table('produits')->get();
            foreach ($produits as $p) {
                if (empty($p->categorie)) {
                    continue;
                }

                $nomCat = trim($p->categorie);
                $entrepriseId = $p->entreprise_id;

                // Trouver ou créer la catégorie
                $catId = DB::table('categories')
                    ->where('entreprise_id', $entrepriseId)
                    ->where('nom', $nomCat)
                    ->value('id');

                if (!$catId) {
                    // Générer un préfixe propre (ex : Épicerie -> EPIC)
                    $cleanNom = Str::slug($nomCat, '');
                    $cleanNom = preg_replace('/[^a-zA-Z]/', '', $cleanNom);
                    $prefixe = strtoupper(substr($cleanNom, 0, 4));
                    if (empty($prefixe)) {
                        $prefixe = 'PROD';
                    }

                    // Assurer l'unicité du préfixe pour cette entreprise
                    $prefixeOriginal = $prefixe;
                    $compteur = 1;
                    while (DB::table('categories')->where('entreprise_id', $entrepriseId)->where('prefixe', $prefixe)->exists()) {
                        $prefixe = substr($prefixeOriginal, 0, 3) . $compteur;
                        $compteur++;
                    }

                    $catId = DB::table('categories')->insertGetId([
                        'entreprise_id' => $entrepriseId,
                        'nom'           => $nomCat,
                        'prefixe'       => $prefixe,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }

                // Associer le produit à la catégorie
                DB::table('produits')
                    ->where('id', $p->id)
                    ->update(['categorie_id' => $catId]);
            }
        } catch (\Exception $e) {
            // Ignorer silencieusement si la table n'a pas encore de données lors d'un fresh install
        }

        // Supprimer l'ancienne colonne texte de produits
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn('categorie');
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        // Recréer la colonne de catégorie texte
        Schema::table('produits', function (Blueprint $table) {
            $table->string('categorie')->nullable()->after('nom');
        });

        // Restaurer la valeur texte à partir des catégories
        try {
            $produits = DB::table('produits')
                ->join('categories', 'produits.categorie_id', '=', 'categories.id')
                ->select('produits.id', 'categories.nom as cat_nom')
                ->get();

            foreach ($produits as $p) {
                DB::table('produits')
                    ->where('id', $p->id)
                    ->update(['categorie' => $p->cat_nom]);
            }
        } catch (\Exception $e) {
        }

        // Supprimer les contraintes et colonnes sur produits
        Schema::table('produits', function (Blueprint $table) {
            $table->dropForeign(['categorie_id']);
            $table->dropForeign(['sous_categorie_id']);
            $table->dropColumn(['categorie_id', 'sous_categorie_id']);
        });

        Schema::dropIfExists('sous_categories');
        Schema::dropIfExists('categories');
    }
};
