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
    // Secret partage avec Comptaflow. Aucune valeur de repli : un secret ecrit
    // dans le code est un secret public des que le depot l'est, et ces routes
    // creent des entreprises. Sans EXTERNAL_SYNC_SECRET, la synchronisation
    // refuse tout appel plutot que d'en accepter avec un secret connu de tous.
    'comptaflow_api_secret' => env('EXTERNAL_SYNC_SECRET'),

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
    /*
    |--------------------------------------------------------------------------
    | Stickers FNE
    |--------------------------------------------------------------------------
    |
    | Prix unitaire d'un sticker de certification, en francs CFA. La plateforme
    | ne le transmet nulle part : elle ne renvoie qu'un nombre de vignettes
    | restantes. Ce tarif sert uniquement a convertir un solde en valeur pour
    | l'alerte de reapprovisionnement — il n'entre dans aucun montant de
    | facture. A ajuster ici si la DGI le revise, plutot qu'en dur dans le code.
    |
    */
    'sticker_prix_unitaire' => env('SELFLOW_STICKER_PRIX', 20),

    /*
    | Seuil d'alerte par defaut, en nombre de stickers. Chaque entreprise peut
    | le redefinir dans ses parametres (`entreprises.sticker_solde_alerte`).
    */
    'sticker_seuil_alerte_defaut' => env('SELFLOW_STICKER_SEUIL', 5),

    'plan_comptable_defaut' => [
        'client_collectif'      => '411000', // Clients
        'fournisseur_collectif' => '401000', // Fournisseurs
        'vente_defaut'          => '701000', // Ventes (compte générique si le produit n'a pas de compte dédié)
        'achat_defaut'          => '601000', // Achats (compte générique si le produit n'a pas de compte dédié)
        'tva_collectee'         => '443100', // État, TVA facturée sur ventes
        'tva_deductible'        => '445200', // État, TVA déductible sur achats
        // Taxes parafiscales collectées pour le compte de l'État (GRA, AIRSI,
        // DTD…) : une dette envers l'État, jamais du chiffre d'affaires.
        'taxes_collectees'      => '447000', // État, autres impôts et taxes
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
    /*
    | Adresse de l'environnement de TEST, telle que publiee par la DGI dans la
    | procedure d'interfacage : « URL test : http://54.247.95.108/ws ». La
    | valeur inscrite ici auparavant, https://fne-sandbox.dgi.gouv.ci, ne
    | figure dans aucun document — un environnement neuf, sans variable
    | d'environnement, appelait donc un hote inexistant.
    */
    'fne_api_url_sandbox'    => env('FNE_API_URL_SANDBOX', 'http://54.247.95.108/ws'),

    /*
    | L'adresse de PRODUCTION n'est pas publique : la DGI la transmet a chaque
    | entreprise apres validation de ses specimens de factures. Elle doit donc
    | etre renseignee dans l'environnement, faute de quoi la normalisation
    | continuerait de viser le bac a sable sans le dire.
    */
    'fne_api_url_production' => env('FNE_API_URL_PRODUCTION', ''),

    /*
    |--------------------------------------------------------------------------
    | Relevés du portail FNE
    |--------------------------------------------------------------------------
    | Dossier où sont déposés les relevés du portail de la DGI, nommés
    | `<login>_<date>.json` (fiche entreprise) et `<login>_<date>.xlsx` (points
    | de facturation). `ImportPortailFneService` le parcourt, range ce qu'il y
    | lit, et ne touche à rien d'autre : ces relevés sont un constat, pas un
    | paramétrage.
    |
    | Ces fichiers portent des données fiscales nominatives. Le dossier par
    | défaut est sous `storage/app`, hors de `public/` : un dossier servi par
    | le serveur web les exposerait à qui en devine le nom.
    */
    'portail_fne' => [
        'dossier_import' => env('PORTAIL_FNE_DOSSIER_IMPORT', storage_path('app/portail-fne')),

        /*
        | Au-delà de ce délai, une demande de relevé n'attend plus : elle traîne.
        | Vingt-quatre heures parce qu'un relevé se produit au mieux une fois par
        | jour ; passer la journée sans réponse veut dire que le scraper ne
        | tourne pas, que le dépôt se fait ailleurs, ou que le login est faux.
        | Aucune de ces trois causes ne se corrige toute seule.
        */
        'delai_alerte_heures' => (int) env('PORTAIL_FNE_DELAI_ALERTE_HEURES', 24),
    ],

];
