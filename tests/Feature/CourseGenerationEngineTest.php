<?php

namespace Tests\Feature;

use App\Jobs\GenerateCourseOutlineJob;
use App\Models\Course;
use App\Models\CourseGenerationRun;
use App\Models\InstructorManualSection;
use App\Models\Material;
use App\Models\Module;
use App\Models\ModuleGenerationArtifact;
use App\Models\ModulePresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Motore generazione corsi da KB — outline → review → contenuti moduli → review
 * → publish. Copre lo scheletro end-to-end (senza toccare la generazione slide
 * vera: fake della Claude API, stesso pattern di ModulePresentationTest).
 */
class CourseGenerationEngineTest extends TestCase
{
    use RefreshDatabase;

    private function clearTerminatingCallbacks(): void
    {
        $prop = new \ReflectionProperty($this->app, 'terminatingCallbacks');
        $prop->setAccessible(true);
        $prop->setValue($this->app, []);
    }

    private function asAdmin(): self
    {
        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@ente.it']);

        return $this;
    }

    private function makeCourseWithKb(): Course
    {
        $course = Course::create([
            'name' => 'Sicurezza sul lavoro',
            'slug' => 'corso-' . Str::lower(Str::random(8)),
            'is_active' => true,
        ]);

        Material::create([
            'course_id' => $course->id,
            'module_id' => null,
            'title' => 'Dispensa base',
            'is_instructor_only' => true,
            'is_downloadable' => false,
            'content_html' => '<p>Contenuto sorgente sulla sicurezza nei luoghi di lavoro, dpi, rischi.</p>',
        ]);

        return $course;
    }

    private function fakeOutlineAndManuals(): void
    {
        config(['services.anthropic.key' => 'test-key']);

        Http::fake(['api.anthropic.com/*' => Http::sequence()
            // 1) outline
            ->push(['content' => [['type' => 'text', 'text' => json_encode([
                'modules' => [['title' => 'Modulo 1 — DPI', 'summary' => 'I dispositivi di protezione individuale.']],
            ])]], 'usage' => ['input_tokens' => 10, 'output_tokens' => 10]], 200)
            // 2) manuale discente
            ->push(['content' => [['type' => 'text', 'text' => '<h3>DPI</h3><p>I dispositivi di protezione individuale proteggono il lavoratore.</p>']]], 200)
            // 3) manuale formatore
            ->push(['content' => [['type' => 'text', 'text' => '<h3>Note per il formatore</h3><p>Enfatizzare l\'uso corretto dei DPI.</p>']]], 200)
            // 4) slide (tool_use, come LessonPresentationService si aspetta)
            ->push(['content' => [[
                'type' => 'tool_use',
                'name' => 'emit_presentation',
                'input' => ['slides' => [
                    ['layout' => 'bullets_clean', 'title' => 'DPI', 'bullets' => ['Casco', 'Guanti']],
                ]],
            ]], 'stop_reason' => 'tool_use', 'usage' => ['input_tokens' => 10, 'output_tokens' => 20]], 200)
        ]);
    }

    public function test_outline_generation_scrive_la_proposta_sul_run(): void
    {
        $course = $this->makeCourseWithKb();
        $this->fakeOutlineAndManuals();

        $run = CourseGenerationRun::create([
            'course_id' => $course->id,
            'status' => 'running',
            'phase' => 'outline',
            'brief' => ['target' => 'lavoratori neoassunti'],
            'started_at' => now(),
        ]);

        GenerateCourseOutlineJob::dispatchSync($run->id);

        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertSame('outline', $run->phase);
        $this->assertCount(1, $run->outline);
        $this->assertSame('Modulo 1 — DPI', $run->outline[0]['title']);
    }

    public function test_flusso_completo_outline_contenuti_review_publish(): void
    {
        Storage::fake('local');
        $course = $this->makeCourseWithKb();
        $this->fakeOutlineAndManuals();

        $run = CourseGenerationRun::create([
            'course_id' => $course->id,
            'status' => 'running',
            'phase' => 'outline',
            'brief' => [],
            'started_at' => now(),
        ]);
        GenerateCourseOutlineJob::dispatchSync($run->id);
        $run->refresh();

        // Approvazione outline → crea il Module (bozza, is_active=false).
        $this->asAdmin()->post(route('admin.course-generation.outline.approve', $run), [
            'modules' => [
                ['title' => 'Modulo 1 — DPI', 'summary' => 'I DPI.', 'include' => '1'],
            ],
        ])->assertRedirect(route('admin.course-generation.show', $run));

        $module = Module::where('course_id', $course->id)->first();
        $this->assertNotNull($module);
        $this->assertFalse($module->is_active);

        // dispatch(...)->afterResponse() nel controller viene eseguito qui dal
        // client di test HTTP di Laravel (che chiama app()->terminate() dopo
        // ogni richiesta). Application::terminate() non svuota però
        // $terminatingCallbacks: senza pulizia, il job rigirerebbe ad ogni
        // request successiva in questo stesso test method, consumando di nuovo
        // la sequenza Http::fake già esaurita e corrompendo gli artefatti.
        $this->clearTerminatingCallbacks();

        $artifacts = ModuleGenerationArtifact::where('run_id', $run->id)->where('module_id', $module->id)->get()->keyBy('artifact_type');
        $this->assertCount(3, $artifacts);
        $this->assertSame('pending_review', $artifacts['student_manual']->status);
        $this->assertSame('pending_review', $artifacts['instructor_manual']->status);
        $this->assertSame('pending_review', $artifacts['slides']->status);

        $module->refresh();
        $this->assertStringContainsString('DPI', $module->content_draft);
        $this->assertNull($module->content); // non pubblicato: content live intatto

        $presentation = ModulePresentation::find($artifacts['slides']->artifact_id);
        $this->assertSame('ready', $presentation->status);
        $this->assertNull($presentation->published_at);

        // Revisione: approva i 3 artefatti (correggendo il manuale discente).
        $this->asAdmin()->post(route('admin.course-generation.artifacts.approve', $artifacts['student_manual']), [
            'html' => '<h3>DPI (corretto)</h3><p>Testo rivisto dal formatore.</p>',
        ])->assertRedirect();
        $this->asAdmin()->post(route('admin.course-generation.artifacts.approve', $artifacts['instructor_manual']))->assertRedirect();
        $this->asAdmin()->post(route('admin.course-generation.artifacts.approve', $artifacts['slides']))->assertRedirect();

        $artifacts['student_manual']->refresh();
        $this->assertTrue($artifacts['student_manual']->edited_by_human);
        $this->assertSame('approved', $artifacts['student_manual']->fresh()->status);

        // Pubblicazione del modulo.
        $this->asAdmin()->post(route('admin.course-generation.modules.publish', [$run, $module]))->assertRedirect();

        $module->refresh();
        $this->assertTrue($module->is_active);
        $this->assertStringContainsString('corretto', $module->content);
        $this->assertNull($module->content_draft);

        $this->assertSame(1, InstructorManualSection::where('module_id', $module->id)->count());

        $presentation->refresh();
        $this->assertNotNull($presentation->published_at);

        $this->assertSame(3, ModuleGenerationArtifact::where('run_id', $run->id)->where('status', 'published')->count());
        $this->assertSame('done', $run->fresh()->phase);
    }

    public function test_creazione_run_bloccata_senza_materiali_kb(): void
    {
        $course = Course::create(['name' => 'Corso vuoto', 'slug' => 'corso-' . Str::lower(Str::random(8)), 'is_active' => true]);

        $this->asAdmin()->get(route('admin.course-generation.create', $course))->assertStatus(422);
    }
}
