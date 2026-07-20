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
use App\Modules\Admin\Controleurs\EntrepriseControleur;
use App\Modules\Admin\Controleurs\RapportControleur;
use App\Modules\Admin\Controleurs\TransfertStockControleur;
use App\Modules\Admin\Controleurs\ProductionControleur;
use App\Modules\Admin\Controleurs\B2bControleur;
use App\Modules\Admin\Controleurs\BonLivraisonControleur;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')
    ->middleware(['auth', 'role:admin', 'habilitation', 'apercu.readonly', 'periode'])
    ->name('admin.')
    ->group(function () {

        // Tableau de bord
        Route::get('/', [AdminControleur::class, 'tableauDeBord'])->name('tableau_de_bord');
        Route::get('/general', [AdminControleur::class, 'tableauDeBordGeneral'])->name('tableau_de_bord_general');

        // ── Ventes ──
        Route::prefix('ventes')->name('ventes.')->middleware('modules:ventes')->group(function () {
            Route::get('/nouvelle',        [VenteControleur::class, 'nouvelle'])->name('nouvelle');
            Route::post('/enregistrer',    [VenteControleur::class, 'enregistrer'])->name('enregistrer');
            Route::get('/factures',        [VenteControleur::class, 'factures'])->name('factures');
            Route::get('/factures/rechercher', [VenteControleur::class, 'rechercherFacturesPourAvoir'])->name('factures.rechercher');
            Route::get('/facture-details/{vente}', [VenteControleur::class, 'detailsFacturePourAvoir'])->name('factures.details');
            Route::get('/factures/produits-categories', [VenteControleur::class, 'produitsParCategorie'])->name('factures.produits_categories');
            Route::post('/avoir/creer-nouveau', [VenteControleur::class, 'creerAvoirNouveau'])->name('avoir.creer_nouveau');

            Route::get('/facture/{vente}', [VenteControleur::class, 'imprimer'])->name('imprimer');
            Route::get('/facture/{vente}/ticket', [VenteControleur::class, 'imprimerTicket'])->name('ticket');
            Route::get('/{vente}/modifier',    [VenteControleur::class, 'modifierFormulaire'])->name('modifier');
            Route::put('/{vente}/modifier',    [VenteControleur::class, 'enregistrerModification'])->name('modifier.enregistrer');
            Route::post('/{vente}/confirmer',  [VenteControleur::class, 'confirmerCommande'])->name('confirmer');
            Route::post('/{vente}/facturer',   [VenteControleur::class, 'facturer'])->name('facturer');
            Route::post('/{vente}/avoir',      [VenteControleur::class, 'creerAvoir'])->name('avoir');
            Route::post('/{vente}/normaliser', [VenteControleur::class, 'normaliser'])->name('normaliser');
            // Workflow Devis → Commande → Facture
            Route::post('/{vente}/envoyer',              [VenteControleur::class, 'envoyer'])->name('envoyer');
            Route::post('/{vente}/convertir-commande',   [VenteControleur::class, 'convertirEnCommande'])->name('convertir.commande');
            Route::post('/{vente}/convertir-facture',    [VenteControleur::class, 'convertirEnFacture'])->name('convertir.facture');
            Route::delete('/{vente}/supprimer',          [VenteControleur::class, 'supprimer'])->name('supprimer');
            // Bon de Livraison
            Route::get ('/{vente}/livraison/creer',       [BonLivraisonControleur::class, 'creerDepuisBC'])->name('livraison.creer');
            Route::post('/{vente}/livraison/enregistrer', [BonLivraisonControleur::class, 'enregistrer'])->name('livraison.enregistrer');
            Route::get ('/livraisons',                    [BonLivraisonControleur::class, 'index'])->name('livraisons');
            Route::get ('/livraison/{bl}',                [BonLivraisonControleur::class, 'imprimer'])->name('livraison.voir');
            Route::post('/livraison/{bl}/livrer',         [BonLivraisonControleur::class, 'marquerLivre'])->name('livraison.livrer');
            Route::post('/livraison/{bl}/facturer',       [BonLivraisonControleur::class, 'convertirEnFacture'])->name('livraison.facturer');
        });

        // ── Achats ──
        Route::prefix('achats')->name('achats.')->middleware('modules:achats')->group(function () {
            Route::get('/nouveau',         [AchatControleur::class, 'nouveau'])->name('nouveau');
            Route::post('/enregistrer',    [AchatControleur::class, 'enregistrer'])->name('enregistrer');
            Route::get('/factures',        [AchatControleur::class, 'factures'])->name('factures');
            Route::get('/factures/rechercher', [AchatControleur::class, 'rechercherFacturesPourAvoir'])->name('factures.rechercher');
            Route::get('/facture-details/{achat}', [AchatControleur::class, 'detailsFacturePourAvoir'])->name('factures.details');
            Route::get('/factures/produits-categories', [AchatControleur::class, 'produitsParCategorie'])->name('factures.produits_categories');
            Route::post('/avoir/creer-nouveau', [AchatControleur::class, 'creerAvoirNouveau'])->name('avoir.creer_nouveau');

            Route::get('/facture/{achat}', [AchatControleur::class, 'imprimer'])->name('imprimer');
            Route::get('/facture/{achat}/bapa', [AchatControleur::class, 'imprimerBapa'])->name('bapa');
            Route::post('/{achat}/confirmer', [AchatControleur::class, 'confirmerCommande'])->name('confirmer');
            Route::post('/{achat}/facturer',  [AchatControleur::class, 'facturer'])->name('facturer');
            Route::post('/{achat}/avoir',     [AchatControleur::class, 'creerAvoir'])->name('avoir');
            Route::post('/{achat}/normaliser', [AchatControleur::class, 'normaliser'])->name('normaliser');
            Route::post('/{achat}/fne',        [\App\Modules\Admin\Controleurs\FneControleur::class, 'attacherFneAchat'])->name('fne.attacher');
        });

        // ── FNE / DGI Stub (Lot I) ──
        Route::prefix('fne')->name('fne.')->group(function () {
            Route::post('/rechercher', [\App\Modules\Admin\Controleurs\FneControleur::class, 'rechercherDocumentFiscal'])->name('rechercher');
        });

        // ── Stock ──
        Route::prefix('stock')->name('stock.')->middleware('modules:stock')->group(function () {
            Route::get('/',           [StockControleur::class, 'index'])->name('index');
            Route::get('/mouvements', [StockControleur::class, 'mouvements'])->name('mouvements');
            Route::get('/rebut',      [StockControleur::class, 'rebut'])->name('rebut');
            Route::post('/rebut',     [StockControleur::class, 'retirerRebut'])->name('rebut.retirer');
            
            // Réceptions (Achats)
            Route::get('/receptions',                   [StockControleur::class, 'receptions'])->name('receptions');
            Route::get('/receptions/{achat}',           [StockControleur::class, 'ficheReception'])->name('receptions.fiche');
            Route::post('/receptions/{achat}/valider',   [StockControleur::class, 'validerReception'])->name('receptions.valider');

            // Livraisons (Ventes)
            Route::get('/livraisons',                   [StockControleur::class, 'livraisons'])->name('livraisons');
            Route::get('/livraisons/{vente}',           [StockControleur::class, 'ficheLivraison'])->name('livraisons.fiche');
            Route::post('/livraisons/{vente}/valider',   [StockControleur::class, 'validerLivraison'])->name('livraisons.valider');

            // Transferts internes
            Route::get('/transferts',                [TransfertStockControleur::class, 'index'])->name('transferts.index');
            Route::post('/transferts',               [TransfertStockControleur::class, 'creer'])->name('transferts.creer');
            Route::post('/transferts/{transfert}/valider', [TransfertStockControleur::class, 'valider'])->name('transferts.valider');
            Route::post('/transferts/{transfert}/rejeter', [TransfertStockControleur::class, 'rejeter'])->name('transferts.rejeter');
        });

        // ── Trésorerie ──
        Route::prefix('tresorerie')->name('tresorerie.')->middleware('modules:comptabilite')->group(function () {
            Route::get('/encaissements', [TresorerieControleur::class, 'encaissements'])->name('encaissements');
            Route::get('/decaissements', [TresorerieControleur::class, 'decaissements'])->name('decaissements');
            Route::get('/journal',       [TresorerieControleur::class, 'journal'])->name('journal');
            Route::get('/codes-journaux', [TresorerieControleur::class, 'codesJournaux'])->name('codes_journaux');
            Route::post('/codes-journaux', [TresorerieControleur::class, 'creerCodeJournal'])->name('creer_code_journal');
            Route::delete('/codes-journaux/{code}', [TresorerieControleur::class, 'supprimerCodeJournal'])->name('supprimer_code_journal');
        });

        // ── Comptabilité ──
        Route::prefix('comptabilite')->name('comptabilite.')->middleware('modules:comptabilite')->group(function () {
            Route::get('/globale',   [\App\Modules\Admin\Controleurs\ComptabiliteControleur::class, 'globale'])->name('globale');
            Route::get('/creances',  [\App\Modules\Admin\Controleurs\ComptabiliteControleur::class, 'creances'])->name('creances');
            Route::get('/tiers/{type}/{id}', [\App\Modules\Admin\Controleurs\ComptabiliteControleur::class, 'releveTiers'])->name('releve_tiers');
            Route::post('/reglement', [\App\Modules\Admin\Controleurs\ComptabiliteControleur::class, 'enregistrerReglement'])->name('enregistrer_reglement');
            Route::get('/plan-comptable', [\App\Modules\Admin\Controleurs\ComptabiliteControleur::class, 'planComptable'])->name('plan_comptable');
            Route::post('/plan-comptable', [\App\Modules\Admin\Controleurs\ComptabiliteControleur::class, 'creerCompteComptable'])->name('creer_compte_comptable');
            Route::post('/ecritures/manuelle', [\App\Modules\Admin\Controleurs\ComptabiliteControleur::class, 'creerEcritureManuelle'])->name('ecriture_manuelle');
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
            Route::get('/',                          [ProduitControleur::class, 'index'])->name('index');
            Route::post('/',                         [ProduitControleur::class, 'creer'])->name('creer');
            Route::get('/{produit}/fiche',           [ProduitControleur::class, 'fiche'])->name('fiche');
            Route::put('/{produit}',                 [ProduitControleur::class, 'modifier'])->name('modifier');
            Route::patch('/{produit}/archiver',      [ProduitControleur::class, 'archiver'])->name('archiver');
            Route::patch('/{produit}/description',   [ProduitControleur::class, 'description'])->name('description');
            Route::post('/{produit}/photo',          [ProduitControleur::class, 'uploaderPhoto'])->name('photo');
            Route::post('/{produit}/details',        [ProduitControleur::class, 'ajouterDetails'])->name('details.ajouter');
            Route::delete('/details/{detail}',       [ProduitControleur::class, 'supprimerDetail'])->name('details.supprimer');
        });

        // ── Production ──
        Route::prefix('production')->name('production.')->middleware('modules:production')->group(function () {
            Route::prefix('fiches-techniques')->name('fiches_techniques.')->group(function () {
                Route::get('/',            [ProductionControleur::class, 'indexFichesTechniques'])->name('index');
                Route::get('/creer',       [ProductionControleur::class, 'creerFicheTechnique'])->name('creer');
                Route::post('/creer',      [ProductionControleur::class, 'enregistrerFicheTechnique'])->name('enregistrer');
                Route::get('/{fiche}/modifier', [ProductionControleur::class, 'modifierFicheTechnique'])->name('modifier');
                Route::put('/{fiche}/modifier', [ProductionControleur::class, 'enregistrerModificationFicheTechnique'])->name('modifier.enregistrer');
                Route::delete('/{fiche}',  [ProductionControleur::class, 'supprimerFicheTechnique'])->name('supprimer');
            });

            Route::prefix('ordres')->name('ordres.')->group(function () {
                Route::get('/',            [ProductionControleur::class, 'indexOrdres'])->name('index');
                Route::get('/creer',       [ProductionControleur::class, 'creerOrdre'])->name('creer');
                Route::post('/creer',      [ProductionControleur::class, 'enregistrerOrdre'])->name('enregistrer');
                Route::post('/{ordre}/valider', [ProductionControleur::class, 'validerOrdre'])->name('valider');
            });
        });

        // ── Communication B2B ──
        Route::prefix('b2b')->name('b2b.')->group(function () {
            Route::get('/negociations/client',     [B2bControleur::class, 'negociationsClient'])->name('negociations.client');
            Route::get('/negociations/fournisseur', [B2bControleur::class, 'negociationsFournisseur'])->name('negociations.fournisseur');
            Route::post('/rfq',                    [B2bControleur::class, 'creerRfq'])->name('rfq.creer');
            Route::post('/negociation/{negociation}/proposer', [B2bControleur::class, 'proposerPrix'])->name('negociation.proposer');
            Route::get('/negociation/{negociation}/stock', [B2bControleur::class, 'verifierStock'])->name('negociation.stock');
            Route::post('/negociation/{negociation}/finaliser', [B2bControleur::class, 'finaliserB2b'])->name('negociation.finaliser');
            Route::post('/achat/{achat}/accepter', [B2bControleur::class, 'accepterAchatB2b'])->name('achat.accepter');
        });

        Route::prefix('clients')->name('clients.')->group(function () {
            Route::get('/',       [ClientControleur::class, 'index'])->name('index');
            Route::post('/',      [ClientControleur::class, 'creer'])->name('creer');
            Route::put('/{client}', [ClientControleur::class, 'modifier'])->name('modifier');
        });

        Route::prefix('fournisseurs')->name('fournisseurs.')->group(function () {
            Route::get('/',       [FournisseurControleur::class, 'index'])->name('index');
            Route::post('/',      [FournisseurControleur::class, 'creer'])->name('creer');
            Route::put('/{fournisseur}', [FournisseurControleur::class, 'modifier'])->name('modifier');
        });

        // ── Banques ──
        Route::post('/banques/creer', [TresorerieControleur::class, 'creerBanqueAjax'])->name('banques.creer');

        // ── Profil utilisateur ──
        Route::get('/mon-profil', [AdminControleur::class, 'monProfil'])->name('mon_profil');
        Route::put('/mon-profil', [AdminControleur::class, 'enregistrerProfil'])->name('mon_profil.enregistrer');

        // ── Paramètres entreprise ──
        Route::get('/entreprise/parametres', [EntrepriseControleur::class, 'parametres'])->name('entreprise.parametres');
        Route::put('/entreprise/parametres', [EntrepriseControleur::class, 'enregistrerParametres'])->name('entreprise.parametres.enregistrer');
        Route::post('/entreprise/comptaflow/sync-simulation', [EntrepriseControleur::class, 'simulerSyncComptaflow'])->name('entreprise.comptaflow.sync');
        Route::post('/entreprise/comptaflow/sync', [EntrepriseControleur::class, 'synchroniserComptaflow'])->name('entreprise.comptaflow.sync_real');


        // ── Périodes / Exercices ──
        Route::post('/periods/switch', [EntrepriseControleur::class, 'switchPeriode'])->name('periods.switch');
        Route::post('/entreprise/periodes', [EntrepriseControleur::class, 'creerPeriode'])->name('entreprise.periodes.creer');
        Route::post('/entreprise/periodes/{periode}/cloturer', [EntrepriseControleur::class, 'cloturerPeriode'])->name('entreprise.periodes.cloturer');

        // ── Rapports ──
        Route::prefix('rapports')->name('rapports.')->group(function () {
            Route::get('/analyse-activite', [RapportControleur::class, 'analyseActivite'])->name('analyse_activite');
        });
    });

