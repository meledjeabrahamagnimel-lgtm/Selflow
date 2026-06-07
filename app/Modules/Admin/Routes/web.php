<?php

use App\Modules\Admin\Controleurs\AdminControleur;
use App\Modules\Admin\Controleurs\VenteControleur;
use App\Modules\Admin\Controleurs\AchatControleur;
use App\Modules\Admin\Controleurs\StockControleur;
use App\Modules\Admin\Controleurs\TresorerieControleur;
use App\Modules\Admin\Controleurs\PointDeVenteControleur;
use App\Modules\Admin\Controleurs\ProduitControleur;
use App\Modules\Admin\Controleurs\ClientControleur;
use App\Modules\Admin\Controleurs\FournisseurControleur;
use App\Modules\Admin\Controleurs\PersonnelControleur;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->middleware(['auth', 'role:admin', 'apercu.readonly'])
    ->name('admin.')
    ->group(function () {

        // Tableau de bord
        Route::get('/', [AdminControleur::class, 'tableauDeBord'])->name('tableau_de_bord');

        // ── Ventes ──
        Route::prefix('ventes')->name('ventes.')->group(function () {
            Route::get('/nouvelle',        [VenteControleur::class, 'nouvelle'])->name('nouvelle');
            Route::post('/enregistrer',    [VenteControleur::class, 'enregistrer'])->name('enregistrer');
            Route::get('/factures',        [VenteControleur::class, 'factures'])->name('factures');
            Route::get('/historique',      [VenteControleur::class, 'historique'])->name('historique');
            Route::get('/facture/{vente}', [VenteControleur::class, 'imprimer'])->name('imprimer');
        });

        // ── Achats ──
        Route::prefix('achats')->name('achats.')->group(function () {
            Route::get('/nouveau',         [AchatControleur::class, 'nouveau'])->name('nouveau');
            Route::post('/enregistrer',    [AchatControleur::class, 'enregistrer'])->name('enregistrer');
            Route::get('/factures',        [AchatControleur::class, 'factures'])->name('factures');
            Route::get('/historique',      [AchatControleur::class, 'historique'])->name('historique');
            Route::get('/facture/{achat}', [AchatControleur::class, 'imprimer'])->name('imprimer');
        });

        // ── Stock ──
        Route::prefix('stock')->name('stock.')->group(function () {
            Route::get('/',           [StockControleur::class, 'index'])->name('index');
            Route::get('/mouvements', [StockControleur::class, 'mouvements'])->name('mouvements');
        });

        // ── Trésorerie ──
        Route::prefix('tresorerie')->name('tresorerie.')->group(function () {
            Route::get('/encaissements', [TresorerieControleur::class, 'encaissements'])->name('encaissements');
            Route::get('/decaissements', [TresorerieControleur::class, 'decaissements'])->name('decaissements');
            Route::get('/journal',       [TresorerieControleur::class, 'journal'])->name('journal');
        });

        // ── Points de vente ──
        Route::prefix('points-de-vente')->name('pdv.')->group(function () {
            Route::get('/',                     [PointDeVenteControleur::class, 'index'])->name('index');
            Route::post('/',                    [PointDeVenteControleur::class, 'creer'])->name('creer');
            Route::post('/activer/{pdv}',       [PointDeVenteControleur::class, 'activerSession'])->name('activer');
            Route::post('/activer-apercu/{pdv}', [PointDeVenteControleur::class, 'activerApercu'])->name('activer_apercu');
            Route::post('/desactiver-apercu',    [PointDeVenteControleur::class, 'desactiverApercu'])->name('desactiver_apercu');
        });

        // ── Gestion Personnel ──
        Route::prefix('personnel')->name('personnel.')->group(function () {
            Route::get('/',            [PersonnelControleur::class, 'index'])->name('index');
            Route::post('/',           [PersonnelControleur::class, 'creer'])->name('creer');
            Route::get('/{personnel}', [PersonnelControleur::class, 'details'])->name('details');
            Route::put('/{personnel}', [PersonnelControleur::class, 'modifier'])->name('modifier');
            Route::post('/{personnel}/statut', [PersonnelControleur::class, 'changerStatut'])->name('statut');
            Route::delete('/{personnel}', [PersonnelControleur::class, 'supprimer'])->name('supprimer');
        });

        // ── Gestion catalogue ──
        Route::prefix('produits')->name('produits.')->group(function () {
            Route::get('/',        [ProduitControleur::class, 'index'])->name('index');
            Route::post('/',       [ProduitControleur::class, 'creer'])->name('creer');
            Route::put('/{produit}',  [ProduitControleur::class, 'modifier'])->name('modifier');
        });

        Route::prefix('clients')->name('clients.')->group(function () {
            Route::get('/',       [ClientControleur::class, 'index'])->name('index');
            Route::post('/',      [ClientControleur::class, 'creer'])->name('creer');
        });

        Route::prefix('fournisseurs')->name('fournisseurs.')->group(function () {
            Route::get('/',       [FournisseurControleur::class, 'index'])->name('index');
            Route::post('/',      [FournisseurControleur::class, 'creer'])->name('creer');
        });
    });
