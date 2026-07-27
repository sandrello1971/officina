<?php

namespace App\Console\Commands;

use App\Jobs\RunFreshnessAgentJob;
use App\Models\CourseFreshnessConfig;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * P25.3d — Scheduler: lancia i freshness-run sui corsi la cui cadenza è SCADUTA.
 *
 * Solo dispatch dei job (estrazione + verifica + proposte). NON applica nulla.
 * CAP per esecuzione (default 5) per evitare bollette a sorpresa: la Fase 2 usa Opus 4.8
 * + web_search (1 chiamata/claim). I corsi oltre il cap vengono rimandati al tick
 * successivo (loggato, mai silenziato). Corsi con cadenza 'off' → ignorati.
 */
class RunDueFreshnessChecks extends Command
{
    protected $signature = 'freshness:run-due {--limit= : Cap di corsi per esecuzione (default 5)}';

    protected $description = 'P25.3d — Lancia i controlli di aggiornamento sui corsi con cadenza scaduta (cap per run).';

    private const DEFAULT_CAP = 5;

    public function handle(): int
    {
        // Interruttore globale di costo (Impostazioni admin): finché OFF, lo scheduler NON
        // lancia alcun run automatico (nessuna spesa API). Il "run now" manuale dell'admin
        // resta comunque disponibile per test puntuali. Default: OFF.
        if ((string) atheneum_setting('freshness_auto_enabled', '0') !== '1') {
            $this->info('freshness:run-due — monitoraggio automatico DISABILITATO (Impostazioni → freshness_auto_enabled). Nessun run lanciato.');
            return self::SUCCESS;
        }

        $cap = (int) ($this->option('limit') ?: self::DEFAULT_CAP);

        $due = CourseFreshnessConfig::where('cadence', '!=', 'off')
            ->get()
            ->filter(fn (CourseFreshnessConfig $c) => $this->isDue($c))
            // Più vecchi (o mai eseguiti) per primi.
            ->sortBy(fn (CourseFreshnessConfig $c) => $c->last_run_at?->timestamp ?? 0)
            ->values();

        $toRun = $due->take($cap);
        foreach ($toRun as $config) {
            RunFreshnessAgentJob::dispatch($config->course_id);
        }

        $dropped = $due->count() - $toRun->count();
        $this->info("freshness:run-due — scaduti: {$due->count()}, lanciati: {$toRun->count()}, rimandati (cap {$cap}): {$dropped}");
        if ($dropped > 0) {
            Log::info("[freshness:run-due] {$dropped} corsi oltre il cap {$cap}, rimandati al prossimo tick.");
        }

        return self::SUCCESS;
    }

    /** Un corso è scaduto se non è mai stato eseguito o se è passato l'intervallo di cadenza. */
    private function isDue(CourseFreshnessConfig $config): bool
    {
        if ($config->last_run_at === null) {
            return true;
        }

        $threshold = match ($config->cadence) {
            'weekly' => now()->subWeek(),
            'monthly' => now()->subMonth(),
            'quarterly' => now()->subMonths(3),
            default => null, // 'off' già filtrato
        };

        return $threshold !== null && $config->last_run_at->lte($threshold);
    }
}
