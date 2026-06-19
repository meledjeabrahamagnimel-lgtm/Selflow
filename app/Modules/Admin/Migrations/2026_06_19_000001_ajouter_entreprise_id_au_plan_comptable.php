<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plan_comptable', function (Blueprint $table) {
            // Drop unique constraint on global 'numero'
            $table->dropUnique('plan_comptable_numero_unique');
            
            // Add nullable 'entreprise_id'
            $table->unsignedBigInteger('entreprise_id')->nullable()->after('id')->index();
            
            // Add foreign key constraint
            $table->foreign('entreprise_id', 'fk_plan_comptable_entreprises')
                ->references('id')
                ->on('entreprises')
                ->onDelete('cascade');
            
            // Add composite unique constraint
            $table->unique(['entreprise_id', 'numero'], 'uniq_plan_comptable_entreprise_numero');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_comptable', function (Blueprint $table) {
            $table->dropUnique('uniq_plan_comptable_entreprise_numero');
            $table->dropForeign('fk_plan_comptable_entreprises');
            $table->dropColumn('entreprise_id');
            $table->unique('numero', 'plan_comptable_numero_unique');
        });
    }
};
