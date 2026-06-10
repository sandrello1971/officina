<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Officina Admin Whitelist — DEPRECATO (P1.2)
    |--------------------------------------------------------------------------
    | La whitelist e' ora gestita via DB nella tabella admins, modificabile
    | dalla UI /admin/admins (AdminAccountController). Questo array vuoto
    | resta come rete di sicurezza: se qualche codice legacy lo consulta
    | ancora, ritorna [] invece di esplodere. Da rimuovere completamente
    | quando saremo certi che nessun altro legge questa chiave.
    */
    'admins' => [],

    /*
    |--------------------------------------------------------------------------
    | Legal Representative Email
    |--------------------------------------------------------------------------
    | Email dell'amministratore autorizzato a firmare digitalmente i
    | certificati emessi dalla piattaforma. Solo l'admin loggato con
    | questa email può accedere all'admin UI di firma certificati.
    |
    | Sottoinsieme della whitelist 'admins': il legale rappresentante è
    | un admin a tutti gli effetti, ma con il privilegio aggiuntivo di
    | firmare. In futuro, se più admin dovranno poter firmare, sostituire
    | con un array e aggiornare il middleware EnsureLegalRepresentative.
    */
    'legal_representative_email' => env('LEGAL_REPRESENTATIVE_EMAIL', 'sandrello@noscite.it'),
];
