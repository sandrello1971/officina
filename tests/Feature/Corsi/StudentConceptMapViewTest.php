<?php

namespace Tests\Feature\Corsi;

use App\Models\Course;
use App\Models\Module;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentConceptMapViewTest extends TestCase
{
    use RefreshDatabase;

    /** Il discente apre una mappa concettuale pubblicata: la vista deve renderizzare (no 500). */
    public function test_discente_apre_mappa_concettuale_senza_errore(): void
    {
        $course = Course::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'M', 'sort_order' => 0, 'content' => '<p>x</p>', 'is_active' => true]);
        $student = Student::create(['name' => 'S', 'email' => 's' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'is_active' => true, 'must_change_password' => false]);
        $course->students()->attach($student->id, ['enrolled_at' => now(), 'is_active' => true]);

        $map = $course->conceptMaps()->create([
            'module_id' => $module->id, 'title' => 'Mappa', 'visibility' => 'published',
            'ai_generated' => true, 'sort_order' => 0,
            'data' => [
                'nodes' => [['id' => 'n1', 'label' => 'A'], ['id' => 'n2', 'label' => 'B']],
                'edges' => [['id' => 'e1', 'from' => 'n1', 'to' => 'n2', 'label' => 'r']],
            ],
        ]);

        $this->withSession(['student_id' => $student->id, 'student_email' => $student->email, 'student_name' => $student->name])
            ->get(route('student.course.concept-map.show', [$course->slug, $map]))
            ->assertOk()
            ->assertSee('Mappe del corso'); // il link con la rotta prima rotta (index)
    }
}
