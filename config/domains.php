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

    // Host base dell'installazione primaria (Effetto Glitch): default di
    // {tenant_host} quando si generano URL fuori da un tenant (CLI, vetrina).
    // Gli altri enti hanno il proprio base_host sul record tenant.
    'base' => env('APP_BASE_DOMAIN', 'officina.effettoglitch.it'),

    'site' => env('APP_SITE_DOMAIN', 'officina.effettoglitch.it'),

    'admin' => env('APP_ADMIN_DOMAIN', 'admin.officina.effettoglitch.it'),

    'learn' => env('APP_LEARN_DOMAIN', 'learn.officina.effettoglitch.it'),

];
