<?php

namespace Tests\Feature\Schola;

use App\Models\Lesson;
use App\Models\LessonPresentation;
use App\Models\LessonVideo;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Topic;
use App\Services\Schola\VideoScriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * V2 — revisione copione: correzione a mano e via prompt (merge mirato, solo la riga
 * indicata), conferma, e ritorno a 'draft' + invalidazione derivati quando si
 * modifica un copione confermato. Nessun TTS/ffmpeg.
 */
class VideoScriptReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.key' => 'test-key', 'services.pptx.model' => 'claude-sonnet-4-6']);
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

    /** Lezione + presentazione pubblicata (4 slide) + video con copione 4 righe. */
    private function setupVideo(array $videoAttrs = []): array
    {
        $prof = $this->prof();
        $lesson = $this->lesson($prof);
        $slides = [['layout' => 'cover', 'title' => 'Cover']];
        for ($i = 1; $i <= 3; $i++) {
            $slides[] = ['layout' => 'bullets_clean', 'title' => "S{$i}", 'bullets' => ["p{$i}"]];
        }
        $pres = LessonPresentation::create(['lesson_id' => $lesson->id, 'status' => 'ready', 'published_at' => now(), 'spec' => ['slides' => $slides]]);
        $video = $lesson->videos()->create(array_merge([
            'presentation_id' => $pres->id, 'status' => 'pending', 'script_status' => 'draft',
            'script' => [
                ['slide_number' => 1, 'text' => 'uno'],
                ['slide_number' => 2, 'text' => 'due'],
                ['slide_number' => 3, 'text' => 'tre'],
                ['slide_number' => 4, 'text' => 'quattro'],
            ],
        ], $videoAttrs));

        return [$prof, $lesson, $pres, $video];
    }

    private function asProf(Student $p): self
    {
        return $this->withSession(['student_id' => $p->id, 'student_name' => $p->name, 'student_email' => $p->email]);
    }

    public function test_edit_a_mano_cambia_solo_quella_riga(): void
    {
        [, , , $video] = $this->setupVideo();

        (new VideoScriptService())->editLine($video, 3, 'TRE NUOVO');
        $video->refresh();

        $this->assertSame('TRE NUOVO', $video->script[2]['text']);
        $this->assertSame('uno', $video->script[0]['text']);
        $this->assertSame('due', $video->script[1]['text']);
        $this->assertSame('quattro', $video->script[3]['text']);
        $this->assertSame('draft', $video->script_status);
    }

    public function test_edit_via_prompt_merge_mirato_solo_quella_riga(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['text' => 'TRE RISCRITTA discorsiva']],
            'usage' => ['input_tokens' => 5, 'output_tokens' => 8],
        ], 200)]);
        [, , , $video] = $this->setupVideo();

        (new VideoScriptService())->editLineViaPrompt($video, 3, 'rendila più discorsiva');
        $video->refresh();

        // SOLO la slide 3 cambia
        $this->assertSame('TRE RISCRITTA discorsiva', $video->script[2]['text']);
        $this->assertSame('uno', $video->script[0]['text']);
        $this->assertSame('due', $video->script[1]['text']);
        $this->assertSame('quattro', $video->script[3]['text']);
        $this->assertSame('draft', $video->script_status);
        Http::assertSentCount(1);
    }

    public function test_modifica_da_confirmed_torna_draft_e_invalida_derivati(): void
    {
        Storage::fake('local');
        [, , , $video] = $this->setupVideo(['script_status' => 'confirmed', 'status' => 'ready', 'file_path' => 'lesson-videos/v.mp4']);
        Storage::disk('local')->put('lesson-videos/v.mp4', 'MP4');

        (new VideoScriptService())->editLine($video, 2, 'due modificata');
        $video->refresh();

        $this->assertSame('draft', $video->script_status);          // confermato → torna bozza
        $this->assertSame('pending', $video->status);               // mp4 invalidato
        $this->assertNull($video->file_path);
        Storage::disk('local')->assertMissing('lesson-videos/v.mp4');
    }

    // ===== Controller =====

    public function test_controller_edit_line(): void
    {
        [$prof, $lesson, , $video] = $this->setupVideo();

        $this->asProf($prof)->post(route('docente.lessons.video.line', $lesson), ['slide_number' => 1, 'text' => 'INTRO NUOVA'])
            ->assertRedirect();

        $this->assertSame('INTRO NUOVA', $video->refresh()->script[0]['text']);
    }

    public function test_controller_confirm(): void
    {
        [$prof, $lesson, , $video] = $this->setupVideo();

        $this->asProf($prof)->post(route('docente.lessons.video.confirm', $lesson))->assertRedirect();

        $this->assertSame('confirmed', $video->refresh()->script_status);
    }
}
