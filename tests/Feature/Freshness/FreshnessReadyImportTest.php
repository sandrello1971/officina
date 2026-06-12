<?php

namespace Tests\Feature\Freshness;

use App\Models\Course;
use App\Models\CourseChangelog;
use App\Models\CourseSource;
use App\Models\InstructorManualSection;
use App\Models\Material;
use App\Services\CourseSourceExtractor;
use App\Services\InstructorManualService;
use App\Services\InstructorManualSplitterService;
use App\Services\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * F-a — Aggancio dell'estrazione course_sources all'import del manuale formatore
 * (InstructorManualService.import / regenerateHtml). Caso semplice: corso SENZA storia
 * di apply. Append-only: v1.0 se assente, bump MAGGIORE se pristino, skip se ha storia
 * (rinviato a F-b). 0 blocchi o eccezione NON rompono l'import del manuale (additività).
 *
 * I test che convertono il .docx richiedono pandoc → auto-skip se assente (come
 * CourseSourceRecoverTest). Lo splitter lavora su content_html (no pandoc).
 */
class FreshnessReadyImportTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): string
    {
        return base_path('tests/Fixtures/p25/mini-course.docx');
    }

    private function requirePandoc(): void
    {
        try {
            app(CourseSourceExtractor::class)->assertPandocAvailable();
        } catch (\RuntimeException $e) {
            $this->markTestSkipped('pandoc non disponibile: ' . $e->getMessage());
        }
    }

    private function makeCourse(): Course
    {
        return Course::create([
            'name' => 'INTERFERENZA', 'slug' => 'c-' . uniqid(),
            'is_active' => true, 'sort_order' => 1,
        ]);
    }

    /** Servizio con RagService neutralizzato (niente embeddings HTTP nei test). */
    private function service(CourseSourceExtractor $extractor): InstructorManualService
    {
        $rag = Mockery::mock(RagService::class);
        $rag->shouldReceive('indexDocument')->andReturnNull();

        return new InstructorManualService($rag, app(InstructorManualSplitterService::class), $extractor);
    }

    /** Estrattore controllabile: registra se è stato chiamato, restituisce blocchi fissi o lancia. */
    private function fakeExtractor(array $blocks = [], bool $shouldThrow = false): CourseSourceExtractor
    {
        return new class($blocks, $shouldThrow) extends CourseSourceExtractor {
            public bool $called = false;
            public function __construct(public array $blocksToReturn, public bool $shouldThrow) {}
            public function extractFromDocx(string $docxPath): array
            {
                $this->called = true;
                if ($this->shouldThrow) {
                    throw new \RuntimeException('estrazione fallita (test)');
                }
                return ['blocks' => $this->blocksToReturn, 'warnings' => [], 'frontmatter' => []];
            }
        };
    }

    // ---- Gate 1: corso nuovo → sezioni (splitter) E course_sources v1.0 ----

    public function test_corso_nuovo_genera_sezioni_e_sorgente_v1(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->makeCourse();

        $this->service(app(CourseSourceExtractor::class))
            ->import($this->fixture(), $course, 'Manuale formatore');

        // splitter: sezioni del manuale create accanto
        $this->assertGreaterThan(0, InstructorManualSection::where('course_id', $course->id)->count());
        // F-a: sorgente strutturato alla prima versione
        $this->assertDatabaseHas('course_sources', ['course_id' => $course->id, 'version' => '1.0']);
        $this->assertCount(9, CourseSource::where('course_id', $course->id)->first()->blocks);
    }

    // ---- Gate 2: corso pristino con sorgente, senza apply → bump MAGGIORE, vecchia preservata ----

    public function test_corso_pristino_riimport_fa_bump_maggiore(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->makeCourse();
        CourseSource::create([
            'course_id' => $course->id, 'version' => '1.0',
            'blocks' => [['id' => 'x', 'type' => 'P', 'text' => 'vecchio']],
        ]);

        $this->service(app(CourseSourceExtractor::class))
            ->import($this->fixture(), $course, 'Manuale formatore');

        // append-only: la v1.0 resta, nasce la v2.0 (bump maggiore)
        $this->assertDatabaseHas('course_sources', ['course_id' => $course->id, 'version' => '1.0']);
        $this->assertDatabaseHas('course_sources', ['course_id' => $course->id, 'version' => '2.0']);
        $this->assertSame(2, CourseSource::where('course_id', $course->id)->count());
        // corrente = ultima per created_at/id = 2.0, coi blocchi del docx
        $current = CourseSource::where('course_id', $course->id)
            ->orderByDesc('created_at')->orderByDesc('id')->first();
        $this->assertSame('2.0', $current->version);
        $this->assertCount(9, $current->blocks);
    }

    // ---- Gate 3: corso CON storia di apply → estrazione SALTATA (rinviata a F-b), import ok ----

    public function test_corso_con_storia_apply_salta_estrazione_ma_importa(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->makeCourse();
        CourseSource::create([
            'course_id' => $course->id, 'version' => '2.0',
            'blocks' => [['id' => 'x', 'type' => 'P', 'text' => 'live']],
        ]);
        CourseChangelog::create([
            'course_id' => $course->id, 'kind' => 'apply', 'content_source' => 'instructor',
            'version_from' => '1.0', 'version_to' => '2.0', 'summary' => 'aggiornamento agente',
        ]);

        $spy = $this->fakeExtractor([['id' => 'n', 'type' => 'P', 'text' => 'nuovo']]);
        $this->service($spy)->import($this->fixture(), $course, 'Manuale formatore');

        // estrazione mai eseguita, nessuna nuova versione
        $this->assertFalse($spy->called);
        $this->assertSame(1, CourseSource::where('course_id', $course->id)->count());
        $this->assertNull(CourseSource::where('course_id', $course->id)->where('version', '3.0')->first());
        // ma il manuale è stato importato comunque (Material + sezioni)
        $this->assertSame(1, Material::where('course_id', $course->id)->where('is_instructor_only', true)->count());
        $this->assertGreaterThan(0, InstructorManualSection::where('course_id', $course->id)->count());
    }

    // ---- Gate 4: 0 blocchi → sezioni sì, sorgente no, import non fallisce ----

    public function test_zero_blocchi_non_blocca_import(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->makeCourse();

        $this->service($this->fakeExtractor([]))
            ->import($this->fixture(), $course, 'Manuale formatore');

        $this->assertSame(0, CourseSource::where('course_id', $course->id)->count()); // sorgente NON generato
        $this->assertGreaterThan(0, InstructorManualSection::where('course_id', $course->id)->count()); // sezioni sì
        $this->assertSame(1, Material::where('course_id', $course->id)->count()); // manuale importato
    }

    // ---- Gate 5: eccezione estrattore → import del manuale comunque riuscito (additività) ----

    public function test_eccezione_estrattore_non_rompe_import(): void
    {
        $this->requirePandoc();
        Storage::fake('local');
        $course = $this->makeCourse();

        $material = $this->service($this->fakeExtractor([], true))
            ->import($this->fixture(), $course, 'Manuale formatore');

        $this->assertNotNull($material->id); // import riuscito nonostante l'eccezione
        $this->assertSame(0, CourseSource::where('course_id', $course->id)->count());
        $this->assertGreaterThan(0, InstructorManualSection::where('course_id', $course->id)->count());
    }
}
