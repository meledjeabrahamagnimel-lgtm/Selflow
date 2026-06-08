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
            'admin.tableau_de_bord'          => 'tableau_de_bord',
            
            'admin.ventes.nouvelle'          => 'nouvelle_vente',
            'admin.ventes.enregistrer'       => 'nouvelle_vente',
            'admin.ventes.factures'          => 'factures_vente',
            'admin.ventes.historique'        => 'historique_ventes',
            'admin.ventes.imprimer'          => 'factures_vente',
            
            'admin.achats.nouveau'           => 'nouvel_achat',
            'admin.achats.enregistrer'       => 'nouvel_achat',
            'admin.achats.factures'          => 'factures_achat',
            'admin.achats.historique'        => 'historique_achats',
            'admin.achats.imprimer'          => 'factures_achat',
            
            'admin.stock.index'              => 'stock_articles',
            'admin.stock.mouvements'         => 'stock_mouvements',
            
            'admin.tresorerie.encaissements' => 'tresorerie_encaissements',
            'admin.tresorerie.decaissements' => 'tresorerie_decaissements',
            'admin.tresorerie.journal'       => 'tresorerie_journal',
            'admin.tresorerie.codes_journaux' => 'tresorerie_codes_journaux',
            'admin.tresorerie.creer_code_journal' => 'tresorerie_codes_journaux',
            'admin.tresorerie.supprimer_code_journal' => 'tresorerie_codes_journaux',
            'admin.banques.creer'            => 'nouvelle_vente',
            
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
            
            'admin.produits.index'           => 'catalogue_produits',
            'admin.produits.creer'           => 'catalogue_produits',
            'admin.produits.modifier'        => 'catalogue_produits',
            
            'admin.clients.index'            => 'tiers_clients',
            'admin.clients.creer'            => 'tiers_clients',
            
            'admin.fournisseurs.index'       => 'tiers_fournisseurs',
            'admin.fournisseurs.creer'       => 'tiers_fournisseurs',
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
