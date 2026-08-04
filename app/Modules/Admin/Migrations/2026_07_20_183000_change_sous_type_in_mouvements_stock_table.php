<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Passer la colonne enum en varchar pour accepter tout sous-type.
        // `MODIFY COLUMN` est propre a MySQL ; sur les autres moteurs, le
        // Schema Builder produit l'equivalent.
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE mouvements_stock MODIFY COLUMN sous_type VARCHAR(255) NULL");

            return;
        }

        Schema::table('mouvements_stock', function (Blueprint $table) {
            $table->string('sous_type', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE mouvements_stock MODIFY COLUMN sous_type ENUM('Reception', 'Livraison', 'Transfert', 'Rebut', 'Ajustement', 'Production') NULL");
        }
    }
};
