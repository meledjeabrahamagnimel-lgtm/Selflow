<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le point de vente actif cesse de mourir avec la session.
 *
 * Il ne vivait que dans `session('point_de_vente_actif_id')`. Or
 * `ConnexionControleur::deconnecter()` appelle `session()->invalidate()` — ce
 * qui est juste, une session doit mourir avec sa déconnexion — et le choix du
 * point de vente partait avec.
 *
 * Conséquence pour un responsable qui tient trois magasins : **à chaque
 * connexion, il repartait sur le premier venu**, sans que rien ne le dise. Il
 * pouvait encaisser une vente au nom d'un magasin où il n'était pas — et le
 * point de vente est ce que la plateforme de la DGI reçoit, sous le nom exact
 * déclaré sur l'espace FNE. Une pièce certifiée sous le mauvais magasin ne se
 * corrige pas : elle s'annule par un avoir.
 *
 * ── Pourquoi une colonne à part, et non `point_de_vente_id` ──
 *
 * `utilisateurs.point_de_vente_id` existe déjà, mais il ne dit pas la même
 * chose : pour un caissier, c'est son **affectation**, décidée par son
 * responsable, et non une préférence. Y écrire le dernier choix ferait qu'un
 * caissier qui bascule d'écran changerait son affectation. Deux idées
 * distinctes, deux colonnes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('utilisateurs', function (Blueprint $table) {
            $table->unsignedBigInteger('point_de_vente_actif_id')->nullable()->after('point_de_vente_id');
        });
    }

    public function down(): void
    {
        Schema::table('utilisateurs', function (Blueprint $table) {
            $table->dropColumn('point_de_vente_actif_id');
        });
    }
};
