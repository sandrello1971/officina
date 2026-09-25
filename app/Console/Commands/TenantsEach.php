<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Esegue un comando artisan nel contesto di ogni ente attivo, uno alla volta.
 * Con --module salta gli enti che non hanno quel modulo. Un errore su un ente
 * viene registrato e non ferma gli altri.
 *
 *   php artisan tenants:each freshness:run-due --module=freshness
 */
class TenantsEach extends Command
{
    protected $signature = 'tenants:each {cmd : Comando da eseguire} {--module= : Solo gli enti con questo modulo}';

    protected $description = 'Esegue un comando per ogni ente attivo (con filtro opzionale sul modulo).';

    public function handle(): int
    {
        $command = (string) $this->argument('cmd');
        $module = $this->option('module');
        $failed = 0;

        foreach (Tenant::query()->where('status', 'active')->orderBy('name')->get() as $tenant) {
            if ($module && ! $tenant->hasModule($module)) {
                continue;
            }

            $this->line("<info>[{$tenant->slug}]</info> {$command}");
            try {
                $tenant->run(function () use ($command) {
                    Artisan::call($command, [], $this->output);
                });
            } catch (\Throwable $e) {
                $failed++;
                report($e);
                $this->error("[{$tenant->slug}] {$e->getMessage()}");
            }
        }

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
