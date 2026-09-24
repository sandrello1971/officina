<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;

class TenantList extends Command
{
    protected $signature = 'tenant:list';

    protected $description = 'Elenca gli enti registrati.';

    public function handle(): int
    {
        $this->table(
            ['slug', 'nome', 'stato', 'host base', 'DB', 'moduli', 'AI'],
            Tenant::query()->orderBy('name')->get()->map(fn (Tenant $t) => [
                $t->slug . ($t->isPrimary() ? ' ★' : ''),
                $t->name,
                $t->status,
                $t->base_host,
                $t->database()->getName(),
                implode(',', (array) $t->licensed_modules),
                $t->ai_key_mode,
            ])
        );

        return self::SUCCESS;
    }
}
