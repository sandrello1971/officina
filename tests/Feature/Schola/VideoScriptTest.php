<?php

namespace Tests\Feature\Schola;

use App\Jobs\GenerateVideoScriptJob;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonPresentation;
use App\Models\LessonVideo;
use App\Models\Module;
use App\Models\ModulePresentation;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\Schola\VideoScriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

/**
 * V1 — generazione copione (Claude, per slide, draft). Solo testo: nessun
 * ElevenLabs/ffmpeg. Http fakeato: per ogni chunk ritorna una riga per ogni
 * "Slide N:" presente nel prompt → verifica copertura e chunking deterministici.
 */
class VideoScriptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.key' => 'test-key', 'services.pptx.model' => 'claude-sonnet-4-6']);
    }

    /** Fake Claude: estrae i numeri di slide dal prompt e ritorna una riga per ciascuno. */
    private function fakeClaude(): void
    {
        Http::fake(['api.anthropic.com/*' => function ($request) {
            $user = $request->data()['messages'][0]['content'] ?? '';
            preg_match_all('/Slide (\d+):/', $user, $m);
            $lines = array_map(fn ($n) => ['slide_number' => (int) $n, 'text' => "Narrazione slide {$n}"], $m[1]);

            return Http::response([
                'content' => [['text' => json_encode($lines)]],
                'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
            ], 200);
        }]);
    }

    private function specWithSlides(int $contentSlides): array
    {
        $slides = [['layout' => 'cover', 'title' => 'Copertina', 'subtitle' => 'Sub']];
        for ($i = 1; $i <= $contentSlides; $i++) {
            $slides[] = ['layout' => 'bullets_clean', 'title' => "Sezione {$i}", 'bullets' => ["punto {$i}a", "punto {$i}b"]];
        }

        return ['theme' => [], 'slides' => $slides];
    }

    private function prof(): Student
    {
        return Student::create(['name' => 'P', 'email' => 'p' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'is_active' => true, 'must_change_password' => false]);
    }

    private function lesson(Student $prof): Lesson
    {
        $topic = Topic::create(['teacher_id' => $prof->id, 'subject_id' => Subject::firstOrCreate(['name' => 'Storia'])->id, 'name' => 'T', 'position' => 0]);

        return Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $prof->id, 'title' => 'L', 'position' => 0, 'generation_status' => 'ready', 'content' => '## x']);
    }

    private function publishedPres(Lesson $lesson, ?array $spec): LessonPresentation
    {
        return LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'ready', 'published_at' => now(), 'spec' => $spec]);
    }

    private function video(Lesson $lesson, LessonPresentation $pres, array $attrs = []): LessonVideo
    {
        return $lesson->videos()->create(array_merge(['presentation_id' => $pres->id, 'status' => 'pending', 'script_status' => 'none'], $attrs));
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    // ===== Servizio =====

    public function test_genera_una_riga_per_ogni_slide(): void
    {
        $this->fakeClaude();
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $pres = $this->publishedPres($lesson, $this->specWithSlides(3)); // cover + 3 = 4
        $video = $this->video($lesson, $pres);

        $result = (new VideoScriptService())->generateScript($video);

        $this->assertCount(4, $result['script']);
        $this->assertSame([1, 2, 3, 4], array_column($result['script'], 'slide_number'));
        $this->assertSame('Narrazione slide 1', $result['script'][0]['text']);
        $this->assertFalse($result['cached']);
        $this->assertSame('video-script-v1', $result['meta']['prompt_version']);
    }

    public function test_chunking_copre_tutte_le_slide(): void
    {
        $this->fakeClaude();
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $pres = $this->publishedPres($lesson, $this->specWithSlides(14)); // cover + 14 = 15 → chunk 10 + 5
        $video = $this->video($lesson, $pres);

        $result = (new VideoScriptService())->generateScript($video);

        $this->assertCount(15, $result['script']);
        $this->assertSame(range(1, 15), array_column($result['script'], 'slide_number'));
        $this->assertSame(2, $result['meta']['chunk_count']);
        Http::assertSentCount(2); // una chiamata per chunk, nessun troncamento
    }

    public function test_cache_non_richiama_claude_se_invariato(): void
    {
        $this->fakeClaude();
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $pres = $this->publishedPres($lesson, $this->specWithSlides(2));
        $video = $this->video($lesson, $pres);

        $first = (new VideoScriptService())->generateScript($video);
        $video->update(['script' => $first['script'], 'script_status' => 'draft', 'generation_meta' => $first['meta']]);

        $this->fakeClaude(); // reset contatore
        $second = (new VideoScriptService())->generateScript($video->refresh());

        $this->assertTrue($second['cached']);
        Http::assertNothingSent();
    }

    public function test_senza_spec_lancia_eccezione(): void
    {
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $pres = $this->publishedPres($lesson, null); // niente spec
        $video = $this->video($lesson, $pres);

        $this->expectException(RuntimeException::class);
        (new VideoScriptService())->generateScript($video);
    }

    // ===== Job =====

    public function test_job_persiste_copione_draft(): void
    {
        $this->fakeClaude();
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $pres = $this->publishedPres($lesson, $this->specWithSlides(2));
        $video = $this->video($lesson, $pres, ['status' => 'generating']);

        (new GenerateVideoScriptJob($video->id, 'lesson'))->handle(app(VideoScriptService::class));
        $video->refresh();

        $this->assertSame('pending', $video->status);          // mp4 non ancora reso (V3)
        $this->assertSame('draft', $video->script_status);
        $this->assertCount(3, $video->script);                 // cover + 2
    }

    // ===== Controller (lezioni) =====

    public function test_controller_dispatcha_con_presentazione_pubblicata(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $pres = $this->publishedPres($lesson, $this->specWithSlides(2));

        $this->asProf($prof)->post(route('docente.lessons.video.script', $lesson))->assertRedirect();

        $video = $lesson->videos()->where('presentation_id', $pres->id)->first();
        $this->assertNotNull($video);
        Bus::assertDispatchedAfterResponse(GenerateVideoScriptJob::class,
            fn (GenerateVideoScriptJob $j) => $j->videoId === $video->id && $j->videoType === 'lesson');
    }

    public function test_controller_422_senza_pubblicata(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $lesson = $this->lesson($prof); // nessuna presentazione pubblicata

        $this->asProf($prof)->post(route('docente.lessons.video.script', $lesson))->assertStatus(422);
        Bus::assertNotDispatchedAfterResponse(GenerateVideoScriptJob::class);
    }

    public function test_controller_422_senza_spec(): void
    {
        Bus::fake();
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $this->publishedPres($lesson, null); // pubblicata ma senza spec

        $this->asProf($prof)->post(route('docente.lessons.video.script', $lesson))->assertStatus(422);
        Bus::assertNotDispatchedAfterResponse(GenerateVideoScriptJob::class);
    }

    // ===== Controller (moduli) — dispatch =====

    public function test_modulo_controller_dispatcha(): void
    {
        Bus::fake();
        $course = Course::create(['name' => 'C', 'slug' => 'c-' . Str::lower(Str::random(8)), 'is_active' => true]);
        $module = Module::create(['course_id' => $course->id, 'title' => 'M', 'content' => '## x', 'sort_order' => 0, 'is_active' => true]);
        $pres = ModulePresentation::create(['module_id' => $module->id, 'status' => 'ready', 'published_at' => now(), 'spec' => $this->specWithSlides(2)]);

        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@ente.it'])
            ->post(route('admin.courses.modules.video.script', [$course, $module]))->assertRedirect();

        Bus::assertDispatchedAfterResponse(GenerateVideoScriptJob::class,
            fn (GenerateVideoScriptJob $j) => $j->videoType === 'module');
    }
}
