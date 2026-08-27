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
    | Legal Representative Email — SEED DI BACKFILL (storico)
    |--------------------------------------------------------------------------
    | L'abilitazione alla firma dei certificati NON è più legata a questa
    | singola email: è ora un privilegio per-account in DB
    | (admins.can_sign_certificates), gestibile da più amministratori dalla
    | UI /admin/admins (AdminAccountController::signature + middleware
    | EnsureLegalRepresentative).
    |
    | Questo valore resta solo come seed: la migrazione che ha introdotto la
    | colonna lo usa per marcare firmatario il legale rappresentante storico,
    | evitando di lasciare la piattaforma senza nessun firmatario al deploy.
    | A migrazione applicata in tutti gli ambienti, è rimovibile.
    */
    'legal_representative_email' => env('LEGAL_REPRESENTATIVE_EMAIL', 'glitch@effettoglitch.it'),

    /*
    |--------------------------------------------------------------------------
    | Copyright — dicitura di tutela del diritto d'autore
    |--------------------------------------------------------------------------
    | Dicitura UNICA di piattaforma: stampata su OGNI contenuto prodotto —
    | PDF e dispense (CourseSourcePdfBuilder), attestati (CertificatePdfBuilder),
    | slide e video (resources/python/build_pptx.py) e ogni pagina a schermo
    | (layouts.partials._copyright). Nessun testo di copyright va scritto a mano
    | altrove: si legge SEMPRE da copyright_notice().
    |
    | L'anno NON è scritto qui: lo compone copyright_notice() a ogni render, così
    | non resta indietro. Metterlo in config lo congelerebbe al momento del
    | `config:cache` (è così che il sito pubblico è rimasto al "© 2025").
    */
    'copyright_holder' => env('ATHENEUM_COPYRIGHT_HOLDER', 'Stefano Domenico Andrello'),

    /*
    | Override completo della dicitura, anno compreso. Vuoto = si compone dal
    | titolare qui sopra. Serve solo per casi eccezionali (es. una variante
    | legale imposta): valorizzandolo si perde l'aggiornamento automatico
    | dell'anno.
    */
    'copyright' => env('ATHENEUM_COPYRIGHT', ''),
];
