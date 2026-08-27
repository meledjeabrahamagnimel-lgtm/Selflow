<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les engagements cessent d'être des compteurs.
 *
 * `produits.quantite_commandee` et `produits.quantite_a_receptionner`
 * existaient depuis l'origine, s'affichaient sur trois écrans, entraient dans
 * le calcul du prévisionnel — et **rien ne les écrivait jamais**. Seul le jeu
 * de démonstration les posait, à zéro. Le prévisionnel valait donc toujours le
 * stock disponible, et la colonne « Commandé » d'un magasin qui attend trente
 * sacs affichait 0.
 *
 * Deux défauts, pas un :
 *
 * 1. **Personne ne les alimentait.** Un compteur dénormalisé doit être
 *    incrémenté à la commande, décrémenté à la livraison, corrigé à
 *    l'annulation, à la modification, à l'avoir. Cinq occasions de dériver, et
 *    aucune n'était traitée.
 * 2. **Ils étaient sur `produits`, donc globaux.** Trente sacs commandés au
 *    magasin d'Abidjan seraient apparus comme engagés au dépôt de Bouaké.
 *
 * La correction ne consiste pas à écrire ces colonnes partout où il aurait
 * fallu : elle consiste à ne plus les stocker. L'engagement se déduit des
 * lignes qui l'ont créé — `quantite - quantite_livree` sur les bons de
 * commande de vente, `quantite - quantite_receptionnee` sur ceux d'achat — et
 * une valeur déduite ne dérive pas.
 *
 * Voir `Produit::quantiteCommandee()` et `Produit::quantiteAReceptionner()`.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Pourquoi le retrait est conditionnel
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Cette migration a échoué en production le 27 août 2026 :
 *
 *     SQLSTATE[42000] 1091 Can't DROP COLUMN `quantite_commandee`;
 *     check that it exists
 *
 * Les deux colonnes n'ont jamais existé sur ce serveur. Elles ont été
 * **ajoutées à `2026_06_05_000004_creer_table_produits` le 20 juillet**, soit
 * quarante-cinq jours après la date de cette migration — donc longtemps après
 * qu'elle eut été appliquée en production. Une migration déjà jouée ne se
 * rejoue pas : modifier son fichier ne change rien à la base qui l'a passée.
 * Le serveur a gardé un `produits` sans ces colonnes, pendant qu'en local
 * chaque `migrate:fresh` les recréait — d'où une suite verte et un
 * déploiement bloqué.
 *
 * Le même écart s'était produit sur `entreprises` : `secteur_activite` et
 * `modules_actifs` ont été ajoutés au même endroit, le même jour, et
 * `2026_07_20_133019_add_missing_columns_to_entreprises` a été écrite pour
 * rattraper les bases qui ne les avaient pas. Ici, le rattrapage n'a pas de
 * sens — ces colonnes doivent disparaître, pas revenir.
 *
 * Ce que la migration veut est un état, non un geste : ces colonnes ne doivent
 * plus être là. Là où elles n'ont jamais été, il n'y a rien à faire.
 */
return new class extends Migration
{
    private const COLONNES = ['quantite_commandee', 'quantite_a_receptionner'];

    public function up(): void
    {
        $presentes = array_values(array_filter(
            self::COLONNES,
            fn ($colonne) => Schema::hasColumn('produits', $colonne)
        ));

        if ($presentes === []) {
            return;
        }

        Schema::table('produits', function (Blueprint $table) use ($presentes) {
            $table->dropColumn($presentes);
        });
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->decimal('quantite_commandee', 15, 3)->default(0);
            $table->decimal('quantite_a_receptionner', 15, 3)->default(0);
        });
    }
};
