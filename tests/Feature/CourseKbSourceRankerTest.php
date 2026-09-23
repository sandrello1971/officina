<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\DocumentRag;
use App\Models\Material;
use App\Services\CourseGeneration\CourseKbSourceRanker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Motore generazione corsi da KB — ranking dei documenti già presenti sul
 * corso per pertinenza all'argomento (triage per l'umano, non una risposta
 * esatta: niente AI, niente retrieval vettoriale).
 */
class CourseKbSourceRankerTest extends TestCase
{
    use RefreshDatabase;

    public function test_marca_suggerito_solo_il_documento_pertinente(): void
    {
        $course = Course::create(['name' => 'Corso test', 'slug' => 'corso-' . Str::lower(Str::random(8)), 'is_active' => true]);

        DocumentRag::create([
            'course_id' => $course->id,
            'title' => 'Sicurezza sul lavoro',
            'content' => 'La sicurezza sul lavoro richiede DPI adeguati e formazione.',
            'chunk_index' => 0,
        ]);
        Material::create([
            'course_id' => $course->id,
            'module_id' => null,
            'title' => 'Ricette di cucina',
            'content_html' => '<p>Testo estraneo, non pertinente.</p>',
        ]);

        $ranked = (new CourseKbSourceRanker())->rank($course, 'Sicurezza sul lavoro');

        $this->assertCount(2, $ranked);
        $this->assertSame('Sicurezza sul lavoro', $ranked[0]['title']);
        $this->assertTrue($ranked[0]['suggested']);
        $this->assertSame('Ricette di cucina', $ranked[1]['title']);
        $this->assertFalse($ranked[1]['suggested']);
    }
}
