<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Exécuter la migration.
     */
    public function up(): void
    {
        Schema::create('utilisateurs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id')->nullable()->index();
            $table->unsignedBigInteger('point_de_vente_id')->nullable()->index();
            $table->string('nom');
            $table->string('email')->unique()->index();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role')->index(); // 'superadmin', 'admin', 'caissier'
            $table->string('statut')->default('actif')->index(); // 'actif', 'inactif'
            $table->rememberToken();
            $table->timestamps();

            // Foreign keys with explicit names
            $table->foreign('entreprise_id', 'fk_utilisateurs_entreprises')
                ->references('id')
                ->on('entreprises')
                ->onDelete('cascade');

            $table->foreign('point_de_vente_id', 'fk_utilisateurs_points_de_vente')
                ->references('id')
                ->on('points_de_vente')
                ->onDelete('set null');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index(); // Pour conserver la compatibilité native de Laravel
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('utilisateurs');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
