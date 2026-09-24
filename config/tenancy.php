<?php

declare(strict_types=1);

use Stancl\Tenancy\Database\Models\Domain;

/*
 * Multi-tenancy Officina: un database Postgres per ente, clonato dal template
 * (TENANT_TEMPLATE_DB). Effetto Glitch è il "tenant zero" registrato sul DB
 * storico (atheneum_db). Pattern ripreso dal CRM.
 */
return [
    'tenant_model' => \App\Models\Tenant::class,
    'id_generator' => Stancl\Tenancy\UUIDGenerator::class,
    'domain_model' => Domain::class,

    // Host serviti fuori da qualsiasi tenant: console di piattaforma e
    // vetrina Effetto Glitch.
    'central_domains' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CENTRAL_DOMAINS', '127.0.0.1,localhost'))
    ))),

    'bootstrappers' => [
        Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\CacheTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\FilesystemTenancyBootstrapper::class,
        Stancl\Tenancy\Bootstrappers\QueueTenancyBootstrapper::class,
    ],

    'database' => [
        'central_connection' => 'central',
        'template_tenant_connection' => 'pgsql',
        'template_db' => env('TENANT_TEMPLATE_DB', 'officina_tpl'),
        'prefix' => 'tenant',
        'suffix' => '',
        'managers' => [
            'pgsql' => App\Tenancy\PostgreSQLTemplateDatabaseManager::class,
        ],
    ],

    'cache' => [
        'tag_base' => 'tenant',
    ],

    'filesystem' => [
        'suffix_base' => 'tenant',
        'disks' => [
            'local',
            'public',
        ],
        'root_override' => [
            'local' => '%storage_path%/app/private/',
            'public' => '%storage_path%/app/public/',
        ],
        'suffix_storage_path' => true,
        // false: asset() deve continuare a servire public/ (Vite, immagini di
        // piattaforma). I file pubblici per-ente passano da rotte dedicate.
        'asset_helper_tenancy' => false,
    ],

    'redis' => [
        'prefix_base' => 'tenant',
        'prefixed_connections' => [],
    ],

    'features' => [],

    // Le rotte tenant sono in routes/web.php (Route::domain con {tenant_host}),
    // non nel routes/tenant.php del pacchetto.
    'routes' => false,

    'migration_parameters' => [
        '--force' => true,
        '--path' => [database_path('migrations/tenant')],
        '--realpath' => true,
    ],

    'seeder_parameters' => [
        '--class' => 'DatabaseSeeder',
    ],
];
