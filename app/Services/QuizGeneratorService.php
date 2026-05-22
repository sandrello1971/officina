<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QuizGeneratorService
{
    public function generateFromContent(Course $course, string $content, int $numQuestions = 10): ?Quiz
    {
        $brand = atheneum_setting('instance_name', 'aziende e PMI');
        $systemPrompt = <<<SYSTEM
Sei un esperto di formazione aziendale per {$brand}.
Devi generare domande a risposta multipla per verificare la comprensione del materiale del corso.

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

        $userPrompt = "Genera {$numQuestions} domande a risposta multipla per il corso '{$course->name}'.\n\n";
        $userPrompt .= "Ecco il contenuto del corso su cui basare le domande:\n\n";
        $userPrompt .= substr(strip_tags($content), 0, 6000);
        $userPrompt .= "\n\nRispondi SOLO con JSON valido.";

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-5',
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

            if (!$data || !isset($data['questions'])) {
                Log::warning('QuizGeneratorService: invalid JSON response');
                return null;
            }

            $quiz = Quiz::create([
                'course_id' => $course->id,
                'title' => 'Quiz AI — ' . $course->name,
                'description' => 'Quiz generato automaticamente da Claude AI',
                'passing_score' => 70,
                'is_active' => true,
                'randomize_questions' => true,
                'show_results_immediately' => true,
            ]);

            foreach ($data['questions'] as $i => $q) {
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

            return $quiz;

        } catch (\Throwable $e) {
            Log::error('QuizGeneratorService error: ' . $e->getMessage());
            return null;
        }
    }
}
