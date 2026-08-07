<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Référentiel de préparamétrage par activité.
 *
 * Ces tables portent le catalogue livré avec l'application, commun à toutes les
 * entreprises : elles ne contiennent aucune donnée client. Une entreprise
 * souscrit à un ou plusieurs profils, et l'application recopie chez elle les
 * familles, articles et comptes correspondants — qu'elle peut ensuite renommer,
 * compléter ou archiver sans que le référentiel en soit affecté.
 *
 * Cinq niveaux, du plus large au plus fin :
 *
 *   Catégorie  →  Profil  →  Famille  →  Article
 *                    ↑          ↑          ↑
 *                    └── Type d'article ───┘   (transversal : il décide des comptes)
 *
 * La catégorie est l'ancien « secteur d'activité » : un tiroir d'affichage. Le
 * profil est le niveau neuf, celui que l'utilisateur choisit et qui décide des
 * modules à ouvrir. La famille porte les comptes ; l'article en hérite.
 *
 * Les numéros de compte sont écrits sur six chiffres, comme le reste de
 * l'application : la racine SYSCOHADA du classeur est complétée par des zéros
 * (`701` → `701000`, `60311` → `603110`).
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Catégories : le regroupement d'affichage ──────────────────────
        Schema::create('referentiel_categories', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->unsignedSmallInteger('ordre')->default(0)->index();
            $table->timestamps();
        });

        // ── Types d'article : le pivot qui décide des comptes ─────────────
        Schema::create('referentiel_types_articles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();          // MARCHANDISE, SERVICE…
            $table->string('libelle');
            $table->string('compte_vente', 6)->nullable();
            $table->string('compte_achat', 6)->nullable();
            $table->string('compte_stock', 6)->nullable();
            $table->string('compte_variation', 6)->nullable();
            // Un type sans compte de stock ne se compte pas : un service n'a ni
            // quantité disponible, ni seuil, ni rupture. C'est cette colonne qui
            // évite de créer une fiche de stock pour une prestation.
            $table->boolean('stockable')->default(false)->index();
            $table->timestamps();
        });

        // ── Plan comptable commun à tous les profils ──────────────────────
        Schema::create('referentiel_comptes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 6)->unique();
            $table->string('intitule');
            $table->timestamps();
        });

        // ── Profils : le métier réel ──────────────────────────────────────
        Schema::create('referentiel_profils', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();           // boutique_quartier…
            $table->string('nom');
            $table->foreignId('categorie_id')->constrained('referentiel_categories')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->boolean('module_stock')->default(false);
            $table->boolean('module_production')->default(false);
            $table->boolean('module_chantiers')->default(false);
            $table->boolean('module_cycles')->default(false);
            $table->text('note_gestion')->nullable();
            $table->timestamps();
        });

        // ── Familles : le rayon, qui porte les comptes ────────────────────
        Schema::create('referentiel_familles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profil_id')->constrained('referentiel_profils')->cascadeOnDelete();
            $table->string('code', 32);                     // VIV, BOI, HYG…
            $table->string('nom');
            $table->foreignId('type_article_id')->constrained('referentiel_types_articles')->cascadeOnDelete();
            $table->string('compte_vente', 6)->nullable();
            $table->string('compte_achat', 6)->nullable();
            $table->string('compte_stock', 6)->nullable();
            $table->string('compte_variation', 6)->nullable();
            $table->timestamps();

            // Un même code de famille se retrouve d'un profil à l'autre : c'est
            // le couple qui identifie.
            $table->unique(['profil_id', 'code'], 'uniq_famille_par_profil');
        });

        // ── Articles types : ce que l'utilisateur retrouvera pré-créé ─────
        Schema::create('referentiel_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('profil_id')->constrained('referentiel_profils')->cascadeOnDelete();
            $table->foreignId('famille_id')->constrained('referentiel_familles')->cascadeOnDelete();
            $table->string('code', 64)->unique();           // BOUQ-VIV-001
            $table->string('designation');
            $table->string('unite', 32)->nullable();
            $table->foreignId('type_article_id')->constrained('referentiel_types_articles')->cascadeOnDelete();
            $table->string('compte_vente', 6)->nullable();
            $table->string('compte_achat', 6)->nullable();
            $table->string('compte_stock', 6)->nullable();
            $table->timestamps();

            // Le classeur laisse volontairement les prix et le stock initial
            // vides : ils varient selon la zone et la période, et c'est à
            // l'utilisateur de les saisir à la souscription.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referentiel_articles');
        Schema::dropIfExists('referentiel_familles');
        Schema::dropIfExists('referentiel_profils');
        Schema::dropIfExists('referentiel_comptes');
        Schema::dropIfExists('referentiel_types_articles');
        Schema::dropIfExists('referentiel_categories');
    }
};
