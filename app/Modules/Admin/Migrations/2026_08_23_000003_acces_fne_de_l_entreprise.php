<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'accès à l'espace FNE d'une entreprise qui en possède déjà un.
 *
 * Une entreprise déjà inscrite auprès de la DGI n'a pas à ressaisir sa
 * situation fiscale : tout est dans son espace FNE. Elle donne son NCC et le
 * mot de passe de cet espace, **une fois**, à la création de son compte ; le
 * superadministrateur s'en sert pour relever le paramétrage et poser la clé
 * d'API. L'entreprise qui n'a pas de compte, elle, renseigne ses informations
 * fiscales pour qu'on lui en ouvre un.
 *
 * ## Pourquoi ce mot de passe est traité comme la clé d'API
 *
 * Ce n'est pas un réglage, c'est **un accès à un service de l'État**. Il
 * rejoint donc `fne_credentials`, la table dont tout le contenu sensible est
 * chiffré au repos par `APP_KEY` — jamais commité — et que seul le
 * superadministrateur consulte.
 *
 * Trois précautions, portées par le code qui l'entoure :
 *
 * - il est **chiffré** comme `cle_test` et `cle_reelle` (cast `encrypted`) ;
 * - il n'est **jamais rendu** à l'écran, ni à l'entreprise ni au
 *   superadministrateur : on ne montre que la date à laquelle il a été fourni ;
 * - il est **effaçable** — une fois le paramétrage relevé, il ne sert plus, et
 *   ce qui ne sert plus ne se garde pas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fne_credentials', function (Blueprint $table) {
            // Le mot de passe de l'espace FNE de l'entreprise. Chiffré au
            // repos, comme les clés : c'est un accès, pas une préférence.
            $table->text('acces_mot_de_passe')->nullable()
                ->comment("Mot de passe de l'espace FNE de l'entreprise, chiffré. Sert au paramétrage, s'efface ensuite.");

            $table->timestamp('acces_fourni_at')->nullable()
                ->comment('Quand l\'entreprise a fourni cet accès. La seule chose qu\'on affiche.');
        });
    }

    public function down(): void
    {
        Schema::table('fne_credentials', function (Blueprint $table) {
            $table->dropColumn(['acces_mot_de_passe', 'acces_fourni_at']);
        });
    }
};
