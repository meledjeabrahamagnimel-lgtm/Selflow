<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Un identifiant tiré au hasard dans les adresses, à la place du numéro de
 * ligne.
 *
 * `/admin/ventes/facture/4213` porte une information que personne n'a voulu
 * publier : **le nombre de factures de la plateforme**. Le lot 8.3 a fermé
 * l'oracle en répondant 404 (page introuvable) sur la pièce d'autrui comme sur
 * une pièce inexistante — mais l'adresse elle-même parle encore, et elle sort de
 * l'application : dans un courriel transféré, une capture d'écran, un billet
 * d'assistance, l'historique d'un navigateur partagé.
 *
 * **La clé primaire ne change pas.** L'entier reste ce qui porte les jointures,
 * les index et les clés étrangères, où il est plus rapide et plus compact.
 * L'`uuid` ne sert qu'à désigner la ressource de l'extérieur.
 *
 * **Une identité, une seule.** `getRouteKeyName()` vaut pour toutes les routes
 * d'un modèle, web et API confondues : le mobile reçoit et emploie le même
 * identifiant que le navigateur. Deux identités auraient rendu impossible de
 * rapprocher un journal du serveur, un billet d'assistance et une capture
 * d'écran — et la moitié du problème serait restée entière pour toujours.
 *
 * Le remplissage des lignes existantes se fait ici : le projet est en
 * développement, il n'y a pas de données réelles, mais une base de travail ne
 * doit pas se retrouver avec des colonnes vides que la contrainte d'unicité
 * refuserait ensuite.
 */
return new class extends Migration
{
    /**
     * Les tables dont les enregistrements se désignent dans une adresse.
     *
     * @var array<int, string>
     */
    private const TABLES = [
        'ventes',
        'achats',
        'entreprises',
        'produits',
        'bons_livraison',
        'points_de_vente',
        'b2b_negotiations',
        'immobilisations',
        'fiches_techniques',
        'codes_journaux',
        'clients',
        'consignations',
        'fournisseurs',
        'transferts_stock',
        'lettrages',
        'periodes',
        'dotations_amortissement',
        'ordres_production',
        'utilisateurs',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            // La colonne naît nullable : les lignes déjà en place n'en ont pas
            // encore, et une contrainte posée trop tôt ferait échouer la
            // migration sur la première d'entre elles.
            Schema::table($table, function (Blueprint $colonnes) {
                $colonnes->uuid('uuid')->nullable()->after('id');
            });

            // Le remplissage se fait par paquets : une base de démonstration
            // porte des dizaines de milliers de lignes, et les charger toutes
            // en mémoire pour leur poser un identifiant serait payer cher une
            // opération qui ne se fait qu'une fois.
            DB::table($table)->whereNull('uuid')->orderBy('id')
                ->chunkById(500, function ($lignes) use ($table) {
                    foreach ($lignes as $ligne) {
                        DB::table($table)->where('id', $ligne->id)
                            ->update(['uuid' => (string) Str::uuid()]);
                    }
                });

            // L'unicité vient ensuite, une fois toutes les lignes servies.
            // C'est elle qui garantit qu'une adresse ne désigne qu'une pièce.
            Schema::table($table, function (Blueprint $colonnes) use ($table) {
                $colonnes->unique('uuid', substr($table, 0, 20) . '_uuid_unicite');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            Schema::table($table, function (Blueprint $colonnes) use ($table) {
                $colonnes->dropUnique(substr($table, 0, 20) . '_uuid_unicite');
                $colonnes->dropColumn('uuid');
            });
        }
    }
};
