<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Material;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Manuali formatore il cui file importato non è più su disco: il download
 * usa il contenuto salvato (quello che il formatore legge a schermo).
 */
class InstructorManualDownloadFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(array $material): array
    {
        $course = Course::create(['name' => 'Corso', 'slug' => 'corso-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        $instructor = Student::create([
            'name' => 'Formatore', 'email' => 'f' . uniqid() . '@example.com', 'password' => bcrypt('x'),
            'role' => 'instructor', 'auto_enroll_all_courses' => true, 'is_active' => true, 'must_change_password' => false,
        ]);
        $m = Material::create(array_merge([
            'course_id' => $course->id, 'title' => 'Manuale Formatore', 'is_instructor_only' => true, 'sort_order' => 1,
            'file_path' => 'instructor-manuals/non-esiste/manuale.md',
        ], $material));

        return [$course, $m, ['student_id' => $instructor->id, 'student_email' => $instructor->email, 'student_name' => $instructor->name]];
    }

    public function test_file_mancante_scarica_il_contenuto_salvato_come_docx(): void
    {
        [$course, $m, $session] = $this->scenario(['content_html' => '<h1>Capitolo 1</h1><p>Testo del manuale.</p>']);

        $res = $this->withSession($session)->get($this->learnUrl("/course/{$course->slug}/instructor/{$m->id}/download"));

        $res->assertOk();
        $this->assertStringContainsString('Manuale Formatore.docx', (string) $res->headers->get('content-disposition'));
        // Un .docx è uno zip: inizia con "PK".
        $this->assertStringStartsWith('PK', file_get_contents($res->baseResponse->getFile()->getPathname()));
    }

    public function test_senza_file_e_senza_contenuto_resta_404(): void
    {
        [$course, $m, $session] = $this->scenario(['content_html' => null]);

        $this->withSession($session)->get($this->learnUrl("/course/{$course->slug}/instructor/{$m->id}/download"))
            ->assertNotFound();
    }
}
