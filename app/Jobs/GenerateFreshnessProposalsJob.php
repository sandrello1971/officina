<?php

namespace App\Jobs;

use App\Models\Course;
use App\Models\FreshnessClaim;
use App\Models\FreshnessRun;
use App\Services\Freshness\FreshnessAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * P25.4 — Fase 3 (proposte) per una run, in un job A PARTE rispetto ai
 * `VerifyFreshnessClaimJob`. Dispatchata da `FreshnessAgent::finishRun()` una volta che
 * TUTTI i claim sono stati verificati.
 *
 * Perché un job a parte e non "genera le proposte dentro l'ultimo VerifyFreshnessClaimJob"
 * (come nella prima versione di P25.4): quel job ha un timeout dimensionato per UNA
 * verifica (~150s). Generare N proposte in sequenza sopra, con chiamate AI dal timeout di
 * default, poteva far scadere ESATTAMENTE lo stesso identico bug che P25.4 doveva
 * risolvere — solo spostato dalla Fase 2 alla Fase 3. Qui il timeout è dimensionato per
 * la Fase 3 e basta.
 */
class GenerateFreshnessProposalsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;       // un run fallito non si ritenta in loop (API costose)
    public int $timeout = 600;   // N proposte in sequenza, ognuna fino a ~120s nel caso peggiore

    public function __construct(public string $runId) {}

    public function handle(FreshnessAgent $agent): void
    {
        $run = FreshnessRun::find($this->runId);
        if (!$run) {
            Log::warning('[GenerateFreshnessProposalsJob] run inesistente', ['run_id' => $this->runId]);
            return;
        }

        $course = Course::find($run->course_id);
        if (!$course) {
            $run->update(['status' => 'failed', 'failure_reason' => 'Corso non trovato in fase di generazione proposte.']);
            return;
        }
        $config = $agent->configFor($course);

        $obsoleteInstructor = FreshnessClaim::where('run_id', $run->id)
            ->where('content_source', 'instructor')->where('verdict', 'obsoleto')->get()->all();
        $obsoleteStudent = FreshnessClaim::where('run_id', $run->id)
            ->where('content_source', 'student')->where('verdict', 'obsoleto')->get()->all();

        $proposalsCreated = 0;
        if ($config->proposals_enabled) {
            $proposalsCreated += $agent->generateProposals($run, $course, $config, $obsoleteInstructor);
        }
        if ($config->student_proposals_enabled) {
            $proposalsCreated += $agent->generateProposals($run, $course, $config, $obsoleteStudent);
        }

        $run->update(['status' => 'completed', 'proposals_created' => $proposalsCreated]);
    }

    /**
     * Se il job viene ucciso dal proprio timeout (o supera i tentativi), Laravel chiama
     * questo hook PRIMA di terminare il processo: senza, la run resterebbe bloccata su
     * 'running' per sempre, esattamente il bug che P25.4 doveva eliminare — solo spostato
     * qui invece che nella verifica.
     */
    public function failed(\Throwable $exception): void
    {
        FreshnessRun::where('id', $this->runId)->update([
            'status' => 'failed',
            'failure_reason' => 'Generazione proposte non completata: ' . $exception->getMessage(),
        ]);
    }
}
