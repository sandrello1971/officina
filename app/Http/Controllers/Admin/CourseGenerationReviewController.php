<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateCourseOutlineJob;
use App\Jobs\GenerateModuleArtifactsJob;
use App\Models\Admin;
use App\Models\CourseGenerationRun;
use App\Models\InstructorManualSection;
use App\Models\Material;
use App\Models\Module;
use App\Models\ModuleGenerationArtifact;
use App\Models\ModulePresentation;
use App\Services\Schola\SlidePreviewService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Motore generazione corsi da KB — UI di revisione formatore. Generalizza il
 * pattern HITL di FreshnessProposalController (form POST classico, niente
 * Livewire): qui si VEDE cosa l'AI ha generato e si APPROVA/CORREGGE/RIFIUTA,
 * nulla diventa visibile ai discenti senza passare da publishModule().
 */
class CourseGenerationReviewController extends Controller
{
    public function show(CourseGenerationRun $run)
    {
        $run->load('course');

        if ($run->isOutlinePending()) {
            return view('admin.course-generation.outline', compact('run'));
        }

        $modules = Module::where('course_id', $run->course_id)
            ->whereHas('generationArtifacts', fn ($q) => $q->where('run_id', $run->id))
            ->with(['generationArtifacts' => fn ($q) => $q->where('run_id', $run->id)])
            ->orderBy('sort_order')
            ->get();

        return view('admin.course-generation.show', compact('run', 'modules'));
    }

    /** Stato per il polling live (analisi outline o contenuti in corso). */
    public function status(CourseGenerationRun $run)
    {
        return response()->json([
            'status' => $run->status,
            'phase' => $run->phase,
            'error' => $run->error,
        ]);
    }

    /** Rilancia la proposta di outline (scarta quella corrente). */
    public function regenerateOutline(CourseGenerationRun $run)
    {
        abort_unless($run->isOutlinePending(), 422, 'Non è più in fase di proposta struttura.');

        $run->update(['status' => 'running', 'outline' => null, 'error' => null]);
        GenerateCourseOutlineJob::dispatch($run->id)->afterResponse();

        return back()->with('success', 'Nuova proposta di struttura in elaborazione.');
    }

    /**
     * Approva (eventualmente corretta) la struttura proposta: crea i Module
     * reali (is_active=false) per le sole entry incluse, poi dispatcha la
     * generazione dei 3 artefatti per ciascuno.
     */
    public function approveOutline(Request $request, CourseGenerationRun $run)
    {
        abort_unless($run->isOutlinePending() && $run->status === 'completed', 422, 'La proposta non è pronta per l\'approvazione.');

        $data = $request->validate([
            'modules' => 'required|array|min:1',
            'modules.*.title' => 'required|string|max:255',
            'modules.*.summary' => 'nullable|string|max:2000',
            'modules.*.include' => 'nullable|string',
        ]);

        $included = array_values(array_filter($data['modules'], fn ($m) => !empty($m['include'])));
        abort_unless(!empty($included), 422, 'Includi almeno un modulo.');

        $baseSort = (int) (Module::where('course_id', $run->course_id)->max('sort_order') ?? -1) + 1;

        $moduleIds = DB::transaction(function () use ($included, $run, $baseSort) {
            $ids = [];
            foreach ($included as $i => $m) {
                $module = Module::create([
                    'course_id' => $run->course_id,
                    'title' => $m['title'],
                    'description' => $m['summary'] ?? null,
                    'is_active' => false, // invisibile ai discenti finché non pubblicato
                    'sort_order' => $baseSort + $i,
                ]);
                $ids[] = $module->id;
            }
            $run->update([
                'phase' => 'content',
                'status' => 'running',
                'outline' => ['planned_modules' => count($ids)],
            ]);
            return $ids;
        });

        foreach ($moduleIds as $moduleId) {
            GenerateModuleArtifactsJob::dispatch($run->id, $moduleId)->afterResponse();
        }

        return redirect()->route('admin.course-generation.show', $run)
            ->with('success', count($moduleIds) . ' moduli creati (bozza): generazione manuali e slide avviata.');
    }

    /**
     * Approva un artefatto. Permette editing del contenuto (manuali) prima di
     * approvare — stesso pattern di FreshnessProposalController::approve().
     */
    public function approve(Request $request, ModuleGenerationArtifact $artifact)
    {
        abort_unless($artifact->isReviewable(), 422, 'Questo artefatto non è in revisione.');

        $data = ['status' => 'approved', 'reviewed_by' => $this->adminId(), 'reviewed_at' => now()];

        if ($artifact->artifact_type !== ModuleGenerationArtifact::TYPE_SLIDES) {
            $newHtml = trim((string) $request->input('html', ''));
            $currentHtml = trim((string) ($artifact->content['html'] ?? ''));
            if ($newHtml !== '' && $newHtml !== $currentHtml) {
                $data['content'] = ['html' => $newHtml];
                $data['edited_by_human'] = true;

                if ($artifact->artifact_type === ModuleGenerationArtifact::TYPE_STUDENT_MANUAL) {
                    $artifact->module?->update(['content_draft' => $newHtml]);
                }
            }
        }

        $artifact->update($data);

        return back()->with('success', 'Artefatto approvato.');
    }

