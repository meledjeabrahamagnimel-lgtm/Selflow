<?php

namespace App\Modules\Authentification\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class VerifierHabilitationRoute
{
    /**
     * Gérer la requête entrante et vérifier l'habilitation de l'utilisateur.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('connexion');
        }

        $utilisateur = Auth::user();

        // Les administrateurs et super-administrateurs possèdent toutes les habilitations par défaut
        if ($utilisateur->estAdmin() || $utilisateur->estSuperAdmin()) {
            return $next($request);
        }

        $nomRoute = $request->route()->getName();

        // Normaliser la route caissier en nom de route admin pour utiliser le même dictionnaire de droits
        $routeNormalisee = str_replace('caissier.', 'admin.', $nomRoute);

        // Correspondance entre les routes et les clés d'habilitations
        $correspondances = [
            'admin.tableau_de_bord'          => 'tableau_de_bord_personnel',
            'admin.tableau_de_bord_general'  => 'tableau_de_bord_general',
            
            'admin.ventes.nouvelle'          => 'nouvelle_vente',
            'admin.ventes.enregistrer'       => 'nouvelle_vente',
            'admin.ventes.factures'          => 'factures_vente',
            'admin.ventes.historique'        => 'historique_ventes',
            'admin.ventes.imprimer'          => 'factures_vente',
            'admin.ventes.ticket'            => 'factures_vente',
            'admin.ventes.avoir'             => 'factures_vente',
            'admin.ventes.normaliser'        => 'factures_vente',
            
            'admin.achats.nouveau'           => 'nouvel_achat',
            'admin.achats.enregistrer'       => 'nouvel_achat',
            'admin.achats.factures'          => 'factures_achat',
            'admin.achats.historique'        => 'historique_achats',
            'admin.achats.imprimer'          => 'factures_achat',
            'admin.achats.bapa'              => 'factures_achat',
            'admin.achats.avoir'             => 'factures_achat',
            'admin.achats.normaliser'        => 'factures_achat',
            
            'admin.stock.index'              => 'stock_articles',
            'admin.stock.mouvements'         => 'stock_mouvements',
            'admin.stock.rebut'              => 'stock_articles',
            'admin.stock.rebut.retirer'      => 'stock_articles',
            'admin.stock.transferts.index'   => 'stock_articles',
            'admin.stock.transferts.creer'   => 'stock_articles',
            'admin.stock.transferts.valider' => 'stock_articles',
            'admin.stock.transferts.rejeter' => 'stock_articles',
            'admin.stock.receptions'         => 'stock_articles',
            'admin.stock.receptions.fiche'   => 'stock_articles',
            'admin.stock.receptions.valider' => 'stock_articles',
            'admin.stock.livraisons'         => 'stock_articles',
            'admin.stock.livraisons.fiche'   => 'stock_articles',
            'admin.stock.livraisons.valider' => 'stock_articles',
            
            'admin.tresorerie.encaissements' => 'tresorerie_encaissements',
            'admin.tresorerie.decaissements' => 'tresorerie_decaissements',
            'admin.tresorerie.journal'       => 'tresorerie_journal',
            'admin.tresorerie.codes_journaux' => 'tresorerie_codes_journaux',
            'admin.tresorerie.creer_code_journal' => 'tresorerie_codes_journaux',
            'admin.tresorerie.supprimer_code_journal' => 'tresorerie_codes_journaux',
            'admin.banques.creer'            => 'nouvelle_vente',
            
            'admin.comptabilite.globale'     => 'comptabilite_globale',
            'admin.comptabilite.creances'    => 'comptabilite_creances',
            'admin.comptabilite.releve_tiers'=> 'comptabilite_creances',
            'admin.comptabilite.reglement'   => 'comptabilite_creances',
            'admin.comptabilite.plan_comptable' => 'comptabilite_plan_comptable',
            'admin.comptabilite.creer_compte_comptable' => 'comptabilite_plan_comptable',
            
            'admin.pdv.index'                => 'gestion_pdv',
            'admin.pdv.creer'                => 'gestion_pdv',
            'admin.pdv.activer'              => 'gestion_pdv',
            'admin.pdv.activer_apercu'       => 'gestion_pdv',
            'admin.pdv.desactiver_apercu'    => 'gestion_pdv',
            
            'admin.personnel.index'          => 'gestion_personnel',
            'admin.personnel.creer'          => 'gestion_personnel',
            'admin.personnel.details'        => 'gestion_personnel',
            'admin.personnel.modifier'       => 'gestion_personnel',
            'admin.personnel.statut'         => 'gestion_personnel',
            'admin.personnel.supprimer'      => 'gestion_personnel',
            
            'admin.produits.index'             => 'catalogue_produits',
            'admin.produits.creer'             => 'catalogue_produits',
            'admin.produits.modifier'          => 'catalogue_produits',
            'admin.produits.fiche'             => 'catalogue_produits',
            'admin.produits.archiver'          => 'catalogue_produits',
            'admin.produits.description'       => 'catalogue_produits',
            'admin.produits.photo'             => 'catalogue_produits',
            'admin.produits.details.ajouter'   => 'catalogue_produits',
            'admin.produits.details.supprimer' => 'catalogue_produits',
            
            // Production
            'admin.production.fiches_techniques.index'    => 'production_recettes',
            'admin.production.fiches_techniques.creer'    => 'production_recettes',
            'admin.production.fiches_techniques.enregistrer'=> 'production_recettes',
            'admin.production.fiches_techniques.modifier' => 'production_recettes',
            'admin.production.fiches_techniques.modifier.enregistrer' => 'production_recettes',
            'admin.production.fiches_techniques.supprimer'=> 'production_recettes',
            'admin.production.ordres.index'              => 'production_ordres',
            'admin.production.ordres.creer'              => 'production_ordres',
            'admin.production.ordres.enregistrer'        => 'production_ordres',
            'admin.production.ordres.valider'            => 'production_ordres',
            
            // Communication B2B
            'admin.b2b.negociations.client'      => 'nouvel_achat',
            'admin.b2b.negociations.fournisseur' => 'nouvelle_vente',
            'admin.b2b.rfq.creer'                => 'nouvel_achat',
            'admin.b2b.negociation.proposer'     => 'nouvel_achat',
            'admin.b2b.negociation.stock'        => 'nouvelle_vente',
            'admin.b2b.negociation.finaliser'    => 'nouvelle_vente',
            'admin.b2b.achat.accepter'           => 'nouvel_achat',
            
            'admin.clients.index'            => 'tiers_clients',
            'admin.clients.creer'            => 'tiers_clients',
            
            'admin.fournisseurs.index'       => 'tiers_fournisseurs',
            'admin.fournisseurs.creer'       => 'tiers_fournisseurs',

            'admin.rapports.analyse_activite' => 'rapports_analyse',
        ];

        if (isset($correspondances[$routeNormalisee])) {
            $cleHabilitation = $correspondances[$routeNormalisee];

            // Distinction spécifique pour l'onglet d'habilitations du personnel
            if ($routeNormalisee === 'admin.personnel.index' && $request->query('tab') === 'habilitations') {
                $cleHabilitation = 'gestion_habilitations';
            }

            if (! $utilisateur->aHabilitation($cleHabilitation)) {
                abort(403, 'Accès refusé. Vous ne disposez pas de l\'habilitation requise pour cette page.');
            }
        }

        return $next($request);
    }
}
