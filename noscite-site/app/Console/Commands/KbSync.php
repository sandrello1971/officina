<?php

namespace App\Console\Commands;

use App\Services\KbSyncService;
use Illuminate\Console\Command;

class KbSync extends Command
{
    protected $signature = 'kb:sync';

    protected $description = 'Sincronizza il vault Obsidian con il DB';

    public function handle()
    {
        $stats = app(KbSyncService::class)->sync();
        $this->info("Sync completato: {$stats['created']} creati, {$stats['updated']} aggiornati, {$stats['errors']} errori");
    }
}
