<?php

namespace App\Services\Freshness;

use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\CourseSource;
use App\Models\FreshnessClaim;
use App\Models\FreshnessRun;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * P25.2 — Orchestratore dell'agente (fasi 1-2).
 *
 * Crea una run → carica l'ULTIMO course_sources del corso (o la versione richiesta) →
 * Fase 1 estrae e PERSISTE i claim → Fase 2 verifica e aggiorna ogni claim → chiude la
 * run. LEGGE il sorgente, non lo modifica MAI. `proposals_created` resta 0 (le proposte
 * sono P25.3). Aggancio per course_id interno.
 */
class FreshnessAgent
{
    public function __construct(
        private FreshnessClaimExtractor $extractor,
        private FreshnessVerifier $verifier,
    ) {}

    public function run(Course $course, ?string $version = null): FreshnessRun
    {
        $run = FreshnessRun::create([
            'course_id' => $course->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $source = $this->loadSource($course, $version);
            $config = $course->freshnessConfig ?? new CourseFreshnessConfig([
                'web_search_enabled' => true,
                'primary_sources' => [],
            ]);

            // Fase 1 — estrazione + persistenza dei claim.
            $extracted = $this->extractor->extract($source->blocks ?? []);

            foreach ($extracted['claims'] as $c) {
                $claim = FreshnessClaim::create([
                    'run_id' => $run->id,
                    'course_id' => $course->id,
                    'block_id' => $c['block_id'],
                    'sentence_ref' => $c['sentence_ref'],
                    'claim_text' => $c['claim_text'],
                    'category' => $c['category'],
                ]);

                // Fase 2 — verifica. Resiliente: un errore su un claim non ferma la run.
                try {
                    $v = $this->verifier->verify($claim->claim_text, $claim->category, $config);
                    $claim->update([
                        'verdict' => $v['verdict'],
                        'source_url' => $v['source_url'],
                        'source_type' => $v['source_type'],
                        'source_date' => $v['source_date'],
                        'confidence' => $v['confidence'],
                        'verified_at' => now(),
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('[FreshnessAgent] verifica claim fallita, lascio non verificato', [
                        'claim_id' => $claim->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'claims_found' => count($extracted['claims']),
                'proposals_created' => 0, // P25.2 non genera proposte
            ]);
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'failure_reason' => $e->getMessage(),
            ]);
            throw $e;
        }

        return $run->refresh();
    }

    /** Ultimo sorgente (o versione richiesta). Fail pulito se assente. */
    private function loadSource(Course $course, ?string $version): CourseSource
    {
        $query = $course->sources();
        if ($version !== null) {
            $query->where('version', $version);
        }
        $source = $query->first(); // sources() è già orderByDesc(created_at)

        if (!$source) {
            $msg = $version !== null
                ? "Nessun course_sources v{$version} per il corso {$course->id}"
                : "Nessun course_sources per il corso {$course->id}: eseguire prima course:recover-source";
            throw new RuntimeException($msg);
        }

        return $source;
    }
}
