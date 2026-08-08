<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les rayons portent leurs comptes.
 *
 * Dans le référentiel, ce n'est pas l'article qui décide de son imputation :
 * c'est sa **famille** — le rayon — qui porte les quatre comptes, et le type
 * d'article qui impose la racine que la famille subdivise. Un magasin qui crée
 * « Boissons fraîches » veut que tout ce qui y entre s'impute au même compte de
 * vente, sans avoir à le redire article par article.
 *
 * `SouscriptionProfilService` copiait pourtant les comptes **sur chaque
 * produit**, et la catégorie de l'entreprise n'en gardait aucun. Trois
 * conséquences :
 *
 * - un article créé à la main après la souscription n'héritait de rien, et
 *   tombait sur le compte générique `701000` ;
 * - changer le compte d'un rayon obligeait à rouvrir chacun de ses articles ;
 * - le lien entre le rayon et son imputation, qui est la règle métier, ne
 *   figurait nulle part une fois la copie faite.
 *
 * Les quatre colonnes rejoignent donc `categories`, et l'imputation se lit par
 * la chaîne : **article → rayon → défaut de configuration**. Le compte posé sur
 * l'article reste prioritaire — c'est l'exception que l'utilisateur assume.
 */
return new class extends Migration
{
    /** Les quatre comptes que porte un rayon, dans l'ordre du référentiel. */
    private const COMPTES = ['compte_vente', 'compte_achat', 'compte_stock', 'compte_variation'];

    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            foreach (self::COMPTES as $compte) {
                $table->string($compte, 20)->nullable()->after('prefixe');
            }
        });

        // Les articles qui se stockent ont besoin d'un compte de stock et d'un
        // compte de variation pour que l'inventaire permanent puisse écrire.
        // Seuls `compte_vente` et `compte_achat` existaient sur `produits`.
        Schema::table('produits', function (Blueprint $table) {
            $table->string('compte_stock', 20)->nullable()->after('compte_achat');
            $table->string('compte_variation', 20)->nullable()->after('compte_stock');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(self::COMPTES);
        });

        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn(['compte_stock', 'compte_variation']);
        });
    }
};
