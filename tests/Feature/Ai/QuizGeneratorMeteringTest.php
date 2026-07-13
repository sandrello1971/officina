<?php

namespace Tests\Feature\Ai;

use App\Models\AiUsage;
use App\Models\Course;
use App\Services\QuizGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Verifica che il refactor di QuizGeneratorService sul ClaudeClient funzioni
 * end-to-end e produca il metering con feature + course_id.
 */
class QuizGeneratorMeteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_generate_from_content_funziona_e_registra_il_metering(): void
    {
        $course = Course::create(['name' => 'CORSO', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);

        $questions = ['questions' => [[
            'question' => 'Domanda?', 'options' => ['a', 'b', 'c', 'd'],
            'correct_answer' => 'a', 'explanation' => 'perché a',
        ]]];

        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode($questions)]],
            'usage' => ['input_tokens' => 120, 'output_tokens' => 60],
        ], 200)]);

        $quiz = app(QuizGeneratorService::class)
            ->generateFromContent($course, str_repeat('contenuto del corso. ', 50), 5);

        $this->assertNotNull($quiz);
        $this->assertSame(1, $quiz->questions()->count());

        $u = AiUsage::where('feature', 'quiz.generate')->firstOrFail();
        $this->assertSame($course->id, $u->course_id);
        $this->assertSame(120, $u->tokens_in);
        $this->assertSame(60, $u->tokens_out);
    }
}
