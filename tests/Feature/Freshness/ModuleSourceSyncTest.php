<?php

namespace Tests\Feature\Freshness;

use App\Models\Course;
use App\Models\CourseSource;
use App\Models\Material;
use App\Models\Module;
use App\Services\Freshness\ModuleSourceSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ModuleSourceSync — allinea course_sources ai moduli per i corsi async SENZA manuale.
 * Risolve staleness (versione = hash del contenuto) e passo manuale (auto-provisioning).
 * Richiede pandoc (come CourseSourceExtractor).
 */
class ModuleSourceSyncTest extends TestCase
{
    use RefreshDatabase;

    private function skipIfNoPandoc(): void
    {
        exec('pandoc --version 2>/dev/null', $o, $rc);
        if ($rc !== 0) {
            $this->markTestSkipped('pandoc non disponibile');
        }
    }

    private function course(): Course
    {
        return Course::create([
            'name' => 'AsyncNoManual', 'slug' => 'async-nomanual-' . uniqid(),
            'is_active' => true, 'sort_order' => 1, 'modality' => 'async',
        ]);
    }

    private function module(Course $c, string $html, int $order): Module
    {
        return Module::create([
            'course_id' => $c->id, 'title' => 'M' . $order,
            'content' => $html, 'sort_order' => $order, 'is_active' => true,
        ]);
    }

    private function sync(): ModuleSourceSync
    {
        return app(ModuleSourceSync::class);
    }

    public function test_crea_il_sorgente_dai_moduli_al_primo_giro(): void
    {
        $this->skipIfNoPandoc();
        $course = $this->course();
        $this->module($course, '<h1>Capitolo 1</h1><p>Nel 2024 il mercato vale 1 miliardo.</p>', 0);
        $this->module($course, '<h1>Capitolo 2</h1><p>Claude 3.5 è il modello del corso.</p>', 1);

        $src = $this->sync()->ensureFresh($course);

        $this->assertNotNull($src);
        $this->assertStringStartsWith('mod-', $src->version);
        $this->assertNotEmpty($src->blocks);
        $this->assertDatabaseCount('course_sources', 1);
    }

    public function test_e_idempotente_se_il_contenuto_non_cambia(): void
    {
        $this->skipIfNoPandoc();
        $course = $this->course();
        $this->module($course, '<h1>Capitolo 1</h1><p>Testo databile del 2024.</p>', 0);

        $first = $this->sync()->ensureFresh($course);
        $second = $this->sync()->ensureFresh($course->fresh());

        $this->assertNotNull($first);
        $this->assertNull($second); // già allineato → nessuna nuova riga
        $this->assertDatabaseCount('course_sources', 1);
    }

    public function test_rigenera_una_nuova_versione_quando_i_moduli_cambiano(): void
    {
        $this->skipIfNoPandoc();
        $course = $this->course();
        $m = $this->module($course, '<h1>Capitolo 1</h1><p>Nel 2024 il mercato vale 1 miliardo.</p>', 0);

        $first = $this->sync()->ensureFresh($course);
        $m->update(['content' => '<h1>Capitolo 1</h1><p>Nel 2026 il mercato vale 2,3 miliardi.</p>']);
        // course_sources.created_at ha precisione al SECONDO: due versioni create nello
        // stesso secondo rendono ambiguo l'orderByDesc(created_at) di sources(). In
        // esercizio i run sono distanti minuti; qui il salto rende il test deterministico.
        $this->travel(1)->second();
        $second = $this->sync()->ensureFresh($course->fresh());

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNotSame($first->version, $second->version);
        $this->assertDatabaseCount('course_sources', 2);
        // loadSource dell'agente prende l'ULTIMO (created_at desc) → il fresco.
        $this->assertSame($second->version, $course->fresh()->sources()->first()->version);
    }

    public function test_salta_i_corsi_con_manuale_formatore(): void
    {
        $this->skipIfNoPandoc();
        $course = $this->course();
        $this->module($course, '<h1>Capitolo 1</h1><p>Testo databile del 2024.</p>', 0);
        Material::create([
            'course_id' => $course->id, 'title' => 'Manuale formatore',
            'file_path' => 'manuale.pdf', 'file_type' => 'pdf',
            'is_instructor_only' => true, 'sort_order' => 0,
        ]);

        $src = $this->sync()->ensureFresh($course);

        $this->assertNull($src); // il manuale è la fonte di verità: non lo scavalchiamo
        $this->assertDatabaseCount('course_sources', 0);
    }

    public function test_null_se_i_moduli_sono_vuoti(): void
    {
        $this->skipIfNoPandoc();
        $course = $this->course(); // nessun modulo

        $this->assertNull($this->sync()->ensureFresh($course));
        $this->assertDatabaseCount('course_sources', 0);
    }
}
