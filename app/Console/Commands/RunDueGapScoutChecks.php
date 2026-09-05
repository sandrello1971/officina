<?php

namespace App\Console\Commands;

use App\Jobs\RunGapScoutJob;
use App\Models\CourseFreshnessConfig;
use App\Models\GapScoutRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * P26.3 — Scheduler: lancia lo Scout di copertura sui corsi la cui gap_cadence è SCADUTA.
 * Gemello di RunDueFreshnessChecks (freshness:run-due), stessa disciplina di costo: interruttore
 * globale, cap per esecuzione, corsi con cadenza 'off' ignorati, run già 'running' saltate.
 *
 * Solo dispatch dei job (scout → coverage_gaps 'suggested'). NON applica nulla: l'inserimento nel
 * corso resta un'azione admin esplicita in più fasi (accetta → genera bozza → posiziona → inserisci).
 */
class RunDueGapScoutChecks extends Command
{
    protected $signature = 'gap:scout-run-due {--limit= : Cap di corsi per esecuzione (default 5)}';

    protected $description = 'P26.3 — Lancia lo Scout di copertura sui corsi con gap_cadence scaduta (cap per run).';

    private const DEFAULT_CAP = 5;

    public function handle(): int
    {
        // Interruttore globale di costo (Impostazioni admin): finché OFF, lo scheduler NON
        // lancia alcun run automatico (nessuna spesa API). Il pulsante "Analizza" manuale
        // in /admin/copertura resta comunque disponibile. Default: OFF.
        if ((string) atheneum_setting('gap_scout_auto_enabled', '0') !== '1') {
            $this->info('gap:scout-run-due — Scout di copertura automatico DISABILITATO (Impostazioni → gap_scout_auto_enabled). Nessun run lanciato.');
            return self::SUCCESS;
        }

        $cap = (int) ($this->option('limit') ?: self::DEFAULT_CAP);

        $due = CourseFreshnessConfig::where('gap_cadence', '!=', 'off')
            ->get()
            ->filter(fn (CourseFreshnessConfig $c) => $this->isDue($c))
            // Più vecchi (o mai eseguiti) per primi.
            ->sortBy(fn (CourseFreshnessConfig $c) => $c->gap_last_run_at?->timestamp ?? 0)
            ->values();

        // Salta i corsi con uno scout già 'running': evita di impilarne un altro sopra.
        $runningCourseIds = GapScoutRun::where('status', 'running')->pluck('course_id')->all();
        $eligible = $due->reject(fn (CourseFreshnessConfig $c) => in_array($c->course_id, $runningCourseIds, true));
        $skippedRunning = $due->count() - $eligible->count();

        $toRun = $eligible->take($cap);
        foreach ($toRun as $config) {
            RunGapScoutJob::dispatch($config->course_id);
        }

        $dropped = $eligible->count() - $toRun->count();
        $this->info("gap:scout-run-due — scaduti: {$due->count()}, già in corso (saltati): {$skippedRunning}, lanciati: {$toRun->count()}, rimandati (cap {$cap}): {$dropped}");
        if ($dropped > 0) {
            Log::info("[gap:scout-run-due] {$dropped} corsi oltre il cap {$cap}, rimandati al prossimo tick.");
        }

        return self::SUCCESS;
    }

    /** Un corso è scaduto se non è mai stato eseguito o se è passato l'intervallo di cadenza. */
    private function isDue(CourseFreshnessConfig $config): bool
    {
        if ($config->gap_last_run_at === null) {
            return true;
        }

        $threshold = match ($config->gap_cadence) {
            'weekly' => now()->subWeek(),
            'monthly' => now()->subMonth(),
            'quarterly' => now()->subMonths(3),
            default => null, // 'off' già filtrato
        };

        return $threshold !== null && $config->gap_last_run_at->lte($threshold);
    }
}
