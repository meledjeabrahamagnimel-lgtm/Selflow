<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Une migration décrit un état, pas un geste.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Ce qui s'est passé
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Le déploiement du 27 août 2026 s'est arrêté net :
 *
 *     SQLSTATE[42000] 1091 Can't DROP COLUMN `quantite_commandee`;
 *     check that it exists
 *
 * `produits.quantite_commandee` et `quantite_a_receptionner` n'ont jamais
 * existé sur ce serveur. Elles ont été **ajoutées le 20 juillet à
 * `2026_06_05_000004_creer_table_produits`**, une migration datée du 5 juin et
 * appliquée en production bien avant. Une migration déjà jouée ne se rejoue
 * pas : retoucher son fichier ne change rien à la base qui l'a passée.
 *
 * D'où l'écart que ces épreuves existent pour empêcher : **en local, chaque
 * `migrate:fresh` recrée tout dans l'ordre et trouve toujours ce qu'il
 * supprime ; en production, la base porte l'histoire réelle des migrations
 * telles qu'elles étaient le jour où elles sont passées.** Une suite verte ne
 * dit rien de ce second cas.
 *
 * Le même écart s'était déjà produit sur `entreprises` — `secteur_activite` et
 * `modules_actifs`, ajoutés au même endroit le même jour — et avait demandé
 * une migration de rattrapage,
 * `2026_07_20_133019_add_missing_columns_to_entreprises`. Personne n'avait vu
 * que `produits` portait la même retouche.
 */
class MigrationsRejouablesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Les répertoires où vivent les migrations de l'application.
     *
     * @return array<int, string>
     */
    private function migrations(): array
    {
        return array_merge(
            glob(app_path('Modules/*/Migrations/*.php')) ?: [],
            glob(database_path('migrations/*.php')) ?: []
        );
    }

    /**
     * Le corps de `up()`, sans celui de `down()`.
     *
     * `down()` n'est pas concernée : elle défait ce que `up()` vient de faire,
     * dans une base dont on sait l'état.
     */
    private function corpsDeUp(string $source): string
    {
        if (!str_contains($source, 'public function up()')) {
            return '';
        }

        $apres = explode('public function up()', $source, 2)[1];

        return explode('public function down()', $apres, 2)[0];
    }

    // ── La reproduction ──────────────────────────────────────────────

    public function test_le_retrait_des_engagements_passe_sur_une_base_qui_n_a_jamais_eu_ces_colonnes(): void
    {
        // C'est l'état exact du serveur : la table existe, les colonnes non.
        $this->assertFalse(Schema::hasColumn('produits', 'quantite_commandee'));
        $this->assertFalse(Schema::hasColumn('produits', 'quantite_a_receptionner'));

        $migration = require app_path(
            'Modules/Admin/Migrations/2026_08_09_000003_engagements_calcules.php'
        );

        $migration->up();

        // Rien à défaire, donc rien qui échoue — et la table est intacte.
        $this->assertTrue(Schema::hasTable('produits'));
        $this->assertFalse(Schema::hasColumn('produits', 'quantite_commandee'));
    }

    public function test_le_retrait_des_engagements_se_rejoue_sans_dommage(): void
    {
        $migration = require app_path(
            'Modules/Admin/Migrations/2026_08_09_000003_engagements_calcules.php'
        );

        // On remet les colonnes pour reproduire une base qui les a — celle
        // d'une installation faite après le 20 juillet.
        Schema::table('produits', function ($table) {
            $table->decimal('quantite_commandee', 15, 3)->default(0);
            $table->decimal('quantite_a_receptionner', 15, 3)->default(0);
        });

        $migration->up();
        $this->assertFalse(Schema::hasColumn('produits', 'quantite_commandee'));

        // Et une seconde fois : c'est ce que fait un déploiement rejoué après
        // une interruption, MySQL n'annulant pas ses ordres de structure.
        $migration->up();
        $this->assertFalse(Schema::hasColumn('produits', 'quantite_a_receptionner'));
    }

    // ── La famille ───────────────────────────────────────────────────

    public function test_aucune_migration_ne_supprime_une_colonne_sans_verifier_qu_elle_existe(): void
    {
        $fautives = [];

        foreach ($this->migrations() as $chemin) {
            $up = $this->corpsDeUp(file_get_contents($chemin));

            if (!str_contains($up, 'dropColumn')) {
                continue;
            }

            if (!str_contains($up, 'hasColumn')) {
                $fautives[] = basename($chemin);
            }
        }

        $this->assertSame([], $fautives, implode("\n", [
            'Ces migrations retirent une colonne sans vérifier qu\'elle est là.',
            'Une base de production peut ne jamais l\'avoir eue — notamment si la',
            'migration qui la créait a été retouchée après avoir été appliquée.',
            'Le déploiement s\'arrête alors en 1091 (Can\'t DROP COLUMN), et tout',
            'ce qui suit reste en attente.',
        ]));
    }

    public function test_aucune_migration_ne_supprime_un_index_sans_verifier_qu_il_existe(): void
    {
        $fautives = [];

        foreach ($this->migrations() as $chemin) {
            $up = $this->corpsDeUp(file_get_contents($chemin));

            $retire = str_contains($up, 'dropIndex') || str_contains($up, 'dropUnique');

            if (!$retire) {
                continue;
            }

            // `getIndexes()` relit la base ; `dropIndexIfExists()` la laisse
            // décider ; `indexExiste()` est le nom que prennent les deux
            // migrations d'unicité. Un `catch` autour du retrait vaut aussi :
            // un index déjà absent n'est pas une anomalie à faire remonter.
            $verifie = str_contains($up, 'getIndexes')
                || str_contains($up, 'indexExiste')
                || str_contains($up, 'indexExists')
                || str_contains($up, 'dropIndexIfExists')
                || str_contains($up, 'catch')
                || str_contains($up, 'hasColumn');

            if (!$verifie) {
                $fautives[] = basename($chemin);
            }
        }

        $this->assertSame([], $fautives,
            'Un index retiré sans vérification arrête le déploiement de la même '
            . 'façon qu\'une colonne : la base de production n\'a pas forcément '
            . 'l\'index que la migration croit y trouver.');
    }

    /**
     * Le garde-fou qui aurait évité les deux incidents.
     *
     * Ajouter une colonne à une migration de création déjà appliquée est sans
     * effet sur les bases qui l'ont passée. Le remède est une migration de
     * rattrapage datée d'aujourd'hui — jamais la retouche de l'ancienne.
     */
    public function test_les_colonnes_retouchees_apres_coup_ont_leur_migration_de_rattrapage(): void
    {
        // `entreprises` : la retouche du 20 juillet a bien reçu son rattrapage.
        $this->assertFileExists(app_path(
            'Modules/Admin/Migrations/2026_07_20_133019_add_missing_columns_to_entreprises.php'
        ));

        $rattrapage = file_get_contents(app_path(
            'Modules/Admin/Migrations/2026_07_20_133019_add_missing_columns_to_entreprises.php'
        ));

        foreach (['secteur_activite', 'modules_actifs'] as $colonne) {
            $this->assertStringContainsString($colonne, $rattrapage);
            $this->assertTrue(Schema::hasColumn('entreprises', $colonne));
        }

        // `produits` : la retouche du même jour n'en a jamais reçu. Ce n'est pas
        // un manque — ces deux colonnes doivent disparaître, pas revenir. C'est
        // le retrait qui devait tenir compte de leur absence.
        $this->assertFalse(Schema::hasColumn('produits', 'quantite_commandee'));
    }
}
