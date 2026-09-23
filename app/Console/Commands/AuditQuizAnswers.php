<?php

namespace App\Console\Commands;

use App\Models\QuizQuestion;
use App\Services\QuizGeneratorService;
use Illuminate\Console\Command;

/**
 * Trova le domande a scelta multipla la cui correct_answer non coincide ESATTAMENTE
 * con una delle opzioni: la correzione del discente usa ===, quindi a quelle
 * domande è impossibile rispondere giusto. Di default solo report; con --fix
 * riconduce le varianti riconoscibili (lettera "b", spazi, maiuscole) al testo
 * esatto dell'opzione. Le irrecuperabili vanno corrette a mano dall'admin.
 */
class AuditQuizAnswers extends Command
{
    protected $signature = 'quiz:audit-answers {--fix : Corregge le correct_answer riconducibili a un\'opzione}';

    protected $description = 'Verifica che la risposta corretta di ogni domanda quiz sia una delle opzioni (--fix per correggere)';

    public function handle(QuizGeneratorService $generator): int
    {
        $fixable = 0;
        $broken = [];

        QuizQuestion::with('quiz:id,title')
            ->where('type', 'multiple_choice')
            ->orderBy('quiz_id')
            ->chunkById(500, function ($questions) use ($generator, &$fixable, &$broken) {
                foreach ($questions as $q) {
                    $options = is_array($q->options) ? $q->options : [];
                    if (in_array($q->correct_answer, $options, true)) {
                        continue;
                    }

                    $resolved = $generator->resolveCorrectAnswer($q->correct_answer, array_values($options));
                    $label = ($q->quiz?->title ?? $q->quiz_id) . ' — ' . mb_strimwidth((string) $q->question, 0, 70, '…');

                    if ($resolved === null) {
                        $broken[] = [$q->id, $label, mb_strimwidth((string) $q->correct_answer, 0, 40, '…')];
                        continue;
                    }

                    $fixable++;
                    $this->line("  riconducibile: {$label}  «{$q->correct_answer}» → «{$resolved}»");
                    if ($this->option('fix')) {
                        $q->update(['correct_answer' => $resolved]);
                    }
                }
            });

        $this->newLine();
        $this->info($fixable . ' domande riconducibili' . ($this->option('fix') ? ' — CORRETTE.' : ' (rilancia con --fix per correggerle).'));

        if ($broken) {
            $this->warn(count($broken) . ' domande NON recuperabili automaticamente (da correggere a mano nell\'admin):');
            $this->table(['question_id', 'quiz — domanda', 'correct_answer attuale'], $broken);
        }

        return self::SUCCESS;
    }
}
