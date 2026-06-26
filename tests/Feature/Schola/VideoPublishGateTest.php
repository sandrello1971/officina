<?php

namespace Tests\Feature\Schola;

use App\Jobs\GenerateVideoJob;
use App\Models\ClassStudent;
use App\Models\Lesson;
use App\Models\LessonPresentation;
use App\Models\LessonPublication;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\Schola\VideoRenderService;
use App\Services\Schola\VideoScriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * R3 — gate "pubblicabile = interrogabile". Per i video GENERATI il publish indicizza
 * (R2, gratis) e poi pubblica: nessun video pubblicato senza indice valido. Invalidazione
 * coerente (re-render/edit copione → spubblicato + indice stale). videoai fakeato.
 */
class VideoPublishGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.videoai.url' => 'http://127.0.0.1:8001', 'services.videoai.token' => 'tok']);
    }

    private function prof(): Student
    {
        return Student::create(['name' => 'P', 'email' => 'p' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'is_active' => true, 'must_change_password' => false]);
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    /** Lezione + presentazione pubblicata (spec) + video ready con copione + slide_timings. */
    private function scenario(array $videoAttrs = []): array
    {
        $prof = $this->prof();
        $topic = Topic::create(['teacher_id' => $prof->id, 'subject_id' => Subject::firstOrCreate(['name' => 'Storia'])->id, 'name' => 'T', 'position' => 0]);
        $lesson = Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $prof->id, 'title' => 'Le cause', 'position' => 0, 'generation_status' => 'ready', 'content' => '## x']);
        $pres = LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'ready', 'published_at' => now(),
            'spec' => ['slides' => [['layout' => 'cover', 'title' => 'Intro'], ['layout' => 'bullets_clean', 'title' => 'A', 'bullets' => ['x']]]]]);
        $video = $lesson->videos()->create(array_merge([
            'presentation_id' => $pres->id, 'status' => 'ready', 'script_status' => 'confirmed',
            'file_path' => "lesson-videos/{$lesson->id}/v.mp4",
            'script' => [['slide_number' => 1, 'text' => 'Benvenuti'], ['slide_number' => 2, 'text' => 'Punto A']],
            'generation_meta' => ['seconds' => 10, 'slide_timings' => [
                ['slide_number' => 1, 'start_sec' => 0, 'end_sec' => 4],
                ['slide_number' => 2, 'start_sec' => 4, 'end_sec' => 10],
            ]],
        ], $videoAttrs));

        return [$prof, $lesson, $pres, $video];
    }

    public function test_publish_generato_indicizza_e_pubblica(): void
    {
        Http::fake(['*/index_chunks' => Http::response(['indexed_chunks' => 4], 200)]);
        [$prof, $lesson, , $video] = $this->scenario();

        $this->asProf($prof)->post(route('docente.lessons.video.publish', $lesson))->assertRedirect();
        $video->refresh();

        $this->assertNotNull($video->indexed_at, 'indicizzato');
        $this->assertNotNull($video->published_at, 'pubblicato');
        $this->assertNotNull($video->video_ai_id);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/index_chunks'));
    }

    public function test_publish_bloccato_se_indicizzazione_fallisce(): void
    {
        Http::fake(['*/index_chunks' => Http::response('down', 500)]);
        [$prof, $lesson, , $video] = $this->scenario();

        $this->asProf($prof)->post(route('docente.lessons.video.publish', $lesson))
            ->assertRedirect()->assertSessionHas('error');
        $video->refresh();

        // INVARIANTE: nessun video pubblicato senza indice valido.
        $this->assertNull($video->published_at, 'NON pubblicato');
        $this->assertNull($video->indexed_at);
    }

    public function test_gia_indicizzato_non_richiama_videoai(): void
    {
        Http::fake(); // se chiamasse videoai fallirebbe (nessuna risposta finta utile)
        [$prof, $lesson, , $video] = $this->scenario(['video_ai_id' => 'gen_lessonvideo_x', 'indexed_at' => now()]);

        $this->asProf($prof)->post(route('docente.lessons.video.publish', $lesson))->assertRedirect();

        $this->assertNotNull($video->refresh()->published_at);
        Http::assertNothingSent(); // già indicizzato → nessuna nuova indicizzazione
    }

    public function test_edit_copione_invalida_indice_e_pubblicazione(): void
    {
        Storage::fake('local');
        [, , , $video] = $this->scenario(['published_at' => now(), 'indexed_at' => now()]);
        Storage::disk('local')->put($video->file_path, 'MP4');

        (new VideoScriptService())->editLine($video, 1, 'Benvenuti aggiornato');
        $video->refresh();

        $this->assertNull($video->published_at, 'spubblicato');
        $this->assertNull($video->indexed_at, 'indice stale');
        $this->assertSame('pending', $video->status);
        $this->assertSame('draft', $video->script_status);
    }

    public function test_render_invalida_indice_e_pubblicazione(): void
    {
        [, , , $video] = $this->scenario(['published_at' => now(), 'indexed_at' => now()]);

        $this->mock(VideoRenderService::class, function ($m) use ($video) {
            $m->shouldReceive('render')->once()->andReturn([
                'file_path' => $video->file_path,
                'meta' => ['seconds' => 12, 'slide_timings' => []],
            ]);
        });

        (new GenerateVideoJob($video->id, 'lesson'))->handle(app(VideoRenderService::class));
        $video->refresh();

        $this->assertSame('ready', $video->status);
        $this->assertNull($video->indexed_at, 'nuovo mp4 → indice stale');
        $this->assertNull($video->published_at, 'nuovo mp4 → spubblicato');
    }

    public function test_studente_vede_solo_video_pubblicato(): void
    {
        Storage::fake('local');
        [$prof, $lesson, $pres, $video] = $this->scenario();
        Storage::disk('local')->put($video->file_path, 'MP4');
        $class = SchoolClass::create(['teacher_id' => $prof->id, 'name' => '3A', 'subject_id' => Subject::firstOrCreate(['name' => 'Storia'])->id,
            'school_year' => '2026/2027', 'invite_code' => SchoolClass::generateInviteCode(), 'invite_enabled' => true, 'requires_approval' => false, 'is_archived' => false]);
        $student = Student::create(['name' => 'S', 'email' => 's' . uniqid() . '@e.it', 'password' => bcrypt('x'), 'role' => 'student', 'is_active' => true, 'must_change_password' => false]);
        ClassStudent::create(['school_class_id' => $class->id, 'student_id' => $student->id, 'status' => 'active', 'approved_at' => now()]);
        LessonPublication::create(['lesson_id' => $lesson->id, 'school_class_id' => $class->id, 'students_can_generate' => true, 'rag_status' => 'ready', 'published_at' => now()]);
        $asStu = fn () => $this->withSession(['student_id' => $student->id, 'student_name' => $student->name, 'student_email' => $student->email]);

        // bozza → non visibile
        $asStu()->get(route('student.classes.lesson.video', [$class, $lesson]))->assertNotFound();

        // pubblicato → visibile (stream)
        $video->update(['published_at' => now()]);
        $asStu()->get(route('student.classes.lesson.video', [$class, $lesson]))->assertOk();
    }
}
