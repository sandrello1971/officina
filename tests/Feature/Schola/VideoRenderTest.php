<?php

namespace Tests\Feature\Schola;

use App\Jobs\GenerateVideoJob;
use App\Models\Lesson;
use App\Models\LessonPresentation;
use App\Models\LessonVideo;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\Tts\TtsProvider;
use App\Services\Schola\SlidePreviewService;
use App\Services\Schola\VideoRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * V3 — render MP4 (TTS + ffmpeg). Lo smoke di render usa ffmpeg REALE su un PNG + un
 * audio di prova (TTS fakeato) → niente chiamate a pagamento. I test del GATE/dispatch
 * non toccano ffmpeg. Verifica anche la cache MP3 (testo invariato → niente nuova TTS).
 */
class VideoRenderTest extends TestCase
{
    use RefreshDatabase;

    private function prof(): Student
    {
        return Student::create(['name' => 'P', 'email' => 'p' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'professor', 'is_active' => true, 'must_change_password' => false]);
    }

    private function lesson(Student $prof): Lesson
    {
        $topic = Topic::create(['teacher_id' => $prof->id, 'subject_id' => Subject::firstOrCreate(['name' => 'Storia'])->id, 'name' => 'T', 'position' => 0]);

        return Lesson::create(['topic_id' => $topic->id, 'teacher_id' => $prof->id, 'title' => 'Le cause', 'position' => 0, 'generation_status' => 'ready', 'content' => '## x']);
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    /** Lezione + presentazione pubblicata + video con copione (script_status param). */
    private function scenario(string $scriptStatus, array $script): array
    {
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $pres = LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'ready', 'published_at' => now(),
            'file_path' => "lesson-presentations/{$lesson->id}/p.pptx", 'spec' => ['slides' => [['layout' => 'cover']]]]);
        Storage::disk('local')->put($pres->file_path, 'PPTX');
        $video = $lesson->videos()->create(['presentation_id' => $pres->id, 'status' => 'pending', 'script_status' => $scriptStatus, 'script' => $script]);

        return [$prof, $lesson, $pres, $video];
    }

    private function pngBytes(): string
    {
        $img = imagecreatetruecolor(320, 180);
        imagefilledrectangle($img, 0, 0, 320, 180, imagecolorallocate($img, 10, 10, 10));
        ob_start();
        imagepng($img);
        $bytes = ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    private function sampleAudioBytes(): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'aud') . '.wav';
        (new Process(['ffmpeg', '-y', '-f', 'lavfi', '-i', 'anullsrc=r=44100:cl=stereo', '-t', '0.4', $tmp]))->run();
        $bytes = is_file($tmp) ? (string) file_get_contents($tmp) : '';
        @unlink($tmp);

        return $bytes;
    }

    // ===== Render reale (skip se ffmpeg/GD assenti) =====

    public function test_render_produce_mp4_e_cache_mp3(): void
    {
        if (!(new ExecutableFinder())->find('ffmpeg') || !function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('ffmpeg o GD non disponibili.');
        }
        Storage::fake('local');
        Http::fake(['api.elevenlabs.io/*' => Http::response('NONDEVE', 200)]); // non deve essere chiamato

        [, $lesson, $pres, $video] = $this->scenario('confirmed', [['slide_number' => 1, 'text' => 'Benvenuti al corso']]);

        // PNG della slide 1 (mock di SlidePreviewService → niente soffice).
        $pngRel = "lesson-presentations/{$lesson->id}/p/slide_1.png";
        Storage::disk('local')->put($pngRel, $this->pngBytes());
        $this->mock(SlidePreviewService::class, fn ($m) => $m->shouldReceive('imagesFor')->andReturn([$pngRel]));

        // TTS fake con contatore (audio di prova reale).
        $audio = $this->sampleAudioBytes();
        $this->assertNotSame('', $audio, 'ffmpeg deve produrre l\'audio di prova');
        $fake = new class($audio) implements TtsProvider {
            public int $calls = 0;
            public function __construct(private string $audio) {}
            public function synthesize(string $text, array $options = []): string { $this->calls++; return $this->audio; }
        };
        $this->app->instance(TtsProvider::class, $fake);

        $result = app(VideoRenderService::class)->render($video->refresh());

        Storage::disk('local')->assertExists($result['file_path']);
        $this->assertStringEndsWith('.mp4', $result['file_path']);
        $this->assertSame(1, $fake->calls);

        // slide_timings: una voce per slide, somma ~= durata totale.
        $timings = $result['meta']['slide_timings'] ?? [];
        $this->assertCount(1, $timings);
        $this->assertSame(1, $timings[0]['slide_number']);
        $this->assertEqualsWithDelta($result['meta']['seconds'], $timings[0]['end_sec'], 0.5);

        // Secondo render: testo invariato → MP3 dalla cache, nessuna nuova TTS.
        app(VideoRenderService::class)->render($video->refresh());
        $this->assertSame(1, $fake->calls, 'cache MP3: niente nuova sintesi a testo invariato');

        Http::assertNothingSent(); // nessuna chiamata reale a ElevenLabs
    }

    public function test_render_richiede_copione_confermato(): void
    {
        Storage::fake('local');
        [, , , $video] = $this->scenario('draft', [['slide_number' => 1, 'text' => 'x']]);

        $this->expectException(\RuntimeException::class);
        app(VideoRenderService::class)->render($video);
    }

    // ===== Controller GATE =====

    public function test_controller_genera_richiede_confermato(): void
    {
        Bus::fake();
        [$prof, $lesson] = $this->scenario('draft', [['slide_number' => 1, 'text' => 'x']]);

        $this->asProf($prof)->post(route('docente.lessons.video.generate', $lesson))->assertStatus(422);
        Bus::assertNotDispatchedAfterResponse(GenerateVideoJob::class);
    }

    public function test_controller_genera_dispatcha_se_confermato(): void
    {
        Bus::fake();
        [$prof, $lesson, , $video] = $this->scenario('confirmed', [['slide_number' => 1, 'text' => 'x']]);

        $this->asProf($prof)->post(route('docente.lessons.video.generate', $lesson))->assertRedirect();

        $this->assertSame('generating', $video->refresh()->status);
        Bus::assertDispatchedAfterResponse(GenerateVideoJob::class,
            fn (GenerateVideoJob $j) => $j->videoId === $video->id && $j->videoType === 'lesson');
    }
}
