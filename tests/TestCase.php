<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Stancl\Tenancy\Bootstrappers\DatabaseTenancyBootstrapper;

abstract class TestCase extends BaseTestCase
{
    /** Ente in cui girano i test: il tenant primario sul DB di test. */
    protected ?Tenant $tenant = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            $this->bootTestTenant();
        }
    }

    protected function tearDown(): void
    {
        if (function_exists('tenancy') && tenancy()->initialized) {
            tenancy()->end();
        }

        parent::tearDown();
    }

    /**
     * Registra il tenant primario sul DB di test e lo inizializza.
     *
     * Nei test central e tenant coincidono col DB di test e con la sua
     * connessione `pgsql`, così la transazione di RefreshDatabase copre anche
     * tenants/domains; per lo stesso motivo il bootstrapper del database è
     * spento (cambierebbe connessione e scriverebbe fuori transazione).
     */
    protected function bootTestTenant(array $attributes = []): Tenant
    {
        config([
            'tenancy.database.central_connection' => 'pgsql',
            'queue.failed.database' => 'pgsql',
            'tenancy.bootstrappers' => array_values(array_diff(
                config('tenancy.bootstrappers'),
                [DatabaseTenancyBootstrapper::class]
            )),
        ]);

        $this->tenant = Tenant::create(array_merge([
            // id fisso: lo storage del tenant è storage/tenant<id>.
            'id' => 'test',
            'name' => 'Effetto Glitch',
            'slug' => 'effettoglitch',
            'status' => 'active',
            'base_host' => config('domains.base'),
            'licensed_modules' => array_keys(config('modules.modules')),
            'ai_key_mode' => Tenant::AI_KEY_BOTH,
            'is_primary' => true,
            'tenancy_db_name' => config('database.connections.pgsql.database'),
            'tenancy_create_database' => false,
        ], $attributes));
        $this->tenant->syncDomains();

        tenancy()->initialize($this->tenant);

        return $this->tenant;
    }

    /**
     * URL assoluta sull'host di amministrazione. Le rotte admin sono vincolate
     * ad admin.* via Route::domain(), quindi un path relativo non le raggiunge.
     */
    protected function adminUrl(string $path = '/'): string
    {
        return 'https://' . config('domains.admin') . '/' . ltrim($path, '/');
    }

    /** URL assoluta sull'host discenti (area learn, docente, scuola). */
    protected function learnUrl(string $path = '/'): string
    {
        return 'https://' . config('domains.learn') . '/' . ltrim($path, '/');
    }

    /** URL assoluta sull'host di vetrina. */
    protected function siteUrl(string $path = '/'): string
    {
        return 'https://' . config('domains.site') . '/' . ltrim($path, '/');
    }
}
