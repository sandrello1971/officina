<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Domini dell'installazione
    |--------------------------------------------------------------------------
    |
    | Officina è servita su tre host distinti:
    |   - site  → vetrina pubblica (oggi ospitata fuori da questa applicazione)
    |   - admin → area di amministrazione della piattaforma
    |   - learn → area discenti, più docente/scuola che condividono la sessione
    |
    | Le rotte sono vincolate a questi host via Route::domain(), quindi ogni
    | modifica qui richiede `php artisan route:clear` (o route:cache) per avere
    | effetto in produzione.
    |
    */

    'site' => env('APP_SITE_DOMAIN', 'officina.effettoglitch.it'),

    'admin' => env('APP_ADMIN_DOMAIN', 'admin.officina.effettoglitch.it'),

    'learn' => env('APP_LEARN_DOMAIN', 'learn.officina.effettoglitch.it'),

];
