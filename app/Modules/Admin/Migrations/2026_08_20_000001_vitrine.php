<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La vitrine publique — le contenant, pas le contenu.
 *
 * Deux tables seulement, et c'est délibéré : une page de présentation change
 * plus souvent que le code qui l'affiche. Écrire les textes en dur aurait
 * voulu dire un déploiement à chaque virgule, et une relecture de code pour
 * une faute de frappe.
 *
 * - `vitrine_sections` : les blocs de la page, dans l'ordre où ils se suivent.
 *   Chaque section porte une **disposition** (`gabarit`) qui dit comment ses
 *   cartes se présentent — en colonnes, en liste, en bandeau.
 * - `vitrine_cartes` : ce qu'une section contient. Un titre, un texte, une
 *   icône, un lien, une image.
 *
 * Rien n'est semé : **le contenu vient du propriétaire du projet**, saisi
 * depuis l'écran superadmin. Une vitrine vide affiche une page d'attente, pas
 * du faux texte qui finirait en production.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vitrine_sections', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();

            // La clé sert aux ancres (`/#tarifs`) et à retrouver une section
            // depuis le code sans dépendre de son numéro de ligne.
            $table->string('cle', 64)->unique();
            $table->string('titre');
            $table->string('sous_titre')->nullable();
            $table->text('texte')->nullable();

            // Comment les cartes de cette section se présentent.
            $table->string('gabarit', 32)->default('colonnes');

            $table->unsignedInteger('ordre')->default(0);
            $table->boolean('publiee')->default(false);
            $table->timestamps();

            $table->index(['publiee', 'ordre']);
        });

        Schema::create('vitrine_cartes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->nullable()->unique();

            $table->foreignId('section_id')
                ->constrained('vitrine_sections')
                ->cascadeOnDelete();

            $table->string('titre');
            $table->text('texte')->nullable();

            // Une icône Font Awesome, telle qu'elle s'écrit dans la page.
            $table->string('icone', 64)->nullable();
            $table->string('image_path')->nullable();

            // Un appel à l'action : le libellé et l'adresse.
            $table->string('lien_libelle')->nullable();
            $table->string('lien_url')->nullable();

            // Ce que la carte met en avant — un prix, un chiffre, une mention.
            $table->string('valeur')->nullable();
            $table->string('mention')->nullable();

            $table->unsignedInteger('ordre')->default(0);
            $table->boolean('publiee')->default(true);
            $table->timestamps();

            $table->index(['section_id', 'ordre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vitrine_cartes');
        Schema::dropIfExists('vitrine_sections');
    }
};
