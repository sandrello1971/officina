<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\DocumentRag;
use App\Models\Material;
use App\Services\CourseGeneration\CourseSourceAggregator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Motore generazione corsi da KB — l'aggregator unifica DocumentRag ("Documenti
 * AI", chunk sovrapposti da ricomporre) e Material ("Materiali Formatore"), con
 * filtro opzionale sulla selezione confermata dal formatore.
 */
class CourseSourceAggregatorTest extends TestCase
{
    use RefreshDatabase;

    private function makeCourse(): Course
    {
        return Course::create(['name' => 'Corso test', 'slug' => 'corso-' . Str::lower(Str::random(8)), 'is_active' => true]);
    }

    public function test_ricompone_i_chunk_sovrapposti_senza_duplicare_testo(): void
    {
        $course = $this->makeCourse();

        // Simula esattamente RagService::chunkText(testo, 1000, 200): chunk1 =
        // primi 1000 char, chunk2 riparte da offset 800 (quindi condivide gli
        // ultimi 200 char di chunk1).
        $chunk0 = str_repeat('A', 800) . str_repeat('X', 200); // 1000 char
        $chunk1 = str_repeat('X', 200) . str_repeat('B', 600); // gli ultimi 200 di chunk0 + nuovo testo

        DocumentRag::create(['course_id' => $course->id, 'title' => 'Manuale', 'content' => $chunk0, 'chunk_index' => 0]);
        DocumentRag::create(['course_id' => $course->id, 'title' => 'Manuale', 'content' => $chunk1, 'chunk_index' => 1]);

        $text = (new CourseSourceAggregator())->aggregate($course);

        // Ricomposizione attesa: 800 A + 200 X + 600 B = 1600 char di contenuto
        // (più l'header "## Manuale\n"), senza la doppia X ripetuta due volte.
        $this->assertSame(1, substr_count($text, str_repeat('X', 200)));
        $this->assertStringContainsString(str_repeat('A', 800) . str_repeat('X', 200) . str_repeat('B', 600), $text);
    }

    public function test_filtro_selezione_esclude_le_fonti_non_scelte(): void
    {
        $course = $this->makeCourse();

        DocumentRag::create(['course_id' => $course->id, 'title' => 'Incluso', 'content' => 'testo incluso', 'chunk_index' => 0]);
        DocumentRag::create(['course_id' => $course->id, 'title' => 'Escluso', 'content' => 'testo escluso', 'chunk_index' => 0]);
        $material = Material::create(['course_id' => $course->id, 'module_id' => null, 'title' => 'Materiale incluso', 'content_html' => '<p>materiale incluso</p>']);
        Material::create(['course_id' => $course->id, 'module_id' => null, 'title' => 'Materiale escluso', 'content_html' => '<p>materiale escluso</p>']);

        $text = (new CourseSourceAggregator())->aggregate($course, [
            ['source_type' => 'document_rag', 'title' => 'Incluso'],
            ['source_type' => 'material', 'id' => $material->id],
        ]);

        $this->assertStringContainsString('Incluso', $text);
        $this->assertStringContainsString('Materiale incluso', $text);
        $this->assertStringNotContainsString('Escluso', $text);
        $this->assertStringNotContainsString('Materiale escluso', $text);
    }

    public function test_nessun_filtro_usa_tutta_la_kb(): void
    {
        $course = $this->makeCourse();
        DocumentRag::create(['course_id' => $course->id, 'title' => 'Uno', 'content' => 'contenuto uno', 'chunk_index' => 0]);
        Material::create(['course_id' => $course->id, 'module_id' => null, 'title' => 'Due', 'content_html' => '<p>contenuto due</p>']);

        $text = (new CourseSourceAggregator())->aggregate($course);

        $this->assertStringContainsString('contenuto uno', $text);
        $this->assertStringContainsString('contenuto due', $text);
    }
}
