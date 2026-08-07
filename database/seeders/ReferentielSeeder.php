<?php

namespace Database\Seeders;

use App\Modules\Admin\Modeles\Referentiel\Article;
use App\Modules\Admin\Modeles\Referentiel\Categorie;
use App\Modules\Admin\Modeles\Referentiel\Compte;
use App\Modules\Admin\Modeles\Referentiel\Famille;
use App\Modules\Admin\Modeles\Referentiel\Profil;
use App\Modules\Admin\Modeles\Referentiel\TypeArticle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Chargement du référentiel de préparamétrage.
 *
 * La source est le classeur « sellflow_parametrage_activites », converti en JSON
 * dans `database/data/referentiel/`. Le JSON est versionné, pas le classeur :
 * un fichier de tableur ne se relit pas dans une revue de code, et son contenu
 * doit pouvoir se comparer d'une version à l'autre.
 *
 * Le chargement est idempotent : le relancer après une nouvelle version du
 * classeur met à jour ce qui a changé sans dupliquer ce qui existe. Il peut donc
 * tourner à chaque déploiement.
 *
 *     php artisan db:seed --class=ReferentielSeeder
 */
class ReferentielSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $categories = $this->chargerCategories();
            $types      = $this->chargerTypesArticles();
            $this->chargerPlanOhada();
            $this->chargerComptes();
            $profils    = $this->chargerProfils($categories);
            $familles   = $this->chargerFamilles($profils, $types);
            $this->chargerArticles($profils, $familles, $types);
        });

        $this->command?->info(sprintf(
            'Référentiel chargé : %d catégories, %d profils, %d familles, %d articles, %d types, %d comptes.',
            Categorie::count(), Profil::count(), Famille::count(),
            Article::count(), TypeArticle::count(), Compte::count()
        ));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lire(string $nom): array
    {
        $chemin = database_path("data/referentiel/{$nom}.json");

        if (!is_file($chemin)) {
            throw new \RuntimeException("Fichier de référentiel introuvable : {$chemin}");
        }

        return json_decode(file_get_contents($chemin), true, 512, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, int> nom → id */
    private function chargerCategories(): array
    {
        $ids = [];
        foreach ($this->lire('categories') as $ligne) {
            $categorie = Categorie::updateOrCreate(
                ['nom' => $ligne['nom']],
                ['ordre' => $ligne['ordre']]
            );
            $ids[$ligne['nom']] = $categorie->id;
        }

        return $ids;
    }

    /** @return array<string, int> code → id */
    private function chargerTypesArticles(): array
    {
        $ids = [];
        foreach ($this->lire('types_articles') as $ligne) {
            $type = TypeArticle::updateOrCreate(
                ['code' => $ligne['code']],
                [
                    'libelle'          => $ligne['libelle'],
                    'compte_vente'     => $ligne['compte_vente'],
                    'compte_achat'     => $ligne['compte_achat'],
                    'compte_stock'     => $ligne['compte_stock'],
                    'compte_variation' => $ligne['compte_variation'],
                    'stockable'        => $ligne['stockable'],
                ]
            );
            $ids[$ligne['code']] = $type->id;
        }

        return $ids;
    }

    /**
     * Les 1 256 comptes de l'acte uniforme OHADA, comme dictionnaire.
     *
     * Ils ne sont pas donnés aux entreprises : ils servent à nommer. Le
     * référentiel impute sur des subdivisions — `701100`, `603110` — dont
     * l'intitulé se déduit de leur racine.
     */
    private function chargerPlanOhada(): void
    {
        $lignes = array_map(fn ($l) => [
            'numero'     => $l['numero'],
            'racine'     => $l['racine'],
            'intitule'   => $l['intitule'],
            'classe'     => (int) $l['classe'],
            'commun'     => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $this->lire('plan_ohada'));

        // 1 256 lignes une par une feraient 1 256 requêtes : on insère par lots,
        // en écrasant ce qui existe déjà pour rester rejouable.
        foreach (array_chunk($lignes, 200) as $lot) {
            Compte::upsert($lot, ['numero'], ['racine', 'intitule', 'classe']);
        }
    }

    /**
     * Les comptes que toute entreprise reçoit, quel que soit son profil.
     *
     * Leur intitulé prime sur celui de l'acte uniforme : le classeur les nomme
     * en tenant compte du contexte ivoirien — « État, TVA facturée (18 % —
     * régime réel) » dit plus que « État, TVA facturée ».
     */
    private function chargerComptes(): void
    {
        foreach ($this->lire('comptes') as $ligne) {
            Compte::updateOrCreate(
                ['numero' => $ligne['numero']],
                ['intitule' => $ligne['intitule'], 'commun' => true,
                 'classe' => (int) substr($ligne['numero'], 0, 1)]
            );
        }
    }

    /**
     * @param  array<string, int>  $categories
     * @return array<string, int>  code → id
     */
    private function chargerProfils(array $categories): array
    {
        $ids = [];
        foreach ($this->lire('profils') as $ligne) {
            $profil = Profil::updateOrCreate(
                ['code' => $ligne['code']],
                [
                    'nom'               => $ligne['nom'],
                    'categorie_id'      => $categories[$ligne['categorie']],
                    'description'       => $ligne['description'] ?: null,
                    'module_stock'      => $ligne['module_stock'],
                    'module_production' => $ligne['module_production'],
                    'module_chantiers'  => $ligne['module_chantiers'],
                    'module_cycles'     => $ligne['module_cycles'],
                    'note_gestion'      => $ligne['note_gestion'],
                ]
            );
            $ids[$ligne['code']] = $profil->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $profils
     * @param  array<string, int>  $types
     * @return array<string, int>  "profil|code" → id
     */
    private function chargerFamilles(array $profils, array $types): array
    {
        $ids = [];
        foreach ($this->lire('familles') as $ligne) {
            $famille = Famille::updateOrCreate(
                ['profil_id' => $profils[$ligne['profil']], 'code' => $ligne['code']],
                [
                    'nom'              => $ligne['nom'],
                    'type_article_id'  => $types[$ligne['type_article']],
                    'compte_vente'     => $ligne['compte_vente'],
                    'compte_achat'     => $ligne['compte_achat'],
                    'compte_stock'     => $ligne['compte_stock'],
                    'compte_variation' => $ligne['compte_variation'],
                ]
            );
            $ids[$ligne['profil'] . '|' . $ligne['code']] = $famille->id;
        }

        return $ids;
    }

    /**
     * @param  array<string, int>  $profils
     * @param  array<string, int>  $familles
     * @param  array<string, int>  $types
     */
    private function chargerArticles(array $profils, array $familles, array $types): void
    {
        foreach ($this->lire('articles') as $ligne) {
            $cle = $ligne['profil'] . '|' . $ligne['famille'];

            if (!isset($familles[$cle])) {
                throw new \RuntimeException(
                    "Article {$ligne['code']} : famille « {$ligne['famille']} » "
                    . "inconnue pour le profil « {$ligne['profil']} »."
                );
            }

            Article::updateOrCreate(
                ['code' => $ligne['code']],
                [
                    'profil_id'       => $profils[$ligne['profil']],
                    'famille_id'      => $familles[$cle],
                    'designation'     => $ligne['designation'],
                    'unite'           => $ligne['unite'] ?: null,
                    'type_article_id' => $types[$ligne['type_article']],
                    'compte_vente'    => $ligne['compte_vente'],
                    'compte_achat'    => $ligne['compte_achat'],
                    'compte_stock'    => $ligne['compte_stock'],
                ]
            );
        }
    }
}
