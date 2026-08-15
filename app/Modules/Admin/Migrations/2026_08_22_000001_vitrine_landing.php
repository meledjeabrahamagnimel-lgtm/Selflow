<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ce qu'une vraie page d'accueil demande en plus.
 *
 * La première version savait afficher des cartes en colonnes. Une page de
 * présentation en demande davantage : une illustration ou une vidéo par
 * section, une alternance de fonds pour que l'œil sépare les blocs au
 * défilement, et une carte qui porte un rôle — le nom d'un produit, la
 * fonction d'une personne.
 *
 * Rien n'est semé ici : **le contenu vient du propriétaire du projet**. Le
 * semeur `VitrineSeeder` pose la structure et les textes qu'il a dictés ;
 * photos, vidéos et noms des autres membres se saisissent depuis l'écran
 * superadmin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vitrine_sections', function (Blueprint $table) {
            // Une image ou une vidéo qui illustre la section. Deux chemins
            // possibles : un fichier déposé, ou une adresse — une vidéo pèse
            // trop pour le disque d'une application de gestion.
            $table->string('media_type', 16)->nullable()->after('gabarit');
            $table->string('media_path')->nullable()->after('media_type');
            $table->string('media_url')->nullable()->after('media_path');
            $table->string('media_legende')->nullable()->after('media_url');

            // Le fond : c'est ce qui découpe la page à l'œil quand on la
            // parcourt. Sans alternance, dix sections se lisent comme un seul
            // bloc.
            $table->string('fond', 16)->default('clair')->after('media_legende');

            // Le bouton principal de la section — « Lire la documentation »,
            // « Créer un compte ». Distinct des cartes, qui sont du contenu.
            $table->string('action_libelle')->nullable()->after('fond');
            $table->string('action_url')->nullable()->after('action_libelle');
        });

        Schema::table('vitrine_cartes', function (Blueprint $table) {
            // Ce que la carte est, en un mot : « Comptabilité », « Développeur ».
            // Le gabarit « produits » l'affiche en étiquette, « equipe » en
            // fonction sous le nom.
            $table->string('role')->nullable()->after('texte');

            // Une seconde ligne d'action : un produit renvoie souvent vers sa
            // page et vers sa documentation.
            $table->string('lien_secondaire_libelle')->nullable()->after('lien_url');
            $table->string('lien_secondaire_url')->nullable()->after('lien_secondaire_libelle');
        });
    }

    public function down(): void
    {
        Schema::table('vitrine_sections', function (Blueprint $table) {
            $table->dropColumn([
                'media_type', 'media_path', 'media_url', 'media_legende',
                'fond', 'action_libelle', 'action_url',
            ]);
        });

        Schema::table('vitrine_cartes', function (Blueprint $table) {
            $table->dropColumn(['role', 'lien_secondaire_libelle', 'lien_secondaire_url']);
        });
    }
};
