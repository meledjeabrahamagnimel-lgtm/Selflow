<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Souscription d'une entreprise à un ou plusieurs profils d'activité.
 *
 * Le secteur d'activité était jusqu'ici une étiquette : choisir « Commercial »
 * ne chargeait ni familles, ni articles, ni comptes. Cette table relie
 * l'entreprise aux profils du référentiel, et garde trace de ce qu'elle a
 * effectivement reçu — une activité mixte cumule plusieurs profils.
 *
 * Deux colonnes séparent au passage ce qui était confondu dans un seul tableau
 * JSON :
 *
 * - `modules_autorises` : ce que l'entreprise a le **droit** d'activer. C'est
 *   le superadmin qui en décide, et il ouvre tout par défaut.
 * - `modules_actifs` : ce que l'utilisateur **veut** voir, dans cette limite.
 *
 * Sans cette distinction, personne ne savait qui avait désactivé quoi : un
 * module absent pouvait aussi bien venir d'un abonnement restreint que d'une
 * préférence de l'utilisateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entreprise_profils', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entreprise_id')->constrained('entreprises')->cascadeOnDelete();
            $table->foreignId('profil_id')->constrained('referentiel_profils')->cascadeOnDelete();

            // Ce que la souscription a réellement créé, pour pouvoir le dire à
            // l'utilisateur et pour ne pas le recréer si le profil est repris.
            $table->unsignedSmallInteger('familles_creees')->default(0);
            $table->unsignedSmallInteger('articles_crees')->default(0);
            $table->timestamp('souscrit_le')->nullable();
            $table->timestamps();

            $table->unique(['entreprise_id', 'profil_id'], 'uniq_souscription_profil');
        });

        Schema::table('entreprises', function (Blueprint $table) {
            $table->json('modules_autorises')->nullable()->after('modules_actifs');

            // Le parcours de souscription se déplie étape par étape et doit
            // pouvoir être quitté puis repris : on retient où l'utilisateur
            // s'est arrêté.
            $table->unsignedTinyInteger('souscription_etape')->default(0)->after('modules_autorises');
            $table->timestamp('souscription_terminee_le')->nullable()->after('souscription_etape');

            // Ce que l'utilisateur a saisi quand aucun profil ne lui convenait.
            // Le référentiel ne couvre pas tous les métiers ; plutôt que de le
            // forcer dans une case, on note ce qu'il dit — et cela alimentera
            // les versions suivantes du classeur.
            $table->string('activite_autre')->nullable()->after('souscription_terminee_le');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entreprise_profils');

        Schema::table('entreprises', function (Blueprint $table) {
            $table->dropColumn([
                'modules_autorises',
                'souscription_etape',
                'souscription_terminee_le',
                'activite_autre',
            ]);
        });
    }
};
