<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Table "fne_credentials" — Identifiants FNE (Facture Normalisée Électronique
 * / DGI Côte d'Ivoire) PAR ENTREPRISE.
 *
 * Contexte : il n'existe pas de clé API FNE unique partagée entre toutes les
 * entreprises clientes de Selflow — chaque entreprise doit obtenir SA PROPRE
 * clé auprès de la DGI (sur demande), en 2 temps :
 *   1. Clé de TEST, fournie immédiatement à la demande, pour normaliser des
 *      factures en environnement sandbox et valider l'intégration.
 *   2. Clé RÉELLE (production), fournie par la DGI une fois les tests validés.
 *
 * Sécurité : les colonnes cle_test / cle_reelle sont chiffrées au repos via
 * le cast Eloquent 'encrypted' (AES-256-CBC, clé dérivée de APP_KEY — le
 * même mécanisme que Laravel utilise pour tout secret applicatif, offrant
 * un niveau de protection comparable à celui des variables .env : la donnée
 * en base est illisible sans APP_KEY, qui elle-même n'existe que dans
 * l'environnement serveur). Aucune clé n'est donc jamais stockée en clair.
 *
 * Voir /PLAN/FNE-gestion-des-cles.md pour le plan complet (workflow,
 * champs DGI, sécurité, décision sur la reconfirmation par mot de passe).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fne_credentials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entreprise_id')->unique();

            // Clé de test (sandbox DGI) — chiffrée
            $table->text('cle_test')->nullable();
            $table->timestamp('cle_test_ajoutee_at')->nullable();
            $table->unsignedBigInteger('cle_test_ajoutee_par')->nullable();

            // Clé réelle (production DGI) — chiffrée
            $table->text('cle_reelle')->nullable();
            $table->timestamp('cle_reelle_ajoutee_at')->nullable();
            $table->unsignedBigInteger('cle_reelle_ajoutee_par')->nullable();

            // Étape actuelle : non_configure | test | validee
            // 'validee' = la clé réelle a été ajoutée et est utilisée en priorité.
            $table->string('statut', 20)->default('non_configure')->index();

            // Résultat du dernier test de connexion (bouton "Tester" côté Admin)
            $table->string('derniere_verification_resultat', 20)->nullable(); // 'succes' | 'echec'
            $table->timestamp('derniere_verification_at')->nullable();

            // Identifiants DGI complémentaires (voir plan pour le détail des
            // champs échangés avec la DGI) — non sensibles, pas de chiffrement requis
            $table->string('ncc_associe', 30)->nullable()
                ->comment('NCC pour lequel la clé a été émise, à des fins de vérification de cohérence');
            $table->text('notes_superadmin')->nullable()
                ->comment('Notes libres superadmin : ex. date de demande DGI, contact, etc.');

            $table->timestamps();

            $table->foreign('entreprise_id')->references('id')->on('entreprises')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fne_credentials');
    }
};
