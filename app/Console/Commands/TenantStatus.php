<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class TenantStatus extends Command
{
    protected $signature = 'tenant:status {tenant : id o slug} {status : active | suspended}';

    protected $description = 'Sospende o riattiva un ente.';

    public function handle(): int
    {
        $status = $this->argument('status');
        if (! in_array($status, ['active', 'suspended'], true)) {
            $this->error('Stato ammesso: active | suspended.');

            return self::FAILURE;
        }
        $tenant = Tenant::query()->where('id', $this->argument('tenant'))
            ->orWhere('slug', $this->argument('tenant'))->first();
        if (! $tenant) {
            $this->error('Ente non trovato.');

            return self::FAILURE;
        }
        $tenant->update(['status' => $status]);
        $this->info("✔ {$tenant->slug}: {$status}");

        return self::SUCCESS;
    }
}
