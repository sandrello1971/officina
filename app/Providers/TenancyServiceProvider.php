<?php

namespace App\Providers;

use App\Jobs\Tenancy\DeleteTenantDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events;
use Stancl\Tenancy\Jobs;
use Stancl\Tenancy\Listeners;
use Stancl\Tenancy\Middleware;

class TenancyServiceProvider extends ServiceProvider
{
    public function events(): array
    {
        return [
            Events\TenantCreated::class => [
                JobPipeline::make([
                    Jobs\CreateDatabase::class,
                    Jobs\MigrateDatabase::class,
                ])->send(fn (Events\TenantCreated $event) => $event->tenant)->shouldBeQueued(false),
            ],
            Events\TenantDeleted::class => [
                // Non droppa i DB registrati (tenant zero).
                JobPipeline::make([
                    DeleteTenantDatabase::class,
                ])->send(fn (Events\TenantDeleted $event) => $event->tenant)->shouldBeQueued(false),
            ],

            Events\TenancyInitialized::class => [
                Listeners\BootstrapTenancy::class,
            ],
            Events\TenancyEnded::class => [
                Listeners\RevertToCentralContext::class,
            ],

            // Config dipendente dall'ente (SMTP, chiavi API, host degli URL):
            // riapplicata a ogni ingresso/uscita, anche nei worker di coda.
            Events\TenancyBootstrapped::class => [
                fn () => \App\Support\TenantConfig::apply(),
            ],
            Events\RevertedToCentralContext::class => [
                fn () => \App\Support\TenantConfig::apply(),
            ],
        ];
    }

    public function boot(): void
    {
        // Nei test un solo DB fa da central e da tenant: migrate:fresh (via
        // RefreshDatabase) deve vedere anche le migration dei tenant. Fuori dai
        // test NO: `migrate` tocca solo il central, i tenant passano da tenants:migrate.
        if ($this->app->environment('testing')) {
            $this->loadMigrationsFrom(database_path('migrations/tenant'));
        }

        // {tenant_host} di Route::domain('admin.{tenant_host}') contiene punti.
        \Illuminate\Support\Facades\Route::pattern('tenant_host', '[a-z0-9.-]+');

        foreach ($this->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $listener = $listener->toListener();
                }
                Event::listen($event, $listener);
            }
        }

        foreach (array_reverse([
            Middleware\PreventAccessFromCentralDomains::class,
            Middleware\InitializeTenancyByDomain::class,
        ]) as $middleware) {
            $this->app[\Illuminate\Contracts\Http\Kernel::class]->prependToMiddlewarePriority($middleware);
        }
    }
}
