<?php

use App\Modules\Admin\Controleurs\Api\AdminApiControleur;
use App\Modules\Admin\Controleurs\Api\VenteApiControleur;
use App\Modules\Admin\Controleurs\Api\AchatApiControleur;
use App\Modules\Admin\Controleurs\Api\StockApiControleur;
use App\Modules\Admin\Controleurs\Api\TresorerieApiControleur;
use App\Modules\Admin\Controleurs\Api\PointDeVenteApiControleur;
use App\Modules\Admin\Controleurs\Api\PersonnelApiControleur;
use App\Modules\Admin\Controleurs\Api\ProduitApiControleur;
use App\Modules\Admin\Controleurs\Api\ClientApiControleur;
use App\Modules\Admin\Controleurs\Api\FournisseurApiControleur;
use App\Modules\Admin\Controleurs\Api\EntrepriseApiControleur;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->middleware(['auth.api', 'role:admin'])
    ->group(function () {

        // Tableau de bord
        Route::get('/tableau-de-bord', [AdminApiControleur::class, 'tableauDeBord']);

        // ── Ventes ──
        Route::prefix('ventes')->group(function () {
            Route::get('/donnees-formulaire', [VenteApiControleur::class, 'donneesFormulaire']);
            Route::post('/enregistrer',       [VenteApiControleur::class, 'enregistrer']);
            Route::get('/factures',           [VenteApiControleur::class, 'factures']);
            Route::get('/devis',              [VenteApiControleur::class, 'devis']);
            Route::get('/commandes',          [VenteApiControleur::class, 'commandes']);
            Route::get('/historique',         [VenteApiControleur::class, 'historique']);
            Route::get('/facture/{vente}',    [VenteApiControleur::class, 'details']);
            Route::post('/{vente}/normaliser', [VenteApiControleur::class, 'normaliser']);
            Route::put('/{vente}/modifier',    [VenteApiControleur::class, 'modifierStatut']);
        });

        // ── Achats ──
        Route::prefix('achats')->group(function () {
            Route::get('/donnees-formulaire', [AchatApiControleur::class, 'donneesFormulaire']);
            Route::post('/enregistrer',       [AchatApiControleur::class, 'enregistrer']);
            Route::get('/factures',           [AchatApiControleur::class, 'factures']);
            Route::get('/historique',         [AchatApiControleur::class, 'historique']);
            Route::get('/facture/{achat}',    [AchatApiControleur::class, 'details']);
            Route::put('/{achat}/modifier',   [AchatApiControleur::class, 'modifierStatut']);
        });

        // ── Stock ──
        Route::prefix('stock')->group(function () {
            Route::get('/',           [StockApiControleur::class, 'index']);
            Route::get('/mouvements', [StockApiControleur::class, 'mouvements']);
        });

        // ── Trésorerie ──
        Route::prefix('tresorerie')->group(function () {
            Route::get('/encaissements',  [TresorerieApiControleur::class, 'encaissements']);
            Route::get('/decaissements',  [TresorerieApiControleur::class, 'decaissements']);
            Route::get('/journal',        [TresorerieApiControleur::class, 'journal']);
            Route::get('/codes-journaux', [TresorerieApiControleur::class, 'codesJournaux']);
            Route::post('/codes-journaux', [TresorerieApiControleur::class, 'creerCodeJournal']);
            Route::delete('/codes-journaux/{code}', [TresorerieApiControleur::class, 'supprimerCodeJournal']);
        });

        // ── Banques ──
        Route::post('/banques/creer', [TresorerieApiControleur::class, 'creerBanque']);

        // ── Points de vente ──
        Route::prefix('points-de-vente')->group(function () {
            Route::get('/',                     [PointDeVenteApiControleur::class, 'index']);
            Route::post('/',                    [PointDeVenteApiControleur::class, 'creer']);
            Route::post('/activer/{pdv}',       [PointDeVenteApiControleur::class, 'activerSession']);
            Route::post('/activer-apercu/{pdv}', [PointDeVenteApiControleur::class, 'activerApercu']);
            Route::post('/desactiver-apercu',    [PointDeVenteApiControleur::class, 'desactiverApercu']);
        });

        // ── Gestion Personnel ──
        Route::prefix('personnel')->group(function () {
            Route::get('/',            [PersonnelApiControleur::class, 'index']);
            Route::post('/',           [PersonnelApiControleur::class, 'creer']);
            Route::get('/{personnel}', [PersonnelApiControleur::class, 'details']);
            Route::put('/{personnel}', [PersonnelApiControleur::class, 'modifier']);
            Route::post('/{personnel}/statut', [PersonnelApiControleur::class, 'changerStatut']);
            Route::delete('/{personnel}', [PersonnelApiControleur::class, 'supprimer']);
        });

        // ── Gestion catalogue (produits, clients, fournisseurs) ──
        Route::prefix('produits')->group(function () {
            Route::get('/',        [ProduitApiControleur::class, 'index']);
            Route::post('/',       [ProduitApiControleur::class, 'creer']);
            Route::put('/{produit}',  [ProduitApiControleur::class, 'modifier']);
        });

        Route::prefix('clients')->group(function () {
            Route::get('/',       [ClientApiControleur::class, 'index']);
            Route::post('/',      [ClientApiControleur::class, 'creer']);
        });

        Route::prefix('fournisseurs')->group(function () {
            Route::get('/',       [FournisseurApiControleur::class, 'index']);
            Route::post('/',      [FournisseurApiControleur::class, 'creer']);
        });

        // ── Paramètres entreprise ──
        Route::get('/entreprise/parametres', [EntrepriseApiControleur::class, 'parametres']);
        Route::post('/entreprise/parametres', [EntrepriseApiControleur::class, 'enregistrerParametres']); // POST pour supporter multipart/form-data avec PUT simulé si nécessaire
    });
