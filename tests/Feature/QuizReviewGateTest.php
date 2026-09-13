<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Quiz;
use App\Services\QuizGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Gate di revisione umana (EU AI Act, Allegato III §4(b)): un quiz del mondo
 * corsi generato dall'AI deve nascere non attivo e diventare visibile agli
 * studenti solo dopo un'attivazione admin esplicita, che lascia evidenza
 * (reviewed_by/reviewed_at).
 */
class QuizReviewGateTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): self
    {
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => 'admin@officina.it']);
    }

    private function fakeClaudeQuestions(): void
    {
        $questions = ['questions' => [[
            'question' => 'Domanda?', 'options' => ['a', 'b', 'c', 'd'],
            'correct_answer' => 'a', 'explanation' => 'perché a',
        ]]];

        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode($questions)]],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 10],
        ], 200)]);
    }

    public function test_quiz_generato_dal_service_nasce_non_attivo(): void
    {
        $course = Course::create(['name' => 'CORSO', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        $this->fakeClaudeQuestions();

        $quiz = app(QuizGeneratorService::class)
            ->generateFromContent($course, str_repeat('contenuto. ', 50), 5);

        $this->assertNotNull($quiz);
        $this->assertFalse($quiz->is_active);
        $this->assertNull($quiz->reviewed_by);
        $this->assertNull($quiz->reviewed_at);
    }

    public function test_form_admin_con_ai_ignora_la_checkbox_attivo(): void
    {
        $course = Course::create(['name' => 'CORSO', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        \App\Models\Module::create(['course_id' => $course->id, 'title' => 'M', 'content' => str_repeat('contenuto. ', 50), 'sort_order' => 0, 'is_active' => true]);
        $this->fakeClaudeQuestions();

        $this->actingAsAdmin()->post(route('admin.quizzes.store'), [
            'title' => 'Quiz AI', 'course_id' => $course->id,
            'passing_score' => 70, 'is_active' => '1', // spuntata: deve essere ignorata
            'generate_with_ai' => '1', 'num_questions' => 5,
        ])->assertRedirect();

        $quiz = Quiz::where('title', 'Quiz AI')->firstOrFail();
        $this->assertFalse($quiz->is_active);
    }

    public function test_activate_attiva_il_quiz_e_registra_la_revisione(): void
    {
        $course = Course::create(['name' => 'CORSO', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        $quiz = Quiz::create(['course_id' => $course->id, 'title' => 'Q', 'passing_score' => 70, 'is_active' => false]);

        $this->actingAsAdmin()
            ->post(route('admin.quizzes.activate', $quiz))
            ->assertRedirect();

        $quiz->refresh();
        $this->assertTrue($quiz->is_active);
        $this->assertSame('admin@officina.it', $quiz->reviewed_by);
        $this->assertNotNull($quiz->reviewed_at);
    }

    public function test_quiz_schola_autoverifica_non_e_toccato_dal_gate(): void
    {
        // persistQuiz senza is_active esplicito (come fa Schola) resta true di default:
        // l'autoverifica dello studente non passa dal gate del mondo corsi.
        $this->fakeClaudeQuestions();
        $res = app(QuizGeneratorService::class)->generateQuestions('contenuto', 'Lezione', 3);
        $quiz = app(QuizGeneratorService::class)->persistQuiz([
            'module_id' => null, 'course_id' => null, 'title' => 'Autoverifica',
        ], $res['questions']);

        $this->assertTrue($quiz->is_active);
    }
}
