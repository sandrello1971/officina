<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Atheneum Admin Whitelist
    |--------------------------------------------------------------------------
    | Email autorizzate ad accedere ad /admin via SSO Microsoft.
    | Per aggiungere nuovi admin: aggiungi l'email a questo array,
    | l'utente deve comunque avere account Microsoft nel tenant Noscite.
    | Nessun record DB: l'accesso avviene solo via session keys
    | (admin_logged_in, admin_email) identiche al login email+password.
    */
    'admins' => [
        'sandrello@noscite.it',
    ],

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
