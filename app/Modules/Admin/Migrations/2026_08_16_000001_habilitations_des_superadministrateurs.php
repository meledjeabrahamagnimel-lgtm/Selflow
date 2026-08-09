<?php

use App\Modules\Authentification\Regles\Habilitations;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Donner aux superadministrateurs en place les habilitations qu'ils exerçaient
 * sans les porter.
 *
 * Le middleware d'habilitation laissait passer une adresse écrite en dur :
 *
 *     if ($utilisateur->email === 'superadmin@gmail.com') return $next($request);
 *
 * et, pour les autres superadministrateurs, **toute route absente du
 * dictionnaire passait sans contrôle**. Les comptes créés par les semeurs
 * n'avaient donc aucune habilitation enregistrée : ils fonctionnaient parce que
 * rien ne les vérifiait vraiment.
 *
 * Le contrôle est maintenant fermé par défaut. Sans cette migration, les
 * superadministrateurs déjà en place se retrouveraient enfermés dehors au
 * premier déploiement — et personne ne pourrait leur rendre leurs droits, ces
 * écrans étant précisément ceux qui les distribuent.
 *
 * **Ce n'est pas une porte dérobée**, et la différence compte : la migration
 * inscrit le privilège dans la fiche, où il se lit, s'audite et se retire. Elle
 * ne touche que les comptes qui existent au moment où elle passe, et seulement
 * ceux dont la colonne est vide — un superadministrateur volontairement
 * restreint garde sa restriction.
 */
return new class extends Migration
{
    public function up(): void
    {
        $comptes = DB::table('utilisateurs')
            ->where('role', 'superadmin')
            ->get(['id', 'habilitations']);

        foreach ($comptes as $compte) {
            $actuelles = json_decode($compte->habilitations ?? '[]', true);

            // Un compte déjà restreint garde sa restriction : la migration
            // répare un oubli, elle ne redistribue pas les droits.
            if (is_array($actuelles) && $actuelles !== []) {
                continue;
            }

            DB::table('utilisateurs')
                ->where('id', $compte->id)
                ->update(['habilitations' => json_encode(Habilitations::PLATEFORME)]);
        }
    }

    public function down(): void
    {
        // Rien à défaire : retirer ces habilitations enfermerait dehors les
        // comptes qui les exercent, et la colonne existait déjà.
    }
};
