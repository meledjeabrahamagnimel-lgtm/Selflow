<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration de conformité comptable et fiscale — Normalisation BAPA (Écart N°3)
 *
 * Ajoute à la table `achats` :
 *  - `normalise` : indicateur de transmission à la DGI
 *  - `numero_fne` : numéro fiscal unique attribué par le FNE/DGI pour le BAPA
 *  - `signature_dgi` : clé cryptographique de signature DGI
 *  - `qr_code_data` : données brutes/URL pour la génération du QR code de contrôle
 */
return new class extends Migration
{
    /**
     * Exécuter la migration.
     */
    public function up(): void
    {
        Schema::table('achats', function (Blueprint $table) {
            $table->boolean('normalise')->default(false)->after('etape');
            $table->string('numero_fne', 100)->nullable()->after('normalise');
            $table->string('signature_dgi', 255)->nullable()->after('numero_fne');
            $table->text('qr_code_data')->nullable()->after('signature_dgi');
        });
    }

    /**
     * Annuler la migration.
     */
    public function down(): void
    {
        Schema::table('achats', function (Blueprint $table) {
            $table->dropColumn(['normalise', 'numero_fne', 'signature_dgi', 'qr_code_data']);
        });
    }
};
