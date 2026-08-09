<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les lots : la péremption appartient à l'arrivage, pas à l'article.
 *
 * `produits.date_peremption` porte **une seule date par article**. Une
 * pharmacie qui reçoit trois arrivages de paracétamol — mars, juin, novembre —
 * n'en enregistre qu'un : la saisie du troisième écrase les deux premiers, et
 * les boîtes de mars restent en rayon sans que rien ne les signale. Le même
 * défaut vaut pour un dépôt de boissons, une boulangerie, un magasin de
 * cosmétiques : c'est-à-dire pour l'essentiel du commerce ivoirien.
 *
 * Trois manques, donc :
 *
 * - **une date par article** au lieu d'une par arrivage ;
 * - **aucune traçabilité** — un rappel de lot du fabricant est impossible à
 *   honorer, faute de savoir quel arrivage est parti chez quel client ;
 * - **aucun ordre de sortie**. Rien n'impose de servir d'abord ce qui périme
 *   le plus tôt, et la marchandise la plus ancienne reste au fond du rayon.
 *
 * ## Ce que la structure pose
 *
 * Un lot est un arrivage : un numéro, une date de péremption, une quantité, sur
 * **un site**. Le même numéro de lot dans deux magasins fait deux fiches, comme
 * le stock lui-même, parce que la marchandise n'est pas au même endroit.
 *
 * `mouvement_lots` dit quels lots un mouvement a consommés. Un mouvement de
 * stock reste **un seul mouvement** — la comptabilité, le CUMP (Coût Unitaire
 * Moyen Pondéré) et le journal ne changent pas — et porte à côté de lui le
 * détail de sa ventilation par lot. C'est ce qui permet de répondre à un rappel
 * sans réécrire l'inventaire permanent.
 *
 * ## L'ordre de sortie : FEFO, et non FIFO
 *
 * *First Expired, First Out* : on sert d'abord ce qui périme le plus tôt, et
 * non ce qui est arrivé le premier. Les deux coïncident souvent, jamais
 * toujours : un arrivage récent à date courte doit partir avant un arrivage
 * ancien à date longue. Le FIFO laisserait périmer le premier.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id');
            $table->unsignedBigInteger('produit_id');
            $table->unsignedBigInteger('point_de_vente_id');

            // Le numéro du fabricant, ou celui que le magasin attribue. C'est
            // ce qu'un rappel de lot désigne.
            $table->string('numero_lot', 100);

            $table->date('date_peremption')->nullable();
            $table->date('date_fabrication')->nullable();

            // Même précision que le stock : le gramme sur le kilo.
            $table->decimal('quantite', 15, 3)->default(0);

            // Le coût de cet arrivage-là. Le CUMP (Coût Unitaire Moyen
            // Pondéré) reste la valeur du stock ; celui-ci sert à savoir ce
            // qu'a coûté un lot qu'on met au rebut.
            $table->decimal('cout_unitaire', 15, 4)->default(0);

            $table->unsignedBigInteger('fournisseur_id')->nullable();
            $table->timestamps();

            $table->foreign('entreprise_id')->references('id')->on('entreprises')->cascadeOnDelete();
            $table->foreign('produit_id')->references('id')->on('produits')->cascadeOnDelete();
            $table->foreign('point_de_vente_id')->references('id')->on('points_de_vente')->cascadeOnDelete();
            $table->foreign('fournisseur_id')->references('id')->on('fournisseurs')->nullOnDelete();

            // Un numéro de lot ne désigne qu'un arrivage sur un site : deux
            // fiches pour le même numéro rendraient le rappel inexploitable.
            $table->unique(['produit_id', 'point_de_vente_id', 'numero_lot'], 'lots_unicite');

            // L'index qui sert le FEFO : la sortie cherche, pour un article et
            // un site, le lot non vide qui périme le plus tôt.
            $table->index(['produit_id', 'point_de_vente_id', 'date_peremption'], 'lots_fefo');
        });

        Schema::create('mouvement_lots', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('mouvement_stock_id');
            $table->unsignedBigInteger('lot_id');
            $table->decimal('quantite', 15, 3);
            $table->timestamps();

            $table->foreign('mouvement_stock_id')->references('id')->on('mouvements_stock')->cascadeOnDelete();
            $table->foreign('lot_id')->references('id')->on('lots')->cascadeOnDelete();

            $table->index('mouvement_stock_id', 'mvt_lots_mouvement');
            $table->index('lot_id', 'mvt_lots_lot');
        });

        Schema::table('produits', function (Blueprint $table) {
            // Tous les articles ne se suivent pas par lot : un sac de ciment
            // n'a pas de date, et imposer un numéro de lot à sa réception
            // ferait perdre du temps sans rien apporter. Le suivi s'active
            // article par article.
            $table->boolean('suivi_par_lot')->default(false)->after('date_peremption');

            // Le préavis, en jours, avant lequel un lot est signalé. Trente
            // jours conviennent à l'alimentaire ; un médicament se retire des
            // rayons bien plus tôt, un cosmétique bien plus tard.
            $table->unsignedSmallInteger('preavis_peremption')->default(30)->after('suivi_par_lot');
        });
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn(['suivi_par_lot', 'preavis_peremption']);
        });

        Schema::dropIfExists('mouvement_lots');
        Schema::dropIfExists('lots');
    }
};
