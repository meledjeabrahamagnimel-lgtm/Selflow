<?php

use Illuminate\Support\Facades\Route;

/*
 * La racine est déclarée par le module d'authentification, sous le nom
 * `accueil` : elle conduit chacun à son espace selon son rôle.
 *
 * Elle l'était **aussi** ici, sans condition, et ce fichier étant chargé le
 * premier, c'est cette version-ci qui répondait : tout utilisateur connecté
 * atteignant `/` était renvoyé vers `connexion`, page réservée aux visiteurs,
 * qui le renvoyait à la racine. Le navigateur s'arrêtait au bout de vingt
 * allers-retours sur `ERR_TOO_MANY_REDIRECTS`.
 *
 * Voir `app/Modules/Authentification/Routes/web.php`.
 */

