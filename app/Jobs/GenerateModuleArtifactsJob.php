<?php

namespace App\Jobs;

use App\Models\BrandProfile;
use App\Models\CourseGenerationRun;
use App\Models\Module;
use App\Models\ModuleGenerationArtifact;
use App\Services\CourseGeneration\CourseSourceAggregator;
use App\Services\CourseGeneration\ModuleManualGenerationService;
use App\Services\Schola\LessonPresentationService;
use App\Services\Schola\SchoolBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

// Motore generazione corsi da KB — Fase 2: per UN modulo (creato dall'outline
// approvato), genera i 3 artefatti — manuale discente, manuale formatore, slide —
// ciascuno con la propria riga ModuleGenerationArtifact in 'pending_review'
// (o 'draft_ai' + errore in generation_meta se quel singolo step fallisce: un
// artefatto fallito non blocca gli altri due).
//
// Manuale discente → module.content_draft (bozza, non tocca module.content).
// Manuale formatore → testo in artifact.content (materializzato in
// InstructorManualSection solo al publish).
// Slide → riusa LessonPresentationService::buildForModule() così com'è (stesso
// generatore di ModulePresentationController), la ModulePresentation risultante
// resta bozza (published_at null) finché il modulo non viene pubblicato.
class GenerateModuleArtifactsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries = 1;

    public function __construct(public string $runId, public string $moduleId) {}

    public function handle(
        CourseSourceAggregator $aggregator,
        ModuleManualGenerationService $manuals,
        LessonPresentationService $presentations,
    ): void {
        $run = CourseGenerationRun::find($this->runId);
        $module = Module::find($this->moduleId);
        if (!$run || !$module) {
            return; // eliminati nel frattempo
        }

        $brief = $run->brief ?? [];
        $source = $aggregator->aggregate($run->course, $run->selected_sources);

        $this->generateStudentManual($run, $module, $manuals, $brief, $source);
        $this->generateInstructorManual($run, $module, $manuals, $brief, $source);
        $this->generateSlides($run, $module, $presentations);

        $this->maybeCompleteRun($run);
    }

    private function generateStudentManual(CourseGenerationRun $run, Module $module, ModuleManualGenerationService $manuals, array $brief, string $source): void
    {
        $artifact = $this->artifactFor($run, $module, ModuleGenerationArtifact::TYPE_STUDENT_MANUAL);

        try {
            $html = $manuals->generateStudentManual($module, $brief, $source);
            $module->update(['content_draft' => $html]);
            $artifact->update(['status' => 'pending_review', 'content' => ['html' => $html]]);
        } catch (Throwable $e) {
            Log::warning('[officina] generazione manuale discente fallita', ['module_id' => $module->id, 'error' => $e->getMessage()]);
            $artifact->update(['status' => 'draft_ai', 'generation_meta' => ['failure_reason' => $e->getMessage()]]);
        }
    }

    private function generateInstructorManual(CourseGenerationRun $run, Module $module, ModuleManualGenerationService $manuals, array $brief, string $source): void
    {
        $artifact = $this->artifactFor($run, $module, ModuleGenerationArtifact::TYPE_INSTRUCTOR_MANUAL);

        try {
            $html = $manuals->generateInstructorManual($module, $brief, $source);
            $artifact->update(['status' => 'pending_review', 'content' => ['html' => $html]]);
        } catch (Throwable $e) {
            Log::warning('[officina] generazione manuale formatore fallita', ['module_id' => $module->id, 'error' => $e->getMessage()]);
            $artifact->update(['status' => 'draft_ai', 'generation_meta' => ['failure_reason' => $e->getMessage()]]);
        }
    }

    private function generateSlides(CourseGenerationRun $run, Module $module, LessonPresentationService $presentations): void
    {
        $artifact = $this->artifactFor($run, $module, ModuleGenerationArtifact::TYPE_SLIDES);

        $draft = $module->presentations()->draft()->latest()->first()
            ?? $module->presentations()->create(['status' => 'pending']);
        $artifact->update(['artifact_id' => $draft->id]);

        if (trim(strip_tags($module->content_draft ?? '')) === '') {
            $artifact->update(['status' => 'draft_ai', 'generation_meta' => ['failure_reason' => 'Manuale discente non ancora generato: le slide si costruiscono dal suo contenuto.']]);
            return;
        }

        $draft->update(['status' => 'generating']);

        try {
            // buildForModule() rileggerebbe module.content dal DB (non la bozza
            // in content_draft): si usa il core condiviso buildFrom() con il
            // testo del draft, stessa composizione di buildForModule() ma senza
            // toccare il content pubblicato del modulo.
            $theme = BrandProfile::forPlatform()->resolvedTheme();
            $schoolName = SchoolBranding::for(null)->instanceName();
            $result = $presentations->buildFrom(
                trim((string) $module->content_draft),
                (string) $module->title,
                trim((string) ($module->course?->name ?? '')),
                $schoolName,
                $theme,
                "module-presentations/{$module->id}/{$draft->id}.pptx",
                [
                    'subject' => $module->course?->name,
                    'log_context' => ['module_id' => $module->id, 'module_presentation_id' => $draft->id],
                ],
            );
            $draft->update([
                'file_path' => $result['file_path'],
                'status' => 'ready',
                'generation_meta' => $result['meta'],
                'spec' => $result['spec'] ?? null,
            ]);
            $artifact->update(['status' => 'pending_review']);
        } catch (Throwable $e) {
            Log::warning('[officina] generazione slide modulo fallita', ['module_id' => $module->id, 'error' => $e->getMessage()]);
            $draft->update(['status' => 'failed', 'generation_meta' => ['failure_reason' => $e->getMessage()]]);
            $artifact->update(['status' => 'draft_ai', 'generation_meta' => ['failure_reason' => $e->getMessage()]]);
        }
    }

    private function artifactFor(CourseGenerationRun $run, Module $module, string $type): ModuleGenerationArtifact
    {
        return ModuleGenerationArtifact::firstOrCreate(
            ['run_id' => $run->id, 'module_id' => $module->id, 'artifact_type' => $type],
            ['status' => 'draft_ai']
        );
    }

    /**
     * Chiude la run quando ogni modulo pianificato ha le sue 3 righe artefatto
     * (esito individuale pending_review o draft_ai+errore non conta: qui si
     * verifica solo che il job sia PASSATO su ogni modulo, non che sia riuscito
     * — i fallimenti restano visibili in revisione con "Rigenera modulo").
     */
    private function maybeCompleteRun(CourseGenerationRun $run): void
    {
        $expectedModules = (int) ($run->outline['planned_modules'] ?? 0);
        if ($expectedModules === 0) {
            return;
        }

        $doneModules = ModuleGenerationArtifact::where('run_id', $run->id)
            ->select('module_id')
            ->groupBy('module_id')
            ->havingRaw('COUNT(*) = 3')
            ->get()
            ->count();

        if ($doneModules >= $expectedModules) {
            $run->update(['phase' => 'content', 'status' => 'completed', 'completed_at' => now()]);
        }
    }
}
