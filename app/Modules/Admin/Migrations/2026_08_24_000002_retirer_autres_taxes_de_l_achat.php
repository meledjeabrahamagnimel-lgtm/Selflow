<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `achats.montant_autres_taxes` est retirée — décision du propriétaire du
 * projet, 24/08/2026.
 *
 * La colonne avait été posée sur `ventes` et sur `achats` d'un même geste,
 * par symétrie. Mais les deux ne portent pas la même chose :
 *
 *   - à la **vente**, une taxe additionnelle est collectée pour le compte de
 *     l'État. C'est une **dette**, créditée au 447000, et elle est reversée ;
 *   - à l'**achat**, une taxe supportée est une **charge** de l'entreprise, et
 *     le compte à retenir dépend de sa nature : droit d'enregistrement, taxe
 *     non récupérable, redevance. Il n'y a pas de compte unique à deviner.
 *
 * Aucun écran ne l'alimentait, `ventilationAchat()` l'ignorait, `montant_ttc`
 * ne la comportait pas. Une colonne qui annonce un montant que rien ne calcule
 * finit par être crue : la retirer est plus honnête que la laisser dormir.
 *
 * Le jour où le besoin se présentera, il faudra d'abord savoir quel compte de
 * charge retenir — et le poser dans la configuration, comme les autres.
 *
 * `ventes.montant_autres_taxes` **n'est pas touchée**.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('achats', 'montant_autres_taxes')) {
            Schema::table('achats', function (Blueprint $table) {
                $table->dropColumn('montant_autres_taxes');
            });
        }
    }

    /**
     * La colonne se repose vide, ce qui est exactement l'état d'où elle vient :
     * aucun achat n'en a jamais porté de valeur.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('achats', 'montant_autres_taxes')) {
            Schema::table('achats', function (Blueprint $table) {
                $table->decimal('montant_autres_taxes', 15, 2)->default(0)->after('montant_ttc');
            });
        }
    }
};
