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

];
