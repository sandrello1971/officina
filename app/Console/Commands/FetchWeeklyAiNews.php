<?php

namespace App\Console\Commands;

use App\Jobs\FetchAiNewsJob;
use Illuminate\Console\Command;

/**
 * News AI — scheduler settimanale. Dispatcha il recupero (web_search) come job async.
 *
 * Interruttore globale di costo (Impostazioni admin, default OFF): finché
 * `ainews_auto_enabled` non è '1' NON parte alcun recupero automatico (nessuna spesa API).
 * Il "Recupera ora" manuale dall'admin resta comunque disponibile. Stesso pattern di
 * `freshness:run-due`.
 */
class FetchWeeklyAiNews extends Command
{
    protected $signature = 'ainews:fetch-weekly';

    protected $description = 'Recupera le news AI della settimana (ricerca online) come bozze da rivedere.';

    public function handle(): int
    {
        if ((string) atheneum_setting('ainews_auto_enabled', '0') !== '1') {
            $this->info('ainews:fetch-weekly — recupero automatico DISABILITATO (Impostazioni → ainews_auto_enabled). Niente eseguito.');
            return self::SUCCESS;
        }

        FetchAiNewsJob::dispatch();
        $this->info('ainews:fetch-weekly — recupero news accodato.');

        return self::SUCCESS;
    }
}
