<?php

return [
    // Host della console di piattaforma (fuori da ogni ente). Va anche in
    // DNS/nginx/certificato; è trattato come dominio central.
    'domain' => env('PLATFORM_DOMAIN', 'platform.officina.effettoglitch.it'),
];
