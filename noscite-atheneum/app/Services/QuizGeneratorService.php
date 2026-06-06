<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QuizGeneratorService
{
    private const CLAUDE_MODEL = 'claude-sonnet-4-5';
    private const PROMPT_VERSION = 'quiz-2026-06';

    /**
     * Percorso storico (mondo corsi): genera e persiste un quiz legato a un corso.
     * Comportamento invariato — delega la generazione delle domande al core
     * generateQuestions e la persistenza a persistQuiz.
     */
    public function generateFromContent(Course $course, string $content, int $numQuestions = 10): ?Quiz
    {
        $brand = atheneum_setting('instance_name', 'aziende e PMI');

        $result = $this->generateQuestions($content, $course->name, $numQuestions, [
            'audience' => "formazione aziendale per {$brand}",
            'subject_noun' => 'corso',
        ]);

        if ($result === null) {
            return null;
        }

        return $this->persistQuiz([
            'course_id' => $course->id,
            'title' => 'Quiz AI — ' . $course->name,
            'description' => 'Quiz generato automaticamente da Claude AI',
            'passing_score' => 70,
            'is_active' => true,
            'randomize_questions' => true,
            'show_results_immediately' => true,
        ], $result['questions']);
    }

    /**
     * Core parametrizzato: interroga Claude e restituisce le domande a risposta
     * multipla (NON persiste nulla). Riusabile dal mondo corsi e da Schola.
     *
     * @param  string  $content       testo sorgente su cui basare le domande
     * @param  string  $contextLabel  titolo/etichetta del contesto (corso, documento, ecc.)
     * @param  array   $options       ['audience' => string, 'subject_noun' => string]
     * @return array{questions: array, meta: array}|null  null in caso di errore API/parse
     */
    public function generateQuestions(string $content, string $contextLabel, int $numQuestions = 10, array $options = []): ?array
    {
        $audience = $options['audience']
            ?? 'studenti di scuola superiore (linguaggio chiaro, registro scolastico)';
        $subjectNoun = $options['subject_noun'] ?? 'materiale';

        $systemPrompt = <<<SYSTEM
Sei un esperto di {$audience}.
Devi generare domande a risposta multipla per verificare la comprensione del materiale.

Regole:
- Ogni domanda deve avere esattamente 4 opzioni (a, b, c, d)
- Una sola risposta corretta per domanda
- Le domande devono testare comprensione reale, non solo memoria
- Le opzioni sbagliate devono essere plausibili
- Includi una spiegazione breve per la risposta corretta
- Rispondi SOLO con JSON valido, nessun testo extra

Formato JSON richiesto:
{
  "questions": [
    {
      "question": "testo della domanda",
      "options": ["opzione a", "opzione b", "opzione c", "opzione d"],
      "correct_answer": "testo esatto dell'opzione corretta",
      "explanation": "spiegazione breve della risposta"
    }
  ]
}
SYSTEM;

        $userPrompt = "Genera {$numQuestions} domande a risposta multipla per il {$subjectNoun} '{$contextLabel}'.\n\n";
        $userPrompt .= "Ecco il contenuto su cui basare le domande:\n\n";
        $userPrompt .= substr(strip_tags($content), 0, 6000);
        $userPrompt .= "\n\nRispondi SOLO con JSON valido.";

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
                'model' => self::CLAUDE_MODEL,
                'max_tokens' => 4096,
                'system' => $systemPrompt,
                'messages' => [['role' => 'user', 'content' => $userPrompt]],
            ]);

            if ($response->failed()) {
                Log::warning('QuizGeneratorService: API call failed', ['status' => $response->status()]);
                return null;
            }

            $text = $response->json('content.0.text', '');
            $text = preg_replace('/```json\s*/i', '', $text);
            $text = preg_replace('/```\s*/i', '', $text);
            $data = json_decode(trim($text), true);

            if (!$data || !isset($data['questions']) || !is_array($data['questions']) || empty($data['questions'])) {
                Log::warning('QuizGeneratorService: invalid JSON response');
                return null;
            }

            return [
                'questions' => $data['questions'],
                'meta' => [
                    'model' => self::CLAUDE_MODEL,
                    'tokens_in' => (int) $response->json('usage.input_tokens', 0),
                    'tokens_out' => (int) $response->json('usage.output_tokens', 0),
                    'prompt_version' => self::PROMPT_VERSION,
                    'questions_count' => count($data['questions']),
                ],
            ];
        } catch (\Throwable $e) {
            Log::error('QuizGeneratorService error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Persiste un quiz + le sue domande. Gli attributi di $attrs prevalgono sui
     * default; i quiz Schola passano module_id e course_id NULL (vivono fuori dal
     * mondo corsi, agganciati a un teaching_artifact via quiz_id).
     *
     * @param  array  $attrs      attributi del Quiz
     * @param  array  $questions  domande nel formato JSON del modello
     */
    public function persistQuiz(array $attrs, array $questions): Quiz
    {
        $quiz = Quiz::create(array_merge([
            'passing_score' => 70,
            'is_active' => true,
            'randomize_questions' => true,
            'show_results_immediately' => true,
        ], $attrs));

        $this->syncQuestions($quiz, $questions);

        return $quiz;
    }

    /**
     * Sostituisce le domande di un quiz esistente (usato in rigenerazione: il
     * quiz_id resta stabile, niente quiz orfani). Riusato anche da persistQuiz.
     */
    public function syncQuestions(Quiz $quiz, array $questions): void
    {
        $quiz->questions()->delete();

        foreach (array_values($questions) as $i => $q) {
            QuizQuestion::create([
                'quiz_id' => $quiz->id,
                'question' => $q['question'] ?? '',
                'type' => 'multiple_choice',
                'options' => $q['options'] ?? [],
                'correct_answer' => $q['correct_answer'] ?? '',
                'explanation' => $q['explanation'] ?? null,
                'points' => 1,
                'sort_order' => $i + 1,
            ]);
        }
    }
}
