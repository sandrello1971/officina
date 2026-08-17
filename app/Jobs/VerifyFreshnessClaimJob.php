<?php

namespace App\Jobs;

use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\FreshnessClaim;
use App\Models\FreshnessRun;
use App\Services\Freshness\FreshnessAgent;
use App\Services\Freshness\FreshnessVerifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * P25.4 — Verifica di UN SOLO claim (Fase 2), dispatchato da `FreshnessAgent::startAsync()`.
 *
 * Prima di questo job, `RunFreshnessAgentJob` verificava tutti i claim di un corso in
 * sequenza dentro un unico processo con un solo timeout per l'intera run: un claim lento
 * (retry su 429/529, web_search lenta) mangiava il budget di tutti gli altri e la run
 * restava bloccata su `running` per sempre. Un job per claim isola il rischio: un claim
 * lento o fallito non blocca né gli altri né la chiusura della run.
 *
 * Resiliente come il codice che sostituisce: un errore di verifica lascia il claim con
 * verdict=null (mai un'eccezione che fa fallire il job). L'ultimo claim verificato della
 * run chiude la run via `FreshnessAgent::finishRun()`.
 */
class VerifyFreshnessClaimJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;       // un claim fallito non si ritenta in loop (API costose)
    public int $timeout = 150;   // margine sopra il tetto stretto di FreshnessVerifier (~120s)

    public function __construct(public string $claimId) {}

    public function handle(FreshnessVerifier $verifier, FreshnessAgent $agent): void
    {
        $claim = FreshnessClaim::find($this->claimId);
        if (!$claim) {
            Log::warning('[VerifyFreshnessClaimJob] claim inesistente', ['claim_id' => $this->claimId]);
            return;
        }

        $course = Course::find($claim->course_id);
        $config = $course?->freshnessConfig ?? new CourseFreshnessConfig([
            'web_search_enabled' => true,
            'primary_sources' => [],
            'audience' => 'adult',
            'proposals_enabled' => true,
            'student_proposals_enabled' => false,
        ]);

        try {
            $v = $verifier->verify($claim->claim_text, $claim->category, $config);
            $claim->update([
                'verdict' => $v['verdict'],
                'source_url' => $v['source_url'],
                'source_type' => $v['source_type'],
                'source_date' => $v['source_date'],
                'confidence' => $v['confidence'],
                'verified_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Fallito: nessun verdetto, ma il claim va comunque marcato "tentato" (verified_at)
            // — altrimenti resterebbe indistinguibile da un claim mai processato e la run
            // non si chiuderebbe mai (lo stesso bug che questo job dovrebbe risolvere).
            $claim->update(['verified_at' => now()]);
            Log::warning('[VerifyFreshnessClaimJob] verifica claim fallita, lascio non verificato', [
                'claim_id' => $claim->id,
                'error' => $e->getMessage(),
            ]);
        }

        $this->maybeFinishRun($claim->run_id, $agent);
    }

    /**
     * Se non restano più claim non ancora tentati per la run, la chiude (Fase 3 + status).
     * Il segnale è `verified_at` (settato SIA su successo che su fallimento), non `verdict`
     * (che resta null sui claim falliti "per sempre" — usarlo qui bloccherebbe la run).
     */
    private function maybeFinishRun(string $runId, FreshnessAgent $agent): void
    {
        $stillPending = FreshnessClaim::where('run_id', $runId)->whereNull('verified_at')->exists();
        if ($stillPending) {
            return;
        }

        $run = FreshnessRun::find($runId);
        if ($run && $run->status === 'running') {
            $agent->finishRun($run);
        }
    }
}
