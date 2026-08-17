<?php

namespace App\Jobs;

use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Services\Freshness\FreshnessAgent;
use App\Services\Freshness\ModuleSourceSync;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * P25.3d/P25.4 — Avvia l'agente (Fase 1: estrazione) e distribuisce la Fase 2 (verifica)
 * su un job per claim (`VerifyFreshnessClaimJob`), così un singolo claim lento non fa
 * scadere l'intera run. Questo job fa SOLO: source sync + estrazione (una chiamata AI) +
 * dispatch dei job di verifica — niente più loop di verifica qui dentro, quindi il timeout
 * può restare basso.
 *
 * SOLO generazione → proposte `pending` nella coda. NON applica nulla (l'applicazione
 * resta HITL manuale, P25.3c). Aggiorna `last_run_at` per lo scheduler.
 */
class RunFreshnessAgentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;       // un run fallito non si ritenta in loop (API costose)
    public int $timeout = 450;   // copre source sync + 1 chiamata di estrazione (fino a ~370s nel caso peggiore) + dispatch

    public function __construct(public string $courseId) {}

    public function handle(FreshnessAgent $agent, ModuleSourceSync $sourceSync): void
    {
        $course = Course::find($this->courseId);
        if (!$course) {
            Log::warning('[RunFreshnessAgentJob] corso inesistente', ['course_id' => $this->courseId]);
            return;
        }

        try {
            // Corsi senza manuale: allinea il sorgente ai moduli (staleness + auto-provisioning)
            // PRIMA del run. Additivo e non bloccante: se fallisce, l'agente procede/fallisce
            // in modo pulito sul sorgente esistente.
            try {
                if ($created = $sourceSync->ensureFresh($course)) {
                    Log::info('[RunFreshnessAgentJob] sorgente riallineato dai moduli', [
                        'course_id' => $course->id, 'source_id' => $created->id, 'version' => $created->version,
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('[RunFreshnessAgentJob] sync sorgente da moduli fallito', [
                    'course_id' => $course->id, 'error' => $e->getMessage(),
                ]);
            }

            $agent->startAsync($course);
        } catch (\Throwable $e) {
            // Es. nessun course_sources: la run è già registrata 'failed' dall'agente.
            Log::warning('[RunFreshnessAgentJob] avvio run non riuscito', [
                'course_id' => $course->id, 'error' => $e->getMessage(),
            ]);
        } finally {
            // Marca il tentativo per lo scheduler (anche in caso di fallimento, per non
            // ritentare a ogni tick). Segna l'AVVIO, non più il completamento: la verifica
            // ora prosegue sui job per-claim dopo che questo job è già tornato.
            CourseFreshnessConfig::updateOrCreate(
                ['course_id' => $course->id],
                ['last_run_at' => now()]
            );
        }
    }
}