    /** Rifiuta un artefatto: resta collegato al modulo, riproponibile con "rigenera modulo". */
    public function reject(ModuleGenerationArtifact $artifact)
    {
        abort_unless($artifact->isReviewable(), 422, 'Questo artefatto non è in revisione.');

        $artifact->update(['status' => 'rejected', 'reviewed_by' => $this->adminId(), 'reviewed_at' => now()]);

        return back()->with('success', 'Artefatto rifiutato: usa "Rigenera modulo" per una nuova proposta.');
    }

    /** Rigenera i 3 artefatti del modulo (rimpiazza quelli non ancora pubblicati). */
    public function regenerateModule(CourseGenerationRun $run, Module $module)
    {
        abort_unless($module->course_id === $run->course_id, 404);

        ModuleGenerationArtifact::where('run_id', $run->id)
            ->where('module_id', $module->id)
            ->where('status', '!=', 'published')
            ->delete();

        GenerateModuleArtifactsJob::dispatch($run->id, $module->id)->afterResponse();

        return back()->with('success', 'Rigenerazione del modulo avviata.');
    }

    /**
     * Pubblica il modulo: solo se i 3 artefatti della run sono 'approved'.
     * content_draft → content, sezioni manuale formatore create, slide bozza
     * promossa a pubblicata, Module.is_active=true.
     */
    public function publishModule(CourseGenerationRun $run, Module $module, SlidePreviewService $preview)
    {
        abort_unless($module->course_id === $run->course_id, 404);

        $artifacts = ModuleGenerationArtifact::where('run_id', $run->id)->where('module_id', $module->id)->get()
            ->keyBy('artifact_type');

        abort_unless(
            $artifacts->count() === 3 && $artifacts->every(fn ($a) => $a->status === 'approved'),
            422,
            'Tutti e 3 gli artefatti del modulo devono essere approvati prima di pubblicare.'
        );

        $studentManual = $artifacts[ModuleGenerationArtifact::TYPE_STUDENT_MANUAL];
        $instructorManual = $artifacts[ModuleGenerationArtifact::TYPE_INSTRUCTOR_MANUAL];
        $slides = $artifacts[ModuleGenerationArtifact::TYPE_SLIDES];

        $oldPublishedPresentation = null;

        DB::transaction(function () use ($module, $studentManual, $instructorManual, $slides, &$oldPublishedPresentation) {
            $module->update([
                'content' => $studentManual->content['html'] ?? $module->content_draft,
                'content_draft' => null,
                'is_active' => true,
            ]);

            $material = $this->aiInstructorManualMaterial($module->course_id);
            $nextSort = (int) (InstructorManualSection::where('module_id', $module->id)->max('sort_order') ?? -1) + 1;
            InstructorManualSection::create([
                'material_id' => $material->id,
                'course_id' => $module->course_id,
                'module_id' => $module->id,
                'title' => $module->title,
                'anchor' => 'ai-generated-' . Str::slug($module->id),
                'heading_level' => 2,
                'sort_order' => $nextSort,
                'content_html' => $instructorManual->content['html'] ?? '',
                'module_assigned_manually' => true,
            ]);

            if ($slides->artifact_id) {
                $draft = ModulePresentation::find($slides->artifact_id);
                if ($draft && $draft->status === 'ready') {
                    $oldPublishedPresentation = $module->presentations()->published()->latest('published_at')->first();
                    if ($oldPublishedPresentation) {
                        $oldPublishedPresentation->update(['published_at' => null]);
                    }
                    $draft->update(['published_at' => now()]);
                }
            }

            foreach ([$studentManual, $instructorManual, $slides] as $a) {
                $a->update(['status' => 'published']);
            }
        });

        if ($oldPublishedPresentation) {
            if ($oldPublishedPresentation->file_path) {
                $preview->purge($oldPublishedPresentation->file_path);
                Storage::disk('local')->delete($oldPublishedPresentation->file_path);
            }
            $oldPublishedPresentation->delete();
        }

        $this->maybeCloseRun($run);

        return back()->with('success', "Modulo «{$module->title}» pubblicato: ora è visibile ai discenti.");
    }

    /** Se tutti i moduli della run sono pubblicati, chiude la run. */
    private function maybeCloseRun(CourseGenerationRun $run): void
    {
        $stillOpen = ModuleGenerationArtifact::where('run_id', $run->id)
            ->where('status', '!=', 'published')
            ->exists();

        if (!$stillOpen) {
            $run->update(['phase' => 'done', 'status' => 'completed', 'completed_at' => now()]);
        }
    }

    /** Material "contenitore" per le InstructorManualSection generate dal motore (una per corso). */
    private function aiInstructorManualMaterial(string $courseId): Material
    {
        return Material::firstOrCreate(
            ['course_id' => $courseId, 'module_id' => null, 'title' => 'Manuale formatore generato (AI)'],
            ['is_instructor_only' => true, 'is_downloadable' => false, 'sort_order' => 0]
        );
    }

    private function adminId(): ?string
    {
        return Admin::where('email', session('admin_email'))->value('id');
    }
}
