<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajouter les champs devise / taux de change et opérateur mobile money
     * sur les tables ventes et achats.
     *
     * devise          : Code ISO de la devise (ex. XOF, EUR, USD…)  — vide ou null = FCFA (XOF)
     * taux_change     : Taux de change par rapport au FCFA  (ex. 655 pour l'Euro)
     * mobile_money_operateur : Opérateur Mobile Money interne (MTN, MOOV, ORANGE, WAVE)
     *                          transmis uniquement au logiciel, pas à la DGI.
     */
    public function up(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            if (!Schema::hasColumn('ventes', 'devise')) {
                $table->string('devise', 10)->nullable()->after('mode_paiement');
            }
            if (!Schema::hasColumn('ventes', 'taux_change')) {
                $table->decimal('taux_change', 12, 4)->nullable()->after('devise');
            }
            if (!Schema::hasColumn('ventes', 'mobile_money_operateur')) {
                $table->string('mobile_money_operateur', 20)->nullable()->after('taux_change');
            }
        });

        Schema::table('achats', function (Blueprint $table) {
            if (!Schema::hasColumn('achats', 'devise')) {
                $table->string('devise', 10)->nullable()->after('mode_paiement');
            }
            if (!Schema::hasColumn('achats', 'taux_change')) {
                $table->decimal('taux_change', 12, 4)->nullable()->after('devise');
            }
            if (!Schema::hasColumn('achats', 'mobile_money_operateur')) {
                $table->string('mobile_money_operateur', 20)->nullable()->after('taux_change');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn(['devise', 'taux_change', 'mobile_money_operateur']);
        });

        Schema::table('achats', function (Blueprint $table) {
            $table->dropColumn(['devise', 'taux_change', 'mobile_money_operateur']);
        });
    }
};
