<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Le journal de stock devient un journal.
 *
 * Trois manques le tenaient à l'écart de ce qu'un journal doit être :
 *
 * 1. **Rien ne rattachait un mouvement à sa pièce.** `reference_document` est
 *    une chaîne libre — le numéro de facture, ou « REBUT-20260809143012 ».
 *    Renuméroter une facture rompait le lien sans que rien ne le signale, et
 *    remonter d'un mouvement à la vente qui l'a produit demandait une
 *    recherche par texte. Un couple `piece_type` / `piece_id` le rattache
 *    désormais vraiment, `reference_document` restant pour l'affichage.
 *
 * 2. **Rien ne portait une contre-passation.** `VenteControleur` effaçait les
 *    mouvements d'une vente qu'on modifiait. Un journal ne s'efface pas : la
 *    sortie de dix sacs a eu lieu, et si elle était erronée, c'est une entrée
 *    de dix sacs qui la corrige. La colonne `contrepasse_id` désigne le
 *    mouvement qu'une écriture vient annuler ; les deux restent lisibles.
 *
 * 3. **`point_de_vente_source_id` mentait.** Sur la sortie d'un transfert, la
 *    colonne recevait la *destination* ; sur l'entrée, la *source*. Elle a
 *    toujours porté la contrepartie — l'autre site du mouvement — et prend le
 *    nom qui lui revient : `point_de_vente_contrepartie_id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mouvements_stock', function (Blueprint $table) {
            // La pièce qui a produit le mouvement : une vente, un achat, un
            // ordre de production, un transfert. Nullable, car un ajustement
            // d'inventaire n'a pas d'autre pièce que lui-même.
            $table->string('piece_type')->nullable()->after('reference_document');
            $table->unsignedBigInteger('piece_id')->nullable()->after('piece_type');

            // Le mouvement que celui-ci annule. Un journal se contre-passe.
            $table->unsignedBigInteger('contrepasse_id')->nullable()->after('piece_id');

            $table->index(['piece_type', 'piece_id'], 'mvt_piece_index');
            $table->index('contrepasse_id', 'mvt_contrepasse_index');

            $table->foreign('contrepasse_id', 'fk_mvt_contrepasse')
                ->references('id')->on('mouvements_stock')
                ->nullOnDelete();
        });

        $this->renommerLaContrepartie('point_de_vente_source_id', 'point_de_vente_contrepartie_id');

        $this->normaliserLesLibelles();
    }

    public function down(): void
    {
        $this->renommerLaContrepartie('point_de_vente_contrepartie_id', 'point_de_vente_source_id');

        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->dropForeign('fk_mvt_contrepasse');
            $table->dropIndex('mvt_piece_index');
            $table->dropIndex('mvt_contrepasse_index');
            $table->dropColumn(['piece_type', 'piece_id', 'contrepasse_id']);
        });
    }

    private function renommerLaContrepartie(string $de, string $vers): void
    {
        if (Schema::hasColumn('mouvements_stock', $de) && !Schema::hasColumn('mouvements_stock', $vers)) {
            Schema::table('mouvements_stock', function (Blueprint $table) use ($de, $vers) {
                $table->renameColumn($de, $vers);
            });
        }
    }

    /**
     * Aligner les valeurs déjà en base sur les constantes du modèle.
     *
     * `ProductionControleur` écrivait « Entree » sans accent là où tout le
     * reste écrit « Entrée » : l'écran des mouvements compare la chaîne exacte,
     * et une entrée de production s'y affichait donc en rouge, précédée d'un
     * signe moins. Un mois de production ressemblait à un mois de sorties.
     *
     * Les sous-types suivent la même logique : l'écran les compare déjà en
     * minuscules, les requêtes de filtre non. On fixe la forme minuscule, qui
     * ne dépend ni de l'accent ni de la casse du contrôleur qui a écrit.
     */
    private function normaliserLesLibelles(): void
    {
        DB::table('mouvements_stock')->where('type_mouvement', 'Entree')
            ->update(['type_mouvement' => 'Entrée']);

        foreach (['Reception' => 'reception', 'Livraison' => 'livraison',
                  'Transfert' => 'transfert', 'Rebut' => 'rebut',
                  'Ajustement' => 'inventaire', 'Production' => 'production_entree'] as $avant => $apres) {
            DB::table('mouvements_stock')->where('sous_type', $avant)
                ->update(['sous_type' => $apres]);
        }
    }
};
