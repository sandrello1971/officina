<?php

namespace App\Console\Commands;

use App\Services\Tenancy\TenantProvisioner;
use Illuminate\Console\Command;

class TenantCreate extends Command
{
    protected $signature = 'tenant:create
        {--name= : Nome dell\'ente}
        {--host= : Host base B (servito su admin.B e learn.B)}
        {--admin-email= : Email del primo amministratore}
        {--admin-name= : Nome del primo amministratore}
        {--slug= : Slug (default: dal nome)}
        {--modules= : CSV moduli (default: config modules.default)}
        {--ai-key-mode=platform : platform | tenant | both}
        {--ai-budget= : Budget mensile USD sulla chiave di piattaforma}';

    protected $description = 'Crea un nuovo ente: DB clonato dal template, domini, impostazioni e primo admin.';

    public function handle(TenantProvisioner $provisioner): int
    {
        $modules = $this->option('modules');

        try {
            $result = $provisioner->provision([
                'name' => (string) $this->option('name'),
                'base_host' => (string) $this->option('host'),
                'admin_email' => (string) $this->option('admin-email'),
                'admin_name' => $this->option('admin-name'),
                'slug' => $this->option('slug'),
                'modules' => $modules !== null ? explode(',', $modules) : config('modules.default'),
                'ai_key_mode' => $this->option('ai-key-mode'),
                'ai_monthly_budget_usd' => $this->option('ai-budget'),
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $tenant = $result['tenant'];
        $this->info("✔ Ente '{$tenant->slug}' creato sul DB {$result['db']}.");
        $this->line("  admin: https://{$tenant->adminHost()}   learn: https://{$tenant->learnHost()}");
        $this->line("  password temporanea admin: {$result['temp_password']}");
        $this->warn("  DNS: record A per {$tenant->adminHost()} e {$tenant->learnHost()} → IP del server;");
        $this->warn('  poi, da root, scripts/tenant-host.sh ' . $tenant->base_host . ' (nginx + certificato).');

        return self::SUCCESS;
    }
}
