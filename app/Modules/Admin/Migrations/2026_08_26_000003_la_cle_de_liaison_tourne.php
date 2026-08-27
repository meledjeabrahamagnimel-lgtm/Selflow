<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La clé de liaison cesse d'être éternelle.
 *
 * Une clé posée une fois et jamais changée ouvre le dossier d'une entreprise
 * aussi longtemps qu'elle existe. Un prestataire qui a vu passer une requête,
 * une sauvegarde égarée, un journal de serveur mal purgé : rien ne referme
 * derrière eux. La rotation borne la durée de vie d'une fuite.
 *
 * Deux colonnes, et deux seulement :
 *
 * - `comptaflow_cle_tournee_le` — quand la clé a été renouvelée pour la
 *   dernière fois. C'est ce que la rotation mensuelle interroge pour savoir
 *   qui est en retard, et ce que l'écran du superadministrateur affiche ;
 * - `comptaflow_rotation_echouee_le` — quand une tentative a échoué. **Une
 *   rotation qui échoue ne doit pas se retenter en boucle** : Comptaflow peut
 *   être en panne pour la journée, et marteler son API ne la réveillera pas.
 *   La date permet d'espacer, et de voir à l'écran ce qui traîne.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            $table->timestamp('comptaflow_cle_tournee_le')->nullable()->after('comptaflow_cle_indice');
            $table->timestamp('comptaflow_rotation_echouee_le')->nullable()->after('comptaflow_cle_tournee_le');
        });
    }

    public function down(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            $table->dropColumn(['comptaflow_cle_tournee_le', 'comptaflow_rotation_echouee_le']);
        });
    }
};
