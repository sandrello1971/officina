<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CompletenessFinding;
use App\Models\InstructorManualSection;
use App\Models\Material;
use App\Models\Module;
use App\Models\ModulePresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    /**
     * Regressione: bug reale trovato durante il primo giro su prod. Una cartella a 0700
     * NON posseduta da www-data (creata da uno script CLI) deve essere segnalata SENZA
     * mai far crashare l'audit — anche quando contiene sottocartelle che, per via degli
     * stessi permessi, non è possibile aprire.
     */
    public function test_cartella_0700_viene_segnalata_senza_crash(): void
    {
        $course = $this->makeCourse();
        $this->addModule($course, 'Capitolo 1', '<h1>Capitolo 1</h1><p>Testo.</p>', 0);

        $dir = Storage::disk('local')->path("materials/{$course->slug}");
        mkdir($dir, 0700, true);
        mkdir("{$dir}/sotto", 0700, true);

        $this->artisan('course:completeness-audit', ['course' => $course->slug])->assertExitCode(0);

        $this->assertDatabaseHas('completeness_findings', [
            'course_id' => $course->id,
            'check_type' => 'bad_permissions',
        ]);

        chmod("{$dir}/sotto", 0775);
        chmod($dir, 0775); // pulizia: non lasciare cartelle 0700 nell'ambiente di test
    }

    /**
     * Regressione: bug reale trovato su un corso vero. Un manuale organizzato per
     * SESSIONE (poche sezioni che raggruppano più capitoli) è normale, non un problema:
     * il controllo non deve inventare per-modulo "non menzionato" (falso positivo
     * osservato al 100% su un corso reale), solo segnalare un conteggio molto basso.
     */
    public function test_manuale_organizzato_per_sessione_non_genera_falsi_positivi_per_modulo(): void
    {
        $course = $this->makeCourse();
        for ($i = 0; $i < 4; $i++) {
            $this->addModule($course, "Capitolo {$i}", '<h1>Cap</h1><p>Testo.</p>', $i);
            $module = Module::where('course_id', $course->id)->where('sort_order', $i)->first();
            ModulePresentation::create(['module_id' => $module->id, 'status' => 'ready', 'source' => 'generated']);
        }
        $material = Material::create([
            'course_id' => $course->id, 'title' => 'Manuale Formatore',
            'is_instructor_only' => true, 'file_type' => 'md', 'sort_order' => 0,
        ]);
        // Un manuale per-sessione: 2 sezioni per 4 capitoli, nessuna contiene i titoli letterali.
        InstructorManualSection::create([
            'material_id' => $material->id, 'course_id' => $course->id, 'title' => 'Sessione 1',
            'anchor' => 'sessione-1', 'heading_level' => 1, 'sort_order' => 0,
            'content_html' => '<h1>Sessione 1</h1><p>Parliamo di configurazione e contesto insieme.</p>',
        ]);
        InstructorManualSection::create([
            'material_id' => $material->id, 'course_id' => $course->id, 'title' => 'Sessione 2',
            'anchor' => 'sessione-2', 'heading_level' => 1, 'sort_order' => 1,
            'content_html' => '<h1>Sessione 2</h1><p>Parliamo di prompting e debug insieme.</p>',
        ]);

        $this->artisan('course:completeness-audit', ['course' => $course->slug])->assertExitCode(0);

        $this->assertDatabaseMissing('completeness_findings', ['check_type' => 'instructor_manual_missing_module']);
        // 2 sezioni per 4 moduli è >= metà: nessuna segnalazione, nemmeno aggregata.
        $this->assertDatabaseMissing('completeness_findings', ['check_type' => 'instructor_manual_low_coverage']);
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
