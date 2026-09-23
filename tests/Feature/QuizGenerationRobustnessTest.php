<?php

namespace Tests\Feature;

use App\Jobs\GenerateArtifactJob;
use App\Models\Course;
use App\Models\Module;
use App\Models\QuizQuestion;
use App\Models\Student;
use App\Models\TeachingArtifact;
use App\Models\TeachingDocument;
use App\Services\ConceptMapGenerationService;
use App\Services\MindMapGenerationService;
use App\Services\QuizGeneratorService;
use App\Services\SummaryGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Robustezza del generatore quiz: estratto UTF-8 valido e distribuito su tutto
 * il corso, domande sempre correggibili (correct_answer ∈ options), JSON con
 * testo di contorno, batch anche per i quiz Schola, bonifica delle domande esistenti.
 */
class QuizGenerationRobustnessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.anthropic.key' => 'test-key']);
    }

    private function fakeQuestions(array $questions, string $prefix = ''): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => $prefix . json_encode(['questions' => $questions])]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ], 200)]);
    }

    private function q(string $text, $correct, array $options = ['uno', 'due', 'tre', 'quattro']): array
    {
        return ['question' => $text, 'options' => $options, 'correct_answer' => $correct, 'explanation' => 'e'];
    }

    private function course(): Course
    {
        return Course::create(['name' => 'C ' . uniqid(), 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    private function sentPrompts(): array
    {
        return Http::recorded()->map(fn ($pair) => $pair[0]->data()['messages'][0]['content'] ?? '')->all();
    }

    // ===== correct_answer sempre una delle opzioni =====

    public function test_correct_answer_ricondotta_o_domanda_scartata(): void
    {
        $this->fakeQuestions([
            $this->q('Lettera?', 'b'),
            $this->q('Lettera con testo?', 'C) tre'),
            $this->q('Spazio finale?', 'due '),
            $this->q('Maiuscole?', 'QUATTRO'),
            $this->q('Indice?', 0),
            $this->q('Non riconducibile?', 'cinque'),
            $this->q('Lettera con testo sbagliato?', 'b) tre'),
            $this->q('Due opzioni?', 'uno', ['uno', 'due']),
            $this->q('Opzioni ripetute?', 'uno', ['uno', 'Uno', 'tre', 'quattro']),
            $this->q('', 'uno'),
        ]);

        $quiz = app(QuizGeneratorService::class)->generateFromContent($this->course(), 'contenuto', 10);

        $questions = $quiz->questions()->orderBy('sort_order')->get();
        $this->assertSame(
            ['Lettera?' => 'due', 'Lettera con testo?' => 'tre', 'Spazio finale?' => 'due', 'Maiuscole?' => 'quattro', 'Indice?' => 'uno'],
            $questions->pluck('correct_answer', 'question')->all()
        );
        foreach ($questions as $q) {
            $this->assertContains($q->correct_answer, $q->options);
        }
    }

    public function test_nessuna_domanda_valida_equivale_a_fallimento(): void
    {
        $this->fakeQuestions([$this->q('Q?', 'zzz'), $this->q('Q2?', 'a', ['a', 'b'])]);

        $this->assertNull(app(QuizGeneratorService::class)->generateFromContent($this->course(), 'contenuto', 2));
    }

    // ===== Estratto del contenuto =====

    public function test_taglio_su_lettera_accentata_non_blocca_la_richiesta(): void
    {
        $this->fakeQuestions([$this->q('Q?', 'uno')]);
        // Byte ASCII fino al limite -1 + "à" (2 byte): il vecchio substr spezzava il carattere.
        $content = str_repeat('x', 11999) . str_repeat('à', 10);

        $quiz = app(QuizGeneratorService::class)->generateFromContent($this->course(), $content, 1);

        $this->assertNotNull($quiz);
        Http::assertSentCount(1);
        $this->assertTrue(mb_check_encoding($this->sentPrompts()[0], 'UTF-8'));
    }

    public function test_sorgente_con_byte_non_utf8_non_blocca_la_richiesta(): void
    {
        $this->fakeQuestions([$this->q('Q?', 'uno')]);

        $quiz = app(QuizGeneratorService::class)->generateFromContent($this->course(), "testo \xC3 rotto \xFF da PDF", 1);

        $this->assertNotNull($quiz);
        Http::assertSentCount(1);
    }

    public function test_estratto_copre_tutti_i_moduli_del_corso(): void
    {
        $this->fakeQuestions([$this->q('Q?', 'uno')]);
        $course = $this->course();
        foreach (range(1, 6) as $i) {
            Module::create(['course_id' => $course->id, 'title' => "M{$i}", 'sort_order' => $i, 'is_active' => true,
                'content' => "<p>MODULO{$i} " . str_repeat('testo del modulo è qui. ', 300) . '</p>']);
        }
        // Come i controller: un elemento per modulo.
        $content = $course->modules()->orderBy('sort_order')->pluck('content')->all();

        app(QuizGeneratorService::class)->generateFromContent($course, $content, 5);

        $prompt = $this->sentPrompts()[0];
        foreach (range(1, 6) as $i) {
            $this->assertStringContainsString("MODULO{$i}", $prompt, "Il modulo {$i} deve arrivare al modello.");
        }
        $this->assertLessThan(12000 + 500, strlen($prompt));
        $this->assertStringNotContainsString('<p>', $prompt);
    }

    public function test_testo_unico_lungo_campionato_su_tutta_la_lunghezza(): void
    {
        $this->fakeQuestions([$this->q('Q?', 'uno')]);
        $content = implode(' ', array_map(fn ($i) => "SEZ{$i} " . str_repeat('perché è così. ', 400), range(1, 8)));

        app(QuizGeneratorService::class)->generateFromContent($this->course(), $content, 5);

        $prompt = $this->sentPrompts()[0];
        $this->assertStringContainsString('SEZ1', $prompt);
        $this->assertTrue(mb_check_encoding($prompt, 'UTF-8'));
        // Deve arrivare anche materiale dalla seconda metà del testo, non solo l'inizio.
        $this->assertMatchesRegularExpression('/SEZ[5-8]/', $prompt);
    }

    public function test_entita_html_decodificate(): void
    {
        $this->fakeQuestions([$this->q('Q?', 'uno')]);

        app(QuizGeneratorService::class)->generateFromContent($this->course(), '<p>perch&eacute; l&#39;AI &egrave;</p>', 1);

        $this->assertStringContainsString("perché l'AI è", $this->sentPrompts()[0]);
    }

    // ===== Parsing della risposta =====

    public function test_testo_prima_del_json_non_fa_fallire(): void
    {
        $this->fakeQuestions([$this->q('Q?', 'uno')], "Ecco le domande richieste:\n");

        $quiz = app(QuizGeneratorService::class)->generateFromContent($this->course(), 'contenuto', 1);

        $this->assertNotNull($quiz);
        $this->assertSame(1, $quiz->questions()->count());
    }

    // ===== Quiz Schola: batch anche oltre 10 domande =====

    public function test_quiz_schola_da_20_domande_generato_a_batch(): void
    {
        $call = 0;
        Http::fake(['api.anthropic.com/*' => function () use (&$call) {
            $call++;
            $qs = array_map(fn ($i) => $this->q("Batch {$call} domanda {$i}?", 'uno'), range(1, 10));

            return Http::response(['content' => [['type' => 'text', 'text' => json_encode(['questions' => $qs])]],
                'usage' => ['input_tokens' => 1, 'output_tokens' => 1]], 200);
        }]);

        $prof = Student::create(['name' => 'Prof', 'email' => 'prof+' . uniqid() . '@example.com', 'password' => bcrypt('x'),
            'role' => 'professor', 'is_active' => true, 'must_change_password' => false]);
        $doc = TeachingDocument::create(['teacher_id' => $prof->id, 'title' => 'Lezione', 'source_type' => 'text',
            'status' => 'ready', 'extracted_text' => str_repeat('La fotosintesi avviene nei cloroplasti. ', 50)]);
        $art = TeachingArtifact::create(['teaching_document_id' => $doc->id, 'teacher_id' => $prof->id,
            'type' => 'quiz', 'title' => 'Quiz', 'status' => 'generating']);

        (new GenerateArtifactJob($art->id, ['num_questions' => 20]))->handle(
            app(MindMapGenerationService::class), app(ConceptMapGenerationService::class),
            app(QuizGeneratorService::class), app(SummaryGenerationService::class),
        );

        $art->refresh();
        $this->assertSame('ready', $art->status);
        $this->assertSame(20, QuizQuestion::where('quiz_id', $art->quiz_id)->count());
        $this->assertGreaterThanOrEqual(2, $call, 'Oltre 10 domande deve generare a batch.');
        Http::assertSent(fn (Request $r) => ($r->data()['max_tokens'] ?? 0) === 4096);
    }

    // ===== Admin: modulo selezionato =====

    public function test_admin_genera_dal_solo_modulo_selezionato(): void
    {
        $this->fakeQuestions([$this->q('Q?', 'uno')]);
        $course = $this->course();
        Module::create(['course_id' => $course->id, 'title' => 'M1', 'content' => 'CONTENUTO_PRIMO', 'sort_order' => 1, 'is_active' => true]);
        $m2 = Module::create(['course_id' => $course->id, 'title' => 'M2', 'content' => 'CONTENUTO_SECONDO', 'sort_order' => 2, 'is_active' => true]);
        $this->withSession(['admin_logged_in' => true, 'admin_email' => 'a@e.it'])->post(route('admin.quizzes.store'), [
            'title' => 'Quiz M2', 'course_id' => $course->id, 'module_id' => $m2->id, 'passing_score' => 70,
            'generate_with_ai' => '1', 'num_questions' => 1,
        ]);

        $prompt = $this->sentPrompts()[0];
        $this->assertStringContainsString('CONTENUTO_SECONDO', $prompt);
        $this->assertStringNotContainsString('CONTENUTO_PRIMO', $prompt);
        $this->assertDatabaseHas('quizzes', ['title' => 'Quiz M2', 'module_id' => $m2->id, 'course_id' => $course->id]);
    }

    // ===== Bonifica domande già salvate =====

    public function test_comando_audit_corregge_solo_con_fix(): void
    {
        $course = $this->course();
        $quiz = \App\Models\Quiz::create(['course_id' => $course->id, 'title' => 'Vecchio', 'passing_score' => 70]);
        $fixable = QuizQuestion::create(['quiz_id' => $quiz->id, 'question' => 'A?', 'type' => 'multiple_choice',
            'options' => ['uno', 'due', 'tre', 'quattro'], 'correct_answer' => 'b', 'points' => 1, 'sort_order' => 1]);
        $broken = QuizQuestion::create(['quiz_id' => $quiz->id, 'question' => 'B?', 'type' => 'multiple_choice',
            'options' => ['uno', 'due', 'tre', 'quattro'], 'correct_answer' => 'boh', 'points' => 1, 'sort_order' => 2]);

        $this->artisan('quiz:audit-answers')->assertSuccessful();
        $this->assertSame('b', $fixable->fresh()->correct_answer, 'Senza --fix non scrive nulla.');

        $this->artisan('quiz:audit-answers', ['--fix' => true])->assertSuccessful();
        $this->assertSame('due', $fixable->fresh()->correct_answer);
        $this->assertSame('boh', $broken->fresh()->correct_answer, 'Le irrecuperabili restano per la correzione manuale.');
    }
}
