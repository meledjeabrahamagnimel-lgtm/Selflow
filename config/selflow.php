<?php

/**
 * Configuration des liaisons externes Selflow.
 * Ces valeurs sont surchargées par les variables d'environnement du fichier .env.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | URL de l'API COMPTAFLOW
    |--------------------------------------------------------------------------
    | URL de base de l'application COMPTAFLOW pour les appels API inter-apps.
    | En local : http://127.0.0.1:8002  (selon le port de php artisan serve)
    | En production : https://comptaflow.example.com
    */
    'comptaflow_api_url' => env('COMPTAFLOW_API_URL', 'http://127.0.0.1:8000'),

    /*
    |--------------------------------------------------------------------------
    | Secret partagé API
    |--------------------------------------------------------------------------
    | Clé secrète partagée entre Selflow et COMPTAFLOW pour authentifier
    | les appels API locaux. Doit être identique dans les deux .env.
    */
    'comptaflow_api_secret' => env('EXTERNAL_SYNC_SECRET', 'selflow-comptaflow-secret-2026'),

    /*
    |--------------------------------------------------------------------------
    | Timeout des requêtes HTTP sortantes (secondes)
    |--------------------------------------------------------------------------
    */
    'api_timeout' => env('EXTERNAL_API_TIMEOUT', 15),

    /*
    |--------------------------------------------------------------------------
    | Plan comptable par défaut (SYSCOHADA révisé - Côte d'Ivoire)
    |--------------------------------------------------------------------------
    | Comptes génériques utilisés en fallback par ComptabiliteService lorsque
    | l'entreprise n'a pas paramétré de compte spécifique. Centralisés ici
    | pour éviter les valeurs codées en dur dispersées dans le code métier :
    | un seul endroit à modifier pour changer la convention de numérotation.
    |
    | Convention : racine SYSCOHADA sans subdivision par défaut (PPP000).
    | Une entreprise qui veut des sous-comptes plus fins (ex: 401110 par
    | fournisseur type) peut le faire via son propre plan comptable, ces
    | valeurs ne servent que de filet de sécurité.
    */
    'plan_comptable_defaut' => [
        'client_collectif'      => '411000', // Clients
        'fournisseur_collectif' => '401000', // Fournisseurs
        'vente_defaut'          => '701000', // Ventes (compte générique si le produit n'a pas de compte dédié)
        'achat_defaut'          => '601000', // Achats (compte générique si le produit n'a pas de compte dédié)
        'tva_collectee'         => '443100', // État, TVA facturée sur ventes
        'tva_deductible'        => '445200', // État, TVA déductible sur achats
        'caisse'                => '571000', // Caisse
        'banque_defaut'         => '521000', // Banque (si aucun journal banque dédié trouvé)
    ],

    /*
    |--------------------------------------------------------------------------
    | FNE — Facture Normalisée Électronique (DGI Côte d'Ivoire)
    |--------------------------------------------------------------------------
    | Chaque entreprise a sa PROPRE clé (voir table fne_credentials), gérée
    | exclusivement par le superadmin. Ces URLs sont communes à toutes les
    | entreprises (seule la clé change), donc centralisées ici.
    */
    'fne_api_url_sandbox'    => env('FNE_API_URL_SANDBOX', 'https://fne-sandbox.dgi.gouv.ci'),
    'fne_api_url_production' => env('FNE_API_URL_PRODUCTION', 'https://fne.dgi.gouv.ci'),

];
