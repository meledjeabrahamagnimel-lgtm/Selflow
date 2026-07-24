<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute une colonne pour stocker l'URL/chemin du PDF officiel renvoyé par
 * la DGI lors de la normalisation d'une facture (avec sticker et logos DGI).
 *
 * Utilisée par le module <GESTION FNE> (page "Factures & Reçus émis/reçus")
 * pour l'action "Télécharger" :
 *   - Document normalisé (normalise = true)  -> lien vers fichier_fne_pdf_url
 *   - Document non normalisé (normalise = false) -> lien vers la facture
 *     d'origine générée par Selflow (route "imprimer" existante).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->string('fichier_fne_pdf_url', 500)->nullable()->after('qr_code_data');
        });

        Schema::table('achats', function (Blueprint $table) {
            $table->string('fichier_fne_pdf_url', 500)->nullable()->after('qr_code_data');
        });
    }

    public function down(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->dropColumn('fichier_fne_pdf_url');
        });

        Schema::table('achats', function (Blueprint $table) {
            $table->dropColumn('fichier_fne_pdf_url');
        });
    }
};
