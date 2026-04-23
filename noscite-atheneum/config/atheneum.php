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
];
