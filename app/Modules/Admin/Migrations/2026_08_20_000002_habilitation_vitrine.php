<?php

use App\Modules\Authentification\Regles\Habilitations;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Donner `gestion_vitrine` aux superadministrateurs déjà en place.
 *
 * Le contrôle des habilitations **ferme par défaut** : une habilitation
 * nouvelle n'ouvre rien tant qu'elle n'est portée par aucun compte. Sans cette
 * migration, l'écran de la vitrine serait refusé à tout le monde le jour de sa
 * mise en ligne — y compris à celui qui vient de l'installer, et qui n'aurait
 * aucun moyen de se l'accorder puisque c'est cet écran-là qui distribue les
 * droits.
 *
 * Seuls les comptes qui portent déjà les habilitations de plateforme sont
 * servis : un superadministrateur volontairement restreint garde sa
 * restriction.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('utilisateurs') || !Schema::hasColumn('utilisateurs', 'habilitations')) {
            return;
        }

        $comptes = DB::table('utilisateurs')
            ->where('role', 'superadmin')
            ->get(['id', 'habilitations']);

        foreach ($comptes as $compte) {
            $actuelles = json_decode((string) $compte->habilitations, true);

            if (!is_array($actuelles) || $actuelles === []) {
                // Compte sans habilitation explicite : la migration des
                // habilitations de plateforme l'a déjà laissé de côté, ou
                // c'est un compte volontairement restreint. On n'y touche pas.
                continue;
            }

            if (in_array('gestion_vitrine', $actuelles, true)) {
                continue;
            }

            // Seuls les comptes qui administrent déjà la plateforme.
            if (!in_array('tableau_de_bord_superadmin', $actuelles, true)) {
                continue;
            }

            $actuelles[] = 'gestion_vitrine';

            DB::table('utilisateurs')->where('id', $compte->id)->update([
                'habilitations' => json_encode(array_values(array_unique($actuelles))),
            ]);
        }
    }

    /**
     * Retirer l'habilitation, sans toucher au reste.
     */
    public function down(): void
    {
        if (!Schema::hasTable('utilisateurs') || !Schema::hasColumn('utilisateurs', 'habilitations')) {
            return;
        }

        foreach (DB::table('utilisateurs')->where('role', 'superadmin')->get(['id', 'habilitations']) as $compte) {
            $actuelles = json_decode((string) $compte->habilitations, true);

            if (!is_array($actuelles)) {
                continue;
            }

            DB::table('utilisateurs')->where('id', $compte->id)->update([
                'habilitations' => json_encode(array_values(array_diff($actuelles, ['gestion_vitrine']))),
            ]);
        }
    }
};
