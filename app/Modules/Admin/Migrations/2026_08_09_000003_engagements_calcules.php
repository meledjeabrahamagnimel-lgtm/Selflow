<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les engagements cessent d'être des compteurs.
 *
 * `produits.quantite_commandee` et `produits.quantite_a_receptionner`
 * existaient depuis l'origine, s'affichaient sur trois écrans, entraient dans
 * le calcul du prévisionnel — et **rien ne les écrivait jamais**. Seul le jeu
 * de démonstration les posait, à zéro. Le prévisionnel valait donc toujours le
 * stock disponible, et la colonne « Commandé » d'un magasin qui attend trente
 * sacs affichait 0.
 *
 * Deux défauts, pas un :
 *
 * 1. **Personne ne les alimentait.** Un compteur dénormalisé doit être
 *    incrémenté à la commande, décrémenté à la livraison, corrigé à
 *    l'annulation, à la modification, à l'avoir. Cinq occasions de dériver, et
 *    aucune n'était traitée.
 * 2. **Ils étaient sur `produits`, donc globaux.** Trente sacs commandés au
 *    magasin d'Abidjan seraient apparus comme engagés au dépôt de Bouaké.
 *
 * La correction ne consiste pas à écrire ces colonnes partout où il aurait
 * fallu : elle consiste à ne plus les stocker. L'engagement se déduit des
 * lignes qui l'ont créé — `quantite - quantite_livree` sur les bons de
 * commande de vente, `quantite - quantite_receptionnee` sur ceux d'achat — et
 * une valeur déduite ne dérive pas.
 *
 * Voir `Produit::quantiteCommandee()` et `Produit::quantiteAReceptionner()`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn(['quantite_commandee', 'quantite_a_receptionner']);
        });
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->decimal('quantite_commandee', 15, 3)->default(0);
            $table->decimal('quantite_a_receptionner', 15, 3)->default(0);
        });
    }
};
