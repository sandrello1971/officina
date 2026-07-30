<?php

namespace Tests\Feature\Schola;

use App\Models\ClassStudent;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonPublication;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "I miei corsi" mostra anche le LEZIONI pubblicate sulle classi Schola: lo
 * studente cercava lì il materiale del docente e trovava la pagina vuota,
 * perché i due mondi (Course di catalogo / Lesson di classe) non hanno legame
 * in tabella. Le card devono rispettare gli stessi gate di
 * StudentLessonController::show — mai una card che porta a 403/404.
 */
class ScholaLessonsInMyCoursesTest extends TestCase
{
    use RefreshDatabase;

    private function prof(): Student
    {
        return Student::create(['name' => 'Prof Rossi', 'email' => 'p' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'is_active' => true, 'must_change_password' => false]);
    }

    private function student(): Student
    {
        return Student::create(['name' => 'Stu', 'email' => 's' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'student', 'is_active' => true, 'must_change_password' => false]);
    }

    private function schoolClass(Student $teacher, string $name = '5AL'): SchoolClass
    {
        return SchoolClass::create(['teacher_id' => $teacher->id, 'name' => $name,
            'subject_id' => Subject::firstOrCreate(['name' => 'Scienze'])->id, 'school_year' => '2026/2027',
            'invite_code' => SchoolClass::generateInviteCode(), 'invite_enabled' => true,
            'requires_approval' => false, 'is_archived' => false]);
    }

    private function enroll(SchoolClass $class, Student $s, string $status = 'active'): void
    {
        ClassStudent::create(['school_class_id' => $class->id, 'student_id' => $s->id,
            'status' => $status, 'approved_at' => $status === 'active' ? now() : null]);
    }

    private function lesson(Student $prof, string $title = 'Biomolecole', array $attrs = []): Lesson
    {
        $topic = Topic::create(['teacher_id' => $prof->id, 'subject_id' => Subject::firstOrCreate(['name' => 'Scienze'])->id,
            'name' => 'Chimica organica', 'position' => 0]);

        return Lesson::create(array_merge([
            'topic_id' => $topic->id, 'teacher_id' => $prof->id, 'title' => $title, 'position' => 0,
            'generation_status' => 'ready', 'content' => 'Le biomolecole sono i mattoni della vita.',
        ], $attrs));
    }

    private function publish(Lesson $lesson, SchoolClass $class): LessonPublication
    {
        return LessonPublication::create(['lesson_id' => $lesson->id, 'school_class_id' => $class->id,
            'students_can_generate' => true, 'rag_status' => 'ready', 'published_at' => now()]);
    }

    private function asUser(Student $s): self
    {
        return $this->withSession(['student_id' => $s->id, 'student_name' => $s->name, 'student_email' => $s->email]);
    }

    public function test_published_class_lesson_appears_in_my_courses_without_any_enrollment(): void
    {
        $prof = $this->prof();
        $stu = $this->student();
        $class = $this->schoolClass($prof);
        $this->enroll($class, $stu);
        $lesson = $this->lesson($prof);
        $this->publish($lesson, $class);

        $this->assertSame(0, $stu->courses()->count(), 'nessuna iscrizione a corsi di catalogo');

        $res = $this->asUser($stu)->get('/learn/corsi')->assertOk();
        $res->assertSee('Biomolecole');
        $res->assertSee('Chimica organica');
        $res->assertSee('Classe 5AL &middot; Scienze', false);
        $res->assertSee(route('student.classes.lesson.show', [$class, $lesson]), false);
        $res->assertDontSee('Nessun corso attivo');
    }

    public function test_lesson_card_links_to_a_page_the_student_can_actually_open(): void
    {
        $prof = $this->prof();
        $stu = $this->student();
        $class = $this->schoolClass($prof);
        $this->enroll($class, $stu);
        $lesson = $this->lesson($prof);
        $this->publish($lesson, $class);

        $this->asUser($stu)
            ->get(route('student.classes.lesson.show', [$class, $lesson]))
            ->assertOk();
    }

    public function test_catalogue_courses_still_render_alongside_class_lessons(): void
    {
        $prof = $this->prof();
        $stu = $this->student();
        $class = $this->schoolClass($prof);
        $this->enroll($class, $stu);
        $this->publish($this->lesson($prof), $class);

        $course = Course::create(['name' => 'AI Literacy Essential', 'slug' => 'ail-' . uniqid(),
            'is_active' => true, 'sort_order' => 1]);
        $stu->courses()->attach($course->id, ['enrolled_at' => now(), 'is_active' => true]);

        $this->asUser($stu)->get('/learn/corsi')->assertOk()
            ->assertSee('Biomolecole')
            ->assertSee('AI Literacy Essential');
    }

    public function test_lessons_of_other_classes_and_pending_enrollments_are_not_listed(): void
    {
        $prof = $this->prof();
        $stu = $this->student();

        $mine = $this->schoolClass($prof, '5AL');
        $this->enroll($mine, $stu);

        // Classe con iscrizione solo "pending": il feed classe darebbe 403.
        $pending = $this->schoolClass($prof, '4BL');
        $this->enroll($pending, $stu, 'pending');
        $this->publish($this->lesson($prof, 'Lezione in attesa'), $pending);

        // Classe di un altro studente: mai visibile.
        $foreign = $this->schoolClass($prof, '1CL');
        $this->publish($this->lesson($prof, 'Lezione altrui'), $foreign);

        $this->asUser($stu)->get('/learn/corsi')->assertOk()
            ->assertDontSee('Lezione in attesa')
            ->assertDontSee('Lezione altrui')
            ->assertDontSee('Classe 4BL')
            ->assertDontSee('Classe 1CL');
    }

    public function test_unpublished_unready_and_trashed_lessons_are_not_listed(): void
    {
        $prof = $this->prof();
        $stu = $this->student();
        $class = $this->schoolClass($prof);
        $this->enroll($class, $stu);

        // Generata ma mai pubblicata sulla classe.
        $this->lesson($prof, 'Mai pubblicata');

        // Pubblicata ma generazione fallita (il caso reale in produzione).
        $failed = $this->lesson($prof, 'Generazione fallita', ['generation_status' => 'failed', 'content' => null]);
        $this->publish($failed, $class);

        // Pubblicata, poi cancellata dal docente: la publication resta orfana.
        $trashed = $this->lesson($prof, 'Cancellata dal docente');
        $this->publish($trashed, $class);
        $trashed->delete();

        $res = $this->asUser($stu)->get('/learn/corsi')->assertOk();
        $res->assertDontSee('Mai pubblicata');
        $res->assertDontSee('Generazione fallita');
        $res->assertDontSee('Cancellata dal docente');
        $res->assertSee('Nessun corso attivo');
    }
}
