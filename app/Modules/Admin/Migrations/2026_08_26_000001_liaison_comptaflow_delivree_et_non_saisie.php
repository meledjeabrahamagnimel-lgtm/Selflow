<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La clé de liaison Comptaflow cesse d'être une donnée saisie.
 *
 * Elle l'était : le formulaire des paramètres portait un champ libre
 * « Clé de synchronisation COMPTAFLOW », avec la consigne d'aller la chercher
 * sur Comptaflow et de la coller ici. `EntrepriseControleur::enregistrer()`
 * l'acceptait comme un champ ordinaire et ouvrait la liaison dès qu'elle
 * changeait.
 *
 * Une entreprise qui obtenait la clé d'une autre — comptable partagé, capture
 * d'écran, ancien salarié — la collait dans ses propres paramètres : la
 * liaison s'ouvrait, **et son référentiel puis ses écritures partaient dans
 * les livres de l'autre**. Le secret partagé n'y changeait rien : il est
 * détenu par le serveur, pas par l'entreprise, et part sur tous les appels.
 * Rien ne vérifiait que la clé saisie désignait bien celui qui la saisissait.
 *
 * Désormais : l'entreprise demande, le superadministrateur valide, Comptaflow
 * génère la clé et la renvoie, Selflow la range. Personne ne la tape.
 *
 * Les colonnes ajoutées portent cette procédure. Et la clé elle-même passe au
 * chiffré : une lecture de la table `entreprises` — sauvegarde égarée, accès
 * en lecture à la base — livrait jusqu'ici toutes les clés en clair, donc
 * l'accès en écriture aux livres de chaque entreprise.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entreprises', function (Blueprint $table) {
            // La demande, avant la liaison. `null` = jamais demandée.
            $table->string('comptaflow_demande_statut', 20)->nullable()->after('comptaflow_company_id');
            $table->timestamp('comptaflow_demande_le')->nullable()->after('comptaflow_demande_statut');
            $table->unsignedBigInteger('comptaflow_demande_par')->nullable()->after('comptaflow_demande_le');
            $table->string('comptaflow_refus_motif', 255)->nullable()->after('comptaflow_demande_par');

            // La liaison, une fois ouverte.
            $table->timestamp('comptaflow_liee_le')->nullable()->after('comptaflow_refus_motif');
            $table->timestamp('comptaflow_revoquee_le')->nullable()->after('comptaflow_liee_le');

            // Les quatre derniers caractères de la clé, en clair, et rien de
            // plus. De quoi reconnaître une clé sans la donner : le
            // superadministrateur doit pouvoir distinguer deux liaisons sans
            // qu'aucun écran n'affiche jamais de clé entière.
            $table->string('comptaflow_cle_indice', 8)->nullable()->after('comptaflow_revoquee_le');
        });

        // Le champ est passé en `encrypted` sur le modèle : les valeurs déjà
        // en base sont en clair et deviendraient indéchiffrables. On les
        // chiffre ici, une fois.
        foreach (DB::table('entreprises')->whereNotNull('comptaflow_sync_key')->get() as $ligne) {
            $claire = (string) $ligne->comptaflow_sync_key;

            if ($claire === '' || self::dejaChiffree($claire)) {
                continue;
            }

            DB::table('entreprises')->where('id', $ligne->id)->update([
                'comptaflow_sync_key'   => Crypt::encryptString($claire),
                'comptaflow_cle_indice' => substr($claire, -4),
                // La liaison existait déjà : on ne la rouvre pas, on date ce
                // qu'on sait dater.
                'comptaflow_liee_le'    => $ligne->comptaflow_last_sync_at ?? now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('entreprises')->whereNotNull('comptaflow_sync_key')->get() as $ligne) {
            $stockee = (string) $ligne->comptaflow_sync_key;

            if (!self::dejaChiffree($stockee)) {
                continue;
            }

            DB::table('entreprises')->where('id', $ligne->id)->update([
                'comptaflow_sync_key' => Crypt::decryptString($stockee),
            ]);
        }

        Schema::table('entreprises', function (Blueprint $table) {
            $table->dropColumn([
                'comptaflow_demande_statut',
                'comptaflow_demande_le',
                'comptaflow_demande_par',
                'comptaflow_refus_motif',
                'comptaflow_liee_le',
                'comptaflow_revoquee_le',
                'comptaflow_cle_indice',
            ]);
        });
    }

    /**
     * Rejouer la migration sur une base déjà traitée chiffrerait le chiffré,
     * et la clé serait perdue. Une valeur chiffrée par Laravel se déchiffre ;
     * une clé en clair ne se déchiffre pas.
     */
    private static function dejaChiffree(string $valeur): bool
    {
        try {
            Crypt::decryptString($valeur);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
};
