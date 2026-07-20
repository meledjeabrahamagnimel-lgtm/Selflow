<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Alter enum column to varchar to allow all kinds of sub_types without DB restriction
        DB::statement("ALTER TABLE mouvements_stock MODIFY COLUMN sous_type VARCHAR(255) NULL");
    }

    public function down(): void
    {
        // Revert to enum
        DB::statement("ALTER TABLE mouvements_stock MODIFY COLUMN sous_type ENUM('Reception', 'Livraison', 'Transfert', 'Rebut', 'Ajustement', 'Production') NULL");
    }
};
