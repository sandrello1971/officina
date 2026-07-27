<?php

namespace App\Jobs;

use App\Services\News\AiNewsFetcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * News AI — esegue il recupero settimanale in modo ASINCRONO (web_search + parsing sono
 * lenti). Salva solo BOZZE: la pubblicazione ai discenti resta HITL (revisione admin).
 */
class FetchAiNewsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;      // fetch AI costoso: niente retry in loop
    public int $timeout = 600;  // web_search + più chiamate: minuti

    public function handle(AiNewsFetcher $fetcher): void
    {
        try {
            $run = $fetcher->run();
            Log::info('[FetchAiNewsJob] rassegna recuperata', [
                'run_id' => $run->id, 'items_found' => $run->items_found,
            ]);
        } catch (\Throwable $e) {
            // La run è già registrata 'failed' dal fetcher.
            Log::warning('[FetchAiNewsJob] recupero non completato', ['error' => $e->getMessage()]);
        }
    }
}
