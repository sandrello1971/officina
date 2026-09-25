<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Tenancy\TenantProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Registra un DATABASE ESISTENTE come ente, senza clonarlo né toccarne i dati.
 * Serve per il tenant zero: l'installazione attuale di Effetto Glitch.
 *
 *   php artisan tenant:register-existing --name="Effetto Glitch" --slug=effettoglitch \
 *       --db=atheneum_db --host=officina.effettoglitch.it --primary --modules=all
 */
class TenantRegisterExisting extends Command
{
    protected $signature = 'tenant:register-existing
        {--name= : Nome dell\'ente}
        {--slug= : Slug (default: dal nome)}
        {--db= : Database esistente da agganciare}
        {--host= : Host base B (admin.B / learn.B)}
        {--primary : Tenant primario (Effetto Glitch): può avere il modulo Scuola}
        {--modules=all : CSV moduli oppure "all"}';

    protected $description = 'Registra un database esistente come ente (tenant zero), senza clonare né seminare.';

    public function handle(TenantProvisioner $provisioner): int
    {
        $name = trim((string) $this->option('name'));
        $db = trim((string) $this->option('db'));

        try {
            $host = TenantProvisioner::normalizeHost((string) $this->option('host'));
            if ($name === '' || $db === '' || $host === '') {
                throw new \InvalidArgumentException('Obbligatori: --name, --db, --host.');
            }
            $slug = Str::slug((string) ($this->option('slug') ?: $name));
            $provisioner->assertAvailable($slug, $host);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $primary = (bool) $this->option('primary');
        if ($primary && Tenant::query()->get()->contains(fn (Tenant $t) => $t->isPrimary())) {
            $this->error('Esiste già un tenant primario.');

            return self::FAILURE;
        }

        $modulesOpt = (string) $this->option('modules');
        $modules = $modulesOpt === 'all' ? array_keys(config('modules.modules')) : explode(',', $modulesOpt);

        // tenancy_create_database=false ferma la pipeline: niente clone, niente migrate.
        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'base_host' => $host,
            'licensed_modules' => TenantProvisioner::sanitizeModules($modules, $primary),
            'ai_key_mode' => Tenant::AI_KEY_BOTH,
            'is_primary' => $primary,
            'tenancy_db_name' => $db,
            'tenancy_create_database' => false,
        ]);
        $tenant->syncDomains();

        $this->info("✔ Ente '{$slug}' registrato sul DB esistente '{$db}' (" . ($primary ? 'primario' : 'secondario') . ').');
        $this->line("  domini: {$tenant->adminHost()}, {$tenant->learnHost()}");
        $this->warn("  Storage: collega storage/tenant{$tenant->id}/{app,framework,logs} alle cartelle esistenti (vedi docs/MULTI_TENANT_DEPLOY.md).");

        return self::SUCCESS;
    }
}
