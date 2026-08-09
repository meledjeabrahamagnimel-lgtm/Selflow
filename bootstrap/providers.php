<?php

use App\Providers\AppServiceProvider;
use App\Providers\LimitesDeDebit;
use App\Modules\FournisseurDeServicesModules;

return [
    AppServiceProvider::class,
    // Les limiteurs doivent exister avant que les routes ne s'y réfèrent :
    // `throttle:api` sur une route dont le limiteur n'est pas déclaré lève une
    // erreur au premier appel, non au démarrage.
    LimitesDeDebit::class,
    FournisseurDeServicesModules::class,
];

