<?php

namespace Tests\Feature\Attendance;

use App\Models\Course;
use App\Models\CourseEdition;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstructorAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private function asUser(Student $s): self
    {
        return $this->withSession([
            'student_id' => $s->id, 'student_email' => $s->email, 'student_name' => $s->name,
        ]);
    }

    private function course(): Course
    {
        return Course::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    public function test_formatore_del_corso_accede_a_edizioni_e_registro(): void
    {
        $course = $this->course();
        $instructor = Student::create(['name' => 'Doc', 'email' => 'd' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'is_active' => true, 'role' => 'instructor', 'must_change_password' => false]);
        $instructor->taughtCourses()->attach($course->id);

        $this->asUser($instructor)->get(route('student.course.editions.index', $course->slug))->assertOk();
        $this->asUser($instructor)->get(route('student.course.register', $course->slug))->assertOk();
    }

    public function test_studente_non_formatore_riceve_403(): void
    {
        $course = $this->course();
        $altro = $this->course();
        $instructor = Student::create(['name' => 'Doc', 'email' => 'd' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'is_active' => true, 'role' => 'instructor', 'must_change_password' => false]);
        $instructor->taughtCourses()->attach($altro->id); // insegna un ALTRO corso

        $this->asUser($instructor)->get(route('student.course.editions.index', $course->slug))->assertForbidden();

        $studente = Student::create(['name' => 'Stu', 'email' => 's' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'is_active' => true, 'role' => 'student', 'must_change_password' => false]);
        $studente->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);
        $this->asUser($studente)->get(route('student.course.register', $course->slug))->assertForbidden();
    }

    public function test_formatore_crea_edizione_e_fa_appello(): void
    {
        $course = $this->course();
        $instructor = Student::create(['name' => 'Doc', 'email' => 'd' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'is_active' => true, 'role' => 'instructor', 'must_change_password' => false]);
        $instructor->taughtCourses()->attach($course->id);
        $discente = Student::create(['name' => 'Anna', 'email' => 'a' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'is_active' => true, 'role' => 'student', 'must_change_password' => false]);

        $this->asUser($instructor)->post(route('student.course.editions.store', $course->slug), [
            'name' => 'Ottobre', 'modality' => 'in_person', 'first_date' => '2026-10-05',
            'days' => 2, 'start_time' => '09:00', 'hours_per_day' => 4, 'weekdays' => [1, 2, 3, 4, 5],
        ])->assertRedirect();

        $edition = CourseEdition::where('course_id', $course->id)->firstOrFail();
        $this->asUser($instructor)->post(route('student.course.editions.students.store', [$course->slug, $edition]), [
            'student_ids' => [$discente->id],
        ])->assertRedirect();

        $day = $edition->days()->firstOrFail();
        $this->asUser($instructor)->post(route('student.course.editions.mark', [$course->slug, $edition, $day]), [
            'marks' => [$discente->id => ['status' => 'presente', 'arrived_at' => '09:30']],
        ])->assertRedirect();

        $this->assertDatabaseHas('attendance_records', [
            'course_session_id' => $day->id, 'student_id' => $discente->id,
            'source' => 'instructor_mark', 'status' => 'presente', 'hours_credited' => 3.5,
            'marked_by' => $instructor->email,
        ]);
        $this->asUser($instructor)->get(route('student.course.editions.show', [$course->slug, $edition]))->assertOk()->assertSee('R 09:30');
    }
}
