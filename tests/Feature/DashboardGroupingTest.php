<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\School;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate 3 — raggruppamento dashboard studente per categoria (con fallback "Altri
 * corsi") e smoke del nuovo rail a icone sui tre layout studente/docente/scuola.
 */
class DashboardGroupingTest extends TestCase
{
    use RefreshDatabase;

    private function student(array $attrs = []): Student
    {
        return Student::create(array_merge([
            'name' => 'Discente ' . uniqid(),
            'email' => 'd' . uniqid() . '@e.it',
            'password' => bcrypt('secret-pw'),
            'is_active' => true,
            'is_demo' => false,
            'must_change_password' => false,
        ], $attrs));
    }

    private function course(string $name, ?CourseCategory $cat = null): Course
    {
        return Course::create([
            'name' => $name,
            'slug' => \Str::slug($name) . '-' . uniqid(),
            'is_active' => true,
            'sort_order' => 1,
            'course_category_id' => $cat?->id,
        ]);
    }

    private function enroll(Student $s, Course $c): void
    {
        $s->courses()->attach($c->id, ['enrolled_at' => now(), 'is_active' => true]);
    }

    private function asStudent(Student $s): self
    {
        return $this->withSession([
            'student_id' => $s->id, 'student_name' => $s->name, 'student_email' => $s->email,
        ]);
    }

    public function test_uncategorized_courses_fall_back_and_dashboard_shows_all(): void
    {
        $student = $this->student();
        $c1 = $this->course('Corso Alfa');
        $c2 = $this->course('Corso Beta');
        $this->enroll($student, $c1);
        $this->enroll($student, $c2);

        // Il grouping vive nella pagina "I miei corsi" (/learn/corsi), non più nella dashboard.
        $res = $this->asStudent($student)->get(route('student.courses.index'));

        $res->assertOk();
        $res->assertSee('Corso Alfa');
        $res->assertSee('Corso Beta');
        // Fallback: gruppo unico "Altri corsi" → nessuna intestazione di categoria.
        $res->assertDontSee('Altri corsi');
    }

    public function test_two_categories_group_with_two_keys_and_headings(): void
    {
        $student = $this->student();
        $sicurezza = CourseCategory::create(['name' => 'Sicurezza', 'slug' => 'sicurezza-' . uniqid()]);
        $qualita = CourseCategory::create(['name' => 'Qualita', 'slug' => 'qualita-' . uniqid()]);

        $c1 = $this->course('Corso Antincendio', $sicurezza);
        $c2 = $this->course('Corso ISO 9001', $qualita);
        $this->enroll($student, $c1);
        $this->enroll($student, $c2);

        $res = $this->asStudent($student)->get(route('student.courses.index'));

        $res->assertOk();
        // Due gruppi → le due intestazioni di categoria compaiono.
        $res->assertSee('Sicurezza');
        $res->assertSee('Qualita');
        $res->assertSee('Corso Antincendio');
        $res->assertSee('Corso ISO 9001');
    }

    public function test_dashboard_is_an_overview_with_stats(): void
    {
        $student = $this->student();
        $this->enroll($student, $this->course('Corso Rail'));

        $res = $this->asStudent($student)->get(route('student.dashboard'));

        $res->assertOk();
        // Dashboard = panoramica con KPI (non più l'elenco corsi raggruppato).
        $res->assertSee('Corsi attivi');
        $res->assertSee('Progresso medio');
        // Topbar (Direzione A): classe .topbar + brand.
        $res->assertSee('class="topbar"', false);
        $res->assertSee('topbar-brand', false);
        // Il contratto composer resta: gli id badge per lo script Reverb.
        $res->assertSee('sidebar-unread-badge', false);
        $res->assertSee('sidebar-announcements-badge', false);
    }

    public function test_docente_dashboard_renders_200_with_rail(): void
    {
        $prof = $this->student(['role' => 'professor']);

        $res = $this->asStudent($prof)->get(route('docente.dashboard'));

        $res->assertOk();
        $res->assertSee('class="topbar"', false);
    }

    public function test_scuola_dashboard_renders_200_with_rail(): void
    {
        $school = School::create(['name' => 'Liceo Test', 'slug' => 'liceo-' . uniqid(), 'type' => 'liceo', 'status' => 'active']);
        $secretary = $this->student(['role' => null, 'is_secretary' => true, 'school_id' => $school->id]);

        $res = $this->asStudent($secretary)->get(route('scuola.dashboard'));

        $res->assertOk();
        $res->assertSee('class="topbar"', false);
    }
}
