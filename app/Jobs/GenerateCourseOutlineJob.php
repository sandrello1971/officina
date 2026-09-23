<?php

namespace App\Jobs;

use App\Models\CourseGenerationRun;
use App\Services\CourseGeneration\CourseOutlineGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

// Motore generazione corsi da KB — Fase 1: propone la struttura del corso
// (outline) a partire dal brief e dai materiali caricati. Scrive la proposta su
// CourseGenerationRun.outline, phase resta 'outline' (in attesa di revisione).
class GenerateCourseOutlineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(public string $runId) {}

    public function handle(CourseOutlineGenerationService $service): void
    {
        $run = CourseGenerationRun::find($this->runId);
        if (!$run) {
            return; // eliminato nel frattempo
        }

        try {
            $outline = $service->generate($run->course, $run->brief ?? []);
            $run->update(['outline' => $outline, 'status' => 'completed']);
        } catch (Throwable $e) {
            Log::warning('[officina] generazione outline corso fallita', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);
            $run->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }
}
