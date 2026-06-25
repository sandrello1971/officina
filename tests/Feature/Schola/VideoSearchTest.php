<?php

namespace Tests\Feature\Schola;

use App\Models\ClassStudent;
use App\Models\Lesson;
use App\Models\LessonPresentation;
use App\Models\LessonPublication;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * R4 — ricerca PER-VIDEO nel player del discente. Proxy a videoai /api/search sul
 * video_ai_id del video pubblicato; clic → seek (start). videoai FAKEATO (l'e2e reale
 * — generato e caricato — si farà al deploy con l'istanza videoai che ha index_chunks).
 */
class VideoSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.videoai.url' => 'http://127.0.0.1:8001', 'services.videoai.token' => 'tok']);
    }

    /** Classe + lezione pubblicata + studente attivo + video pubblicato/indicizzato. */
    private function scenario(array $videoAttrs = []): array
    {
        $prof = Student::create(['name' => 'P', 'email' => 'p' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'role' => 'professor', 'is_active' => true, 'must_change_password' => false]);
        $subject = Subject::firstOrCreate(['name' => 'Storia']);
        $topic = Topic::create(['teacher_id' => $prof->id, 'subject_id' => $subject->id, 'name' => 'T', 'position' => 0]);
        $lesson = Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $prof->id, 'title' => 'Le cause', 'position' => 0, 'generation_status' => 'ready', 'content' => '## x']);
        $pres = LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'ready', 'published_at' => now(), 'spec' => ['slides' => [['title' => 'A']]]]);
        $video = $lesson->videos()->create(array_merge([
            'presentation_id' => $pres->id, 'status' => 'ready', 'script_status' => 'confirmed',
            'file_path' => "lesson-videos/{$lesson->id}/v.mp4",
            'script' => [['slide_number' => 1, 'text' => 'Benvenuti']],
            'generation_meta' => ['slide_timings' => [['slide_number' => 1, 'start_sec' => 0, 'end_sec' => 4]]],
            'video_ai_id' => 'gen_lessonvideo_test', 'indexed_at' => now(), 'published_at' => now(),
        ], $videoAttrs));
        $class = SchoolClass::create(['teacher_id' => $prof->id, 'name' => '3A', 'subject_id' => $subject->id, 'school_year' => '2026/2027',
            'invite_code' => SchoolClass::generateInviteCode(), 'invite_enabled' => true, 'requires_approval' => false, 'is_archived' => false]);
        $student = Student::create(['name' => 'S', 'email' => 's' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'role' => 'student', 'is_active' => true, 'must_change_password' => false]);
        LessonPublication::create(['lesson_id' => $lesson->id, 'school_class_id' => $class->id, 'students_can_generate' => true, 'rag_status' => 'ready', 'published_at' => now()]);

        return compact('lesson', 'video', 'class', 'student');
    }

    private function asStudent(Student $s): self
    {
        return $this->withSession(['student_id' => $s->id, 'student_name' => $s->name, 'student_email' => $s->email]);
    }

    private function enroll($class, $student): void
    {
        ClassStudent::create(['school_class_id' => $class->id, 'student_id' => $student->id, 'status' => 'active', 'approved_at' => now()]);
    }

    public function test_ricerca_ritorna_match_per_questo_video_con_timestamp(): void
    {
        Http::fake(['*/api/search' => Http::response([[
            'video_id' => 'gen_lessonvideo_test', 'relevance' => 0.82,
            'matches' => [['timestamp_str' => '0:04', 'text' => 'Parliamo di attrezzi', 'start' => 4.0, 'type' => 'frame']],
        ]], 200)]);
        ['lesson' => $lesson, 'class' => $class, 'student' => $student] = $this->scenario();
        $this->enroll($class, $student);

        $res = $this->asStudent($student)->postJson(route('student.classes.lesson.video.search', [$class, $lesson]), ['q' => 'cacciavite']);
        $res->assertOk()->assertJsonPath('matches.0.text', 'Parliamo di attrezzi')->assertJsonPath('matches.0.type', 'frame');
        $this->assertEqualsWithDelta(4.0, $res->json('matches.0.start'), 0.01); // start per il seek

        // PER-VIDEO: la ricerca interroga SOLO il video_ai_id corrente.
        Http::assertSent(fn ($r) => str_contains($r->url(), '/api/search')
            && $r->data()['video_ids'] === ['gen_lessonvideo_test']
            && $r->hasHeader('X-Internal-Token', 'tok'));
    }

    public function test_gate_non_iscritto_vietato(): void
    {
        Http::fake();
        ['lesson' => $lesson, 'class' => $class, 'student' => $student] = $this->scenario(); // NON iscritto

        $this->asStudent($student)->postJson(route('student.classes.lesson.video.search', [$class, $lesson]), ['q' => 'x'])
            ->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_video_non_pubblicato_niente_ricerca(): void
    {
        Http::fake();
        ['lesson' => $lesson, 'class' => $class, 'student' => $student] = $this->scenario(['published_at' => null]);
        $this->enroll($class, $student);

        $this->asStudent($student)->postJson(route('student.classes.lesson.video.search', [$class, $lesson]), ['q' => 'x'])
            ->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_query_vuota_422(): void
    {
        Http::fake();
        ['lesson' => $lesson, 'class' => $class, 'student' => $student] = $this->scenario();
        $this->enroll($class, $student);

        $this->asStudent($student)->postJson(route('student.classes.lesson.video.search', [$class, $lesson]), ['q' => '  '])
            ->assertStatus(422);
        Http::assertNothingSent();
    }

    public function test_nessun_riscontro_lista_vuota(): void
    {
        Http::fake(['*/api/search' => Http::response([], 200)]); // videoai: nessun video rilevante
        ['lesson' => $lesson, 'class' => $class, 'student' => $student] = $this->scenario();
        $this->enroll($class, $student);

        $this->asStudent($student)->postJson(route('student.classes.lesson.video.search', [$class, $lesson]), ['q' => 'xyz'])
            ->assertOk()->assertJsonPath('matches', []);
    }
}