// ───────────────────────────────────────────────────────────────────────
// Routes pour l'interface SuperAdmin
// ───────────────────────────────────────────────────────────────────────
Route::prefix('superadmin')
    ->middleware(['auth', 'role:superadmin'])
    ->name('superadmin.')
    ->group(function () {
        Route::get('/', [\App\Modules\Admin\Controleurs\SuperadminControleur::class, 'tableauDeBord'])->name('tableau_de_bord');
        Route::get('/entreprises', [\App\Modules\Admin\Controleurs\SuperadminControleur::class, 'entreprises'])->name('entreprises');
        Route::get('/entreprises/creer', [\App\Modules\Admin\Controleurs\SuperadminControleur::class, 'creerFormulaire'])->name('entreprises.creer');
        Route::post('/entreprises/creer', [\App\Modules\Admin\Controleurs\SuperadminControleur::class, 'creer'])->name('entreprises.creer.enregistrer');
        Route::get('/entreprises/{entreprise}/modifier', [\App\Modules\Admin\Controleurs\SuperadminControleur::class, 'modifierFormulaire'])->name('entreprises.modifier');
        Route::put('/entreprises/{entreprise}/modifier', [\App\Modules\Admin\Controleurs\SuperadminControleur::class, 'modifier'])->name('entreprises.modifier.enregistrer');
        Route::post('/entreprises/{entreprise}/toggle-status', [\App\Modules\Admin\Controleurs\SuperadminControleur::class, 'toggleStatus'])->name('entreprises.toggle_status');
        Route::delete('/entreprises/{entreprise}', [\App\Modules\Admin\Controleurs\SuperadminControleur::class, 'supprimer'])->name('entreprises.supprimer');
        Route::get('/utilisateurs', [\App\Modules\Admin\Controleurs\SuperadminControleur::class, 'utilisateurs'])->name('utilisateurs');
        Route::put('/utilisateurs/{utilisateur}', [\App\Modules\Admin\Controleurs\SuperadminControleur::class, 'modifierUtilisateur'])->name('utilisateurs.modifier');
    });

