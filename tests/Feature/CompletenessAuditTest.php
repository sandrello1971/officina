<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CompletenessFinding;
use App\Models\Module;
use App\Models\ModulePresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Controllo di completezza della consegna: non verifica se un contenuto è aggiornato
 * (Freshness) né se manca un ARGOMENTO (P26 Gap Scout), ma se ogni modulo ha slide,
 * HTML integro e ordinamento corretto. Puro/read-only, nessuna chiamata AI.
 */
class CompletenessAuditTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourse(): Course
    {
        return Course::create([
            'name' => 'Corso di prova',
            'slug' => 'corso-' . Str::lower(Str::random(8)),
            'is_active' => true,
        ]);
    }

    private function addModule(Course $course, string $title, ?string $content, int $sort): Module
    {
        return Module::create([
            'course_id' => $course->id,
            'title' => $title,
            'content' => $content,
            'sort_order' => $sort,
            'is_active' => true,
        ]);
    }

    private function asAdmin(): self
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => 'a@ente.it']);
    }

    public function test_modulo_senza_presentazione_genera_segnalazione(): void
    {
        $course = $this->makeCourse();
        $module = $this->addModule($course, 'Capitolo 1', '<h1>Capitolo 1</h1><p>Testo.</p>', 0);

        $this->artisan('course:completeness-audit', ['course' => $course->slug])->assertExitCode(0);

        $this->assertDatabaseHas('completeness_findings', [
            'course_id' => $course->id,
            'module_id' => $module->id,
            'check_type' => 'missing_presentation',
        ]);
    }

    public function test_modulo_con_presentazione_pronta_non_genera_segnalazione_di_slide(): void
    {
        $course = $this->makeCourse();
        $module = $this->addModule($course, 'Capitolo 1', '<h1>Capitolo 1</h1><p>Testo.</p>', 0);
        ModulePresentation::create(['module_id' => $module->id, 'status' => 'ready', 'source' => 'generated']);

        $this->artisan('course:completeness-audit', ['course' => $course->slug])->assertExitCode(0);

        $this->assertDatabaseMissing('completeness_findings', [
            'module_id' => $module->id,
            'check_type' => 'missing_presentation',
        ]);
    }

    public function test_contenuto_markdown_grezzo_viene_intercettato(): void
    {
        $course = $this->makeCourse();
        $module = $this->addModule($course, 'Capitolo Skills', "Testo senza tag html.\n\n**Punti chiave.** Alcuni punti.", 0);

        $this->artisan('course:completeness-audit', ['course' => $course->slug])->assertExitCode(0);

        $this->assertDatabaseHas('completeness_findings', [
            'module_id' => $module->id,
            'check_type' => 'content_not_html',
        ]);
    }

    public function test_contenuto_html_valido_non_genera_segnalazione(): void
    {
        $course = $this->makeCourse();
        $module = $this->addModule($course, 'Capitolo 1', '<h1>Capitolo 1</h1><p>Testo con <strong>enfasi</strong> vera.</p>', 0);

        $this->artisan('course:completeness-audit', ['course' => $course->slug])->assertExitCode(0);

        $this->assertDatabaseMissing('completeness_findings', [
            'module_id' => $module->id,
            'check_type' => 'content_not_html',
        ]);
    }

    public function test_sort_order_duplicato_viene_intercettato(): void
    {
        $course = $this->makeCourse();
        $this->addModule($course, 'A', '<p>a</p>', 0);
        $this->addModule($course, 'B', '<p>b</p>', 0); // duplicato

        $this->artisan('course:completeness-audit', ['course' => $course->slug])->assertExitCode(0);

        $this->assertDatabaseHas('completeness_findings', [
            'course_id' => $course->id,
            'check_type' => 'sort_order_duplicate',
        ]);
    }

    public function test_una_segnalazione_risolta_viene_chiusa_al_giro_successivo(): void
    {
        $course = $this->makeCourse();
        $module = $this->addModule($course, 'Capitolo 1', '<h1>Capitolo 1</h1><p>Testo.</p>', 0);

        $this->artisan('course:completeness-audit', ['course' => $course->slug]);
        $this->assertDatabaseHas('completeness_findings', [
            'module_id' => $module->id, 'check_type' => 'missing_presentation', 'resolved_at' => null,
        ]);

        // Il problema viene sistemato...
        ModulePresentation::create(['module_id' => $module->id, 'status' => 'ready', 'source' => 'generated']);

        // ...e il giro successivo chiude la segnalazione (non la cancella: resolved_at).
        $this->artisan('course:completeness-audit', ['course' => $course->slug]);
        $finding = CompletenessFinding::where('module_id', $module->id)
            ->where('check_type', 'missing_presentation')->first();
        $this->assertNotNull($finding->resolved_at);
    }

    public function test_segnalazione_ignorata_non_riappare_come_aperta(): void
    {
        $course = $this->makeCourse();
        $module = $this->addModule($course, 'Capitolo 1', '<h1>Capitolo 1</h1><p>Testo.</p>', 0);
        $this->artisan('course:completeness-audit', ['course' => $course->slug]);

        $finding = CompletenessFinding::where('module_id', $module->id)->first();
        $this->asAdmin()->patch(route('admin.completeness.dismiss', $finding))->assertRedirect();

        $this->assertTrue($finding->fresh()->dismissed_at !== null);
        $this->assertDatabaseCount('completeness_findings', 1); // non duplicata al giro successivo
        $this->artisan('course:completeness-audit', ['course' => $course->slug]);
        $this->assertDatabaseCount('completeness_findings', 1);
    }

    public function test_pagina_completezza_admin_e_raggiungibile(): void
    {
        $course = $this->makeCourse();
        $this->addModule($course, 'Capitolo 1', '<h1>Capitolo 1</h1><p>Testo.</p>', 0);

        $this->asAdmin()->get(route('admin.completeness.index'))->assertOk();
        $this->asAdmin()->get(route('admin.completeness.show', $course))->assertOk();
    }

    public function test_pulsante_verifica_ora_esegue_il_controllo(): void
    {
        $course = $this->makeCourse();
        $module = $this->addModule($course, 'Capitolo 1', '<h1>Capitolo 1</h1><p>Testo.</p>', 0);

        $this->asAdmin()->post(route('admin.completeness.run', $course))->assertRedirect();

        $this->assertDatabaseHas('completeness_findings', [
            'module_id' => $module->id, 'check_type' => 'missing_presentation',
        ]);
    }
}
