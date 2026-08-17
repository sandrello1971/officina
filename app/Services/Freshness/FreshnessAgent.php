<?php

namespace App\Services\Freshness;

use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\CourseSource;
use App\Models\FreshnessClaim;
use App\Models\FreshnessRun;
use App\Models\UpdateProposal;
use App\Jobs\GenerateFreshnessProposalsJob;
use App\Jobs\VerifyFreshnessClaimJob;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * P25.2/P25.3 — Orchestratore dell'agente.
 *
 * Crea una run → carica l'ULTIMO course_sources del corso (o la versione richiesta) →
 * Fase 1 estrae e PERSISTE i claim → Fase 2 verifica e aggiorna ogni claim → Fase 3
 * (P25.3, disattivabile) genera le proposte per i claim obsoleti → chiude la run.
 * LEGGE il sorgente, non lo modifica MAI. Aggancio per course_id interno.
 *
 * HITL: la Fase 3 scrive solo proposte `pending`; nulla viene applicato (l'applicazione
 * è P25.3c e consuma solo `approved`).
 */
class FreshnessAgent
{
    public function __construct(
        private FreshnessClaimExtractor $extractor,
        private FreshnessVerifier $verifier,
        private FreshnessProposalGenerator $generator,
        private StudentClaimExtractor $studentExtractor,
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
            $config = $this->configFor($course);

            // Formatore: estrai E verifica subito, PRIMA di passare allo studente. Se
            // l'estrazione studente fallisce, i claim formatore hanno già un verdetto
            // (non restano orfani senza verifica sotto una run marcata 'failed').
            $instructorClaims = $this->extractInstructorClaims($run, $course, $source);
            $obsolete = [];
            foreach ($instructorClaims as $claim) {
                if ($this->verifyClaim($claim, $config)) {
                    $obsolete[] = $claim;
                }
            }

            $studentClaims = [];
            $studentObsolete = [];
            if ($config->student_proposals_enabled) {
                $studentClaims = $this->extractStudentClaims($run, $course);
                foreach ($studentClaims as $claim) {
                    if ($this->verifyClaim($claim, $config)) {
                        $studentObsolete[] = $claim;
                    }
                }
            }

            // ===== Fase 3 — proposte (D2: formatore e studente indipendenti per toggle). =====
            $proposalsCreated = 0;
            if ($config->proposals_enabled) {
                $proposalsCreated += $this->generateProposals($run, $course, $config, $obsolete);
            }
            if ($config->student_proposals_enabled) {
                $proposalsCreated += $this->generateProposals($run, $course, $config, $studentObsolete);
            }

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'claims_found' => count($instructorClaims) + count($studentClaims),
                'proposals_created' => $proposalsCreated,
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

    /**
     * P25.4 — variante ASINCRONA per job in coda: Fase 1 (estrazione, una sola chiamata AI)
     * resta sincrona qui; la Fase 2 (verifica) viene distribuita su un job per claim
     * (`VerifyFreshnessClaimJob`) così un singolo claim lento non fa scadere l'intera run.
     * L'ultimo job a completarsi chiude la run (Fase 3 + status) via `finishRun()`.
     */
    public function startAsync(Course $course, ?string $version = null): FreshnessRun
    {
        $run = FreshnessRun::create([
            'course_id' => $course->id,
            'status' => 'running',
            'started_at' => now(),
        ]);

        try {
            $source = $this->loadSource($course, $version);
            $config = $this->configFor($course);

            $instructorClaims = $this->extractInstructorClaims($run, $course, $source);
            $studentClaims = $config->student_proposals_enabled ? $this->extractStudentClaims($run, $course) : [];
            $allClaims = [...$instructorClaims, ...$studentClaims];

            $run->update(['claims_found' => count($instructorClaims) + count($studentClaims)]);

            if ($allClaims === []) {
                $this->finishRun($run->fresh());
                return $run->refresh();
            }

            foreach ($allClaims as $claim) {
                VerifyFreshnessClaimJob::dispatch($claim->id);
            }
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

    /**
     * Chiude la verifica di una run avviata con `startAsync()`: chiamata dall'ultimo
     * `VerifyFreshnessClaimJob` della run. NON genera le proposte qui dentro — la Fase 3
     * gira in un job a parte (`GenerateFreshnessProposalsJob`) con il suo tetto, così non
     * eredita il timeout di `VerifyFreshnessClaimJob` (dimensionato per UNA verifica, non
     * per N generazioni di proposte in sequenza sopra ad essa).
     *
     * Idempotente: il lock ottimistico guarda E scrive LA STESSA colonna (`finished_at`
     * IS NULL → now()). Sotto Postgres READ COMMITTED, un UPDATE che si blocca sul lock di
     * riga rivaluta il WHERE contro la versione appena committata (EvalPlanQual): se la
     * colonna guardata non è quella scritta (es. guardia su `status`, scrittura su
     * `finished_at`), il secondo chiamante la trova invariata e "vince" anche lui, doppio.
     * Verificato empiricamente in dev: con guardia/scrittura disallineate `UPDATE 1` su
     * ENTRAMBI i lati concorrenti; con guardia e scrittura sulla stessa colonna, solo il
     * primo vince.
     */
    public function finishRun(FreshnessRun $run): void
    {
        $claimed = FreshnessRun::where('id', $run->id)->whereNull('finished_at')->update(['finished_at' => now()]);
        if ($claimed === 0) {
            return; // già chiusa (o mai stata 'running') da un altro job
        }

        $course = Course::find($run->course_id);
        if (!$course) {
            $run->update(['status' => 'failed', 'failure_reason' => 'Corso non trovato in fase di chiusura run.']);
            return;
        }
        $config = $this->configFor($course);

        $hasObsolete = FreshnessClaim::where('run_id', $run->id)->where('verdict', 'obsoleto')->exists();
        $wantsProposals = ($config->proposals_enabled || $config->student_proposals_enabled) && $hasObsolete;

        if (!$wantsProposals) {
            $run->update(['status' => 'completed', 'proposals_created' => 0]);
            return;
        }

        GenerateFreshnessProposalsJob::dispatch($run->id);
    }

    /**
     * Fase 3 — genera le proposte per i claim obsoleti indicati e le persiste. Chiamata
     * da `run()` (sincrono) e da `GenerateFreshnessProposalsJob` (asincrono). Pubblico
     * perché il job vive in un'altra classe.
     *
     * @param  list<FreshnessClaim>  $obsoleteClaims
     */
    public function generateProposals(FreshnessRun $run, Course $course, CourseFreshnessConfig $config, array $obsoleteClaims): int
    {
        $created = 0;
        foreach ($obsoleteClaims as $claim) {
            try {
                $gen = $this->generator->generate($claim->claim_text, $claim->category, [
                    'source_url' => $claim->source_url,
                ]);

                UpdateProposal::create([
                    'run_id' => $run->id,
                    'freshness_claim_id' => $claim->id,
                    'course_id' => $course->id,
                    'content_source' => $claim->content_source, // instructor | student
                    'block_id' => $claim->block_id,   // valorizzato solo per instructor
                    'module_id' => $claim->module_id, // valorizzato solo per student
                    'sentence_ref' => $claim->sentence_ref,
                    'before' => $claim->claim_text, // verbatim: ancora del diff
                    'after' => $gen['after'],
                    'reason' => $gen['reason'],
                    'source' => $claim->source_url,
                    'source_type' => $claim->source_type,
                    'confidence' => $claim->confidence,
                    'audience' => $config->audience ?? 'adult',
                    'status' => 'pending',
                ]);
                $created++;
            } catch (\Throwable $e) {
                Log::warning('[FreshnessAgent] generazione proposta fallita, claim saltato', [
                    'claim_id' => $claim->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $created;
    }

    /** Config del corso, o i default impliciti se il corso non ne ha una propria. */
    public function configFor(Course $course): CourseFreshnessConfig
    {
        return $course->freshnessConfig ?? new CourseFreshnessConfig([
            'web_search_enabled' => true,
            'primary_sources' => [],
            'audience' => 'adult',
            'proposals_enabled' => true,
            'student_proposals_enabled' => false,
        ]);
    }

    /**
     * Fase 1 lato FORMATORE — estrae e PERSISTE i claim dal sorgente strutturato. Nessuna
     * verifica qui: la Fase 2 resta a carico del chiamante (sincrona in `run()`,
     * distribuita su job in `startAsync()`).
     *
     * @return list<FreshnessClaim>
     */
    private function extractInstructorClaims(FreshnessRun $run, Course $course, CourseSource $source): array
    {
        $extracted = $this->extractor->extract($source->blocks ?? []);
        $claims = [];
        foreach ($extracted['claims'] as $c) {
            $claims[] = FreshnessClaim::create([
                'run_id' => $run->id,
                'course_id' => $course->id,
                'content_source' => 'instructor',
                'block_id' => $c['block_id'],
                'sentence_ref' => $c['sentence_ref'],
                'claim_text' => $c['claim_text'],
                'category' => $c['category'],
            ]);
        }

        return $claims;
    }

    /**
     * Fase 1 lato STUDENTE (modules.content) — solo se opt-in (student_proposals_enabled).
     * È contenuto utente-finale: niente analisi finché non è esplicitamente attivata.
     * Chiamare SOLO dopo aver già estratto (e, in `run()`, verificato) il lato formatore:
     * se l'estrazione qui lancia, i claim formatore devono essere già a posto.
     *
     * @return list<FreshnessClaim>
     */
    private function extractStudentClaims(FreshnessRun $run, Course $course): array
    {
        $extracted = $this->studentExtractor->extract($course->modules()->get());
        $claims = [];
        foreach ($extracted['claims'] as $c) {
            $claims[] = FreshnessClaim::create([
                'run_id' => $run->id,
                'course_id' => $course->id,
                'content_source' => 'student',
                'module_id' => $c['module_id'],
                'sentence_ref' => $c['sentence_ref'],
                'claim_text' => $c['claim_text'],
                'category' => $c['category'],
            ]);
        }

        return $claims;
    }

    /**
     * Fase 2 — verifica un claim (formatore o studente, stesso verificatore). Aggiorna il
     * claim col verdetto. Resiliente: un errore non ferma la run. Ritorna true se obsoleto.
     */
    private function verifyClaim(FreshnessClaim $claim, CourseFreshnessConfig $config): bool
    {
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
            return $v['verdict'] === 'obsoleto';
        } catch (\Throwable $e) {
            Log::warning('[FreshnessAgent] verifica claim fallita, lascio non verificato', [
                'claim_id' => $claim->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
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