// ───────────────────────────────────────────────────────────────────────
// Routes pour l'interface Caissier (Point de Vente)
// ───────────────────────────────────────────────────────────────────────
Route::prefix('caissier')
    ->middleware(['auth', 'role:admin,caissier', 'caissier.acces', 'habilitation', 'apercu.readonly', 'periode'])
    ->name('caissier.')
    ->group(function () {
        // Le tableau de bord du caissier est l'écran de caisse/nouvelle vente
        Route::get('/', [VenteControleur::class, 'nouvelle'])->name('tableau_de_bord');

        Route::prefix('ventes')->name('ventes.')->group(function () {
            Route::get('/nouvelle',        [VenteControleur::class, 'nouvelle'])->name('nouvelle');
            Route::post('/enregistrer',    [VenteControleur::class, 'enregistrer'])->name('enregistrer');
            Route::get('/factures',        [VenteControleur::class, 'factures'])->name('factures');

            Route::get('/facture/{vente}', [VenteControleur::class, 'imprimer'])->name('imprimer');
            Route::get('/facture/{vente}/ticket', [VenteControleur::class, 'imprimerTicket'])->name('ticket');
            Route::get('/{vente}/modifier',    [VenteControleur::class, 'modifierFormulaire'])->name('modifier');
            Route::put('/{vente}/modifier',    [VenteControleur::class, 'enregistrerModification'])->name('modifier.enregistrer');
            Route::post('/{vente}/normaliser', [VenteControleur::class, 'normaliser'])->name('normaliser');
            // Workflow Devis → Commande → Facture
            Route::post('/{vente}/envoyer',             [VenteControleur::class, 'envoyer'])->name('envoyer');
            Route::post('/{vente}/convertir-commande',  [VenteControleur::class, 'convertirEnCommande'])->name('convertir.commande');
            Route::post('/{vente}/convertir-facture',   [VenteControleur::class, 'convertirEnFacture'])->name('convertir.facture');
            Route::delete('/{vente}/supprimer',         [VenteControleur::class, 'supprimer'])->name('supprimer');
            // Bon de Livraison
            Route::get ('/{vente}/livraison/creer',       [BonLivraisonControleur::class, 'creerDepuisBC'])->name('livraison.creer');
            Route::post('/{vente}/livraison/enregistrer', [BonLivraisonControleur::class, 'enregistrer'])->name('livraison.enregistrer');
            Route::get ('/livraisons',                    [BonLivraisonControleur::class, 'index'])->name('livraisons');
            Route::get ('/livraison/{bl}',                [BonLivraisonControleur::class, 'imprimer'])->name('livraison.voir');
            Route::post('/livraison/{bl}/livrer',         [BonLivraisonControleur::class, 'marquerLivre'])->name('livraison.livrer');
            Route::post('/livraison/{bl}/facturer',       [BonLivraisonControleur::class, 'convertirEnFacture'])->name('livraison.facturer');
        });

        Route::prefix('stock')->name('stock.')->group(function () {
            Route::get('/',           [StockControleur::class, 'index'])->name('index');
            Route::get('/mouvements', [StockControleur::class, 'mouvements'])->name('mouvements');
        });

        Route::prefix('tresorerie')->name('tresorerie.')->group(function () {
            Route::get('/encaissements', [TresorerieControleur::class, 'encaissements'])->name('encaissements');
        });

        // ── Banques ──
        Route::post('/banques/creer', [TresorerieControleur::class, 'creerBanqueAjax'])->name('banques.creer');
    });

