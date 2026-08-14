<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Remplir les identifiants opaques que les peuplements ont laissés vides.
 *
 * La migration `2026_08_17_000001_identifiants_opaques` avait bien servi
 * toutes les lignes en place. Mais les semeurs insèrent en SQL brut — dix
 * endroits, sur `ventes`, `achats`, `produits`, `clients`, `fournisseurs`,
 * `codes_journaux`, `fiches_techniques`, `ordres_production` et
 * `transferts_stock` — et **une insertion brute ne passe pas par le modèle** :
 * le crochet `creating` qui pose l'`uuid` ne s'exécute pas.
 *
 * Repeupler une base après cette migration reposait donc des lignes sans
 * identifiant. Et comme MySQL accepte autant de `NULL` qu'on veut dans une
 * colonne unique, rien ne s'y opposait — jusqu'à ce que le tableau de bord
 * demande l'adresse d'une de ces pièces et tombe sur une erreur 500
 * (*Internal Server Error* — erreur interne du serveur), faute de savoir
 * comment la désigner.
 *
 * Les semeurs sont corrigés ; celle-ci répare ce qu'ils ont déjà écrit. Elle
 * ne touche que les lignes vides : une base saine la traverse sans rien faire.
 */
return new class extends Migration
{
    /**
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
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'uuid')) {
                continue;
            }

            DB::table($table)->whereNull('uuid')->orderBy('id')
                ->chunkById(500, function ($lignes) use ($table) {
                    foreach ($lignes as $ligne) {
                        DB::table($table)->where('id', $ligne->id)
                            ->update(['uuid' => (string) Str::uuid()]);
                    }
                });
        }
    }

    /**
     * Rien à défaire : retirer des identifiants déjà publiés casserait les
     * adresses qui circulent.
     */
    public function down(): void
    {
    }
};
