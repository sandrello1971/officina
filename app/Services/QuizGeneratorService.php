<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use Illuminate\Support\Facades\Log;

class QuizGeneratorService
{
    private const PROMPT_VERSION = 'quiz-2026-06';

    /** Domande per chiamata Claude nel pool: piccolo → niente troncamento JSON. */
    private const POOL_BATCH_SIZE = 10;
    /** Tetto ai round di batch (anti-loop se il modello non raggiunge il target). */
    private const POOL_MAX_ROUNDS = 12;
    /** Contenuto usato per i pool grandi (più materiale = più varietà). */
    private const POOL_CONTENT_CHARS = 16000;
    /** Contenuto usato per la singola chiamata (≤ POOL_BATCH_SIZE domande). */
    private const SINGLE_CONTENT_CHARS = 12000;
    /** Finestre in cui l'estratto campiona un testo unico quando non ci sta tutto. */
    private const EXCERPT_WINDOWS = 8;
    private const EXCERPT_SEPARATOR = "\n[…]\n";

    public function __construct(private \App\Services\Ai\ClaudeClient $claude) {}

    /**
     * Percorso storico (mondo corsi): genera e persiste un quiz legato a un corso.
     * Comportamento invariato — delega la generazione delle domande al core
     * generateQuestions e la persistenza a persistQuiz.
     */
    public function generateFromContent(
        Course $course,
        string|array $content,
        int $numQuestions = 10,
        ?int $questionsPerAttempt = null
    ): ?Quiz {
        $brand = atheneum_setting('instance_name', 'aziende e PMI');
        $opts = [
            'audience' => "formazione aziendale per {$brand}",
            'subject_noun' => 'corso',
            'meter' => ['feature' => 'quiz.generate', 'course_id' => $course->id],
        ];

        $result = $this->generateQuestionSet($content, $course->name, $numQuestions, $opts);

        if ($result === null) {
            return null;
        }

        // questions_per_attempt valido solo se < pool effettivo; altrimenti NULL (tutte).
        $pool = count($result['questions']);
        $perAttempt = ($questionsPerAttempt !== null && $questionsPerAttempt > 0 && $questionsPerAttempt < $pool)
            ? $questionsPerAttempt
            : null;

        return $this->persistQuiz([
            'course_id' => $course->id,
            'title' => 'Quiz AI — ' . $course->name,
            'description' => 'Quiz generato automaticamente da Claude AI',
            'passing_score' => 70,
            'is_active' => true,
            'randomize_questions' => true,
            'questions_per_attempt' => $perAttempt,
            'show_results_immediately' => true,
        ], $result['questions']);
    }

    /**
     * Restituisce un set di domande della dimensione richiesta SENZA persistere:
     * pool grande (> POOL_BATCH_SIZE) → generazione a batch (evita il troncamento
     * del single-call); pool piccolo → singola chiamata storica. Riusabile da
     * chiunque debba poi persistere con attributi propri (es. il form admin).
     *
     * @return array{questions: array, meta: array}|null
     */
    public function generateQuestionSet(string|array $content, string $contextLabel, int $numQuestions, array $options = []): ?array
    {
        return $numQuestions > self::POOL_BATCH_SIZE
            ? $this->generatePool($content, $contextLabel, $numQuestions, $options)
            : $this->generateQuestions($content, $contextLabel, $numQuestions, $options);
    }

    /**
     * Genera un POOL ampio di domande iterando in batch da POOL_BATCH_SIZE finché
     * raggiunge $target (o esaurisce i round). Ogni batch è una chiamata Claude
     * separata (no troncamento JSON). Dedup su testo normalizzato + anti-ripetizione
     * via prompt (avoid-list). NON persiste nulla.
     *
     * @return array{questions: array, meta: array}|null
     */
    public function generatePool(string|array $content, string $contextLabel, int $target, array $options = []): ?array
    {
        $collected = [];      // questions accumulate
        $seen = [];           // testo normalizzato → true (dedup)
        $tokensIn = 0;
        $tokensOut = 0;
        $rounds = 0;

        while (count($collected) < $target && $rounds < self::POOL_MAX_ROUNDS) {
            $rounds++;
            $need = $target - count($collected);
            $batch = min(self::POOL_BATCH_SIZE, $need + 2); // +2 margine per gli scarti del dedup

            $res = $this->generateQuestions($content, $contextLabel, $batch, array_merge($options, [
                'content_chars' => self::POOL_CONTENT_CHARS,
                // Finestre dell'estratto sfalsate a ogni round: i batch vedono
                // porzioni diverse del corso → più copertura e varietà.
                'excerpt_phase' => ($rounds - 1) / self::POOL_MAX_ROUNDS,
                'avoid' => array_map(fn ($q) => $q['question'] ?? '', $collected),
            ]));

            if ($res === null) {
                // Batch fallito: se abbiamo già qualcosa, ci fermiamo con ciò che c'è.
                Log::warning('QuizGeneratorService: batch pool fallito', ['round' => $rounds, 'collected' => count($collected)]);
                break;
            }

            $tokensIn += $res['meta']['tokens_in'] ?? 0;
            $tokensOut += $res['meta']['tokens_out'] ?? 0;

            $added = 0;
            foreach ($res['questions'] as $q) {
                $key = $this->normalizeQuestion($q['question'] ?? '');
                if ($key === '' || isset($seen[$key])) {
                    continue; // duplicato/quasi-identico → scarta
                }
                $seen[$key] = true;
                $collected[] = $q;
                $added++;
                if (count($collected) >= $target) {
                    break;
                }
            }

            // Round improduttivo (0 nuove dopo il dedup) → evita loop infinito.
            if ($added === 0) {
                Log::info('QuizGeneratorService: round pool senza nuove domande, stop', ['round' => $rounds]);
                break;
            }
        }

        if (empty($collected)) {
            return null;
        }

        return [
            'questions' => array_slice($collected, 0, $target),
            'meta' => [
                'model' => config('services.anthropic.model'),
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'prompt_version' => self::PROMPT_VERSION,
                'questions_count' => min(count($collected), $target),
                'rounds' => $rounds,
            ],
        ];
    }

    /**
     * Testo da mandare al modello, al massimo ~$maxBytes byte e sempre UTF-8 valido
     * (mb_strcut: un substr a byte spezzava le lettere accentate → json_encode
     * falliva → la richiesta non partiva mai, e sempre sullo stesso corso).
     * Se il contenuto non ci sta, invece di tenere solo l'inizio (quiz sui soli
     * primi moduli):
     *  - elenco di sezioni (un elemento per modulo) → budget ripartito equamente,
     *    le sezioni corte cedono il margine alle lunghe: ogni modulo è presente;
     *  - testo unico → EXCERPT_WINDOWS finestre equidistanti su tutto il testo.
     * $phase (0..1) sposta le finestre per variare l'estratto tra i batch del pool.
     */
    private function excerpt(string|array $content, int $maxBytes, float $phase = 0.0): string
    {
        $sections = array_values(array_filter(
            array_map(fn ($c) => $this->plainText((string) $c), (array) $content),
            fn ($t) => $t !== ''
        ));
        $phase = fmod(max($phase, 0.0), 1.0);
        $joined = implode("\n\n", $sections);

        if (strlen($joined) <= $maxBytes) {
            return $joined;
        }

        if (count($sections) === 1) {
            $text = $sections[0];
            $sections = [];
            $stride = intdiv(strlen($text), self::EXCERPT_WINDOWS);
            for ($i = 0; $i < self::EXCERPT_WINDOWS; $i++) {
                $sections[] = mb_strcut($text, $i * $stride, $stride, 'UTF-8');
            }
        }

        // Ripartizione equa: dalla sezione più corta, ognuna prende al più la sua
        // quota; l'avanzo passa alle successive.
        $budget = $maxBytes - count($sections) * strlen(self::EXCERPT_SEPARATOR);
        $lengths = array_map('strlen', $sections);
        asort($lengths);
        $alloc = [];
        $left = count($sections);
        foreach ($lengths as $i => $len) {
            $alloc[$i] = min($len, intdiv(max($budget, 0), $left));
            $budget -= $alloc[$i];
            $left--;
        }

        $parts = [];
        foreach ($sections as $i => $text) {
            $shift = (int) ($phase * (strlen($text) - $alloc[$i]));
            $parts[] = trim(mb_strcut($text, $shift, $alloc[$i], 'UTF-8'));
        }

        return implode(self::EXCERPT_SEPARATOR, array_filter($parts, fn ($p) => $p !== ''));
    }

    /** HTML → testo semplice (tag via, entità decodificate, spazi compattati). */
    private function plainText(string $html): string
    {
        // mb_scrub: anche un testo sorgente già non-UTF-8 (es. estrazione PDF)
        // farebbe fallire json_encode della richiesta.
        $text = html_entity_decode(strip_tags(mb_scrub($html, 'UTF-8')), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n\s*\n\s*(\n\s*)+/u", "\n\n", $text) ?? $text;

        return trim($text);
    }

    /**
     * Tiene solo domande correggibili: testo non vuoto, esattamente 4 opzioni
     * distinte, correct_answer IDENTICA a una delle opzioni (la correzione del
     * discente confronta con ===). Riconduce le varianti tipiche del modello —
     * lettera ("b", "B)"), indice, spazi/maiuscole diverse — al testo esatto
     * dell'opzione; ciò che resta ambiguo viene scartato (meglio una domanda in
     * meno che una a cui non si può rispondere giusto).
     */
    public function sanitizeQuestions(array $questions): array
    {
        $valid = [];

        foreach ($questions as $q) {
            if (!is_array($q)) {
                continue;
            }
            $text = trim((string) ($q['question'] ?? ''));
            $options = array_values(array_map(
                fn ($o) => trim((string) $o),
                array_filter($q['options'] ?? [], fn ($o) => is_scalar($o))
            ));

            if ($text === '' || count($options) !== 4 || in_array('', $options, true)
                || count(array_unique(array_map('mb_strtolower', $options))) !== 4) {
                continue;
            }

            $correct = $this->resolveCorrectAnswer($q['correct_answer'] ?? null, $options);
            if ($correct === null) {
                continue;
            }

            $valid[] = [
                'question' => $text,
                'options' => $options,
                'correct_answer' => $correct,
                'explanation' => isset($q['explanation']) ? trim((string) $q['explanation']) : null,
            ];
        }

        if (count($valid) < count($questions)) {
            Log::info('QuizGeneratorService: domande scartate in validazione', [
                'received' => count($questions), 'valid' => count($valid),
            ]);
        }

        return $valid;
    }

    /** Testo esatto dell'opzione corretta, o null se non determinabile senza ambiguità. */
    public function resolveCorrectAnswer(mixed $answer, array $options): ?string
    {
        $options = array_values(array_map(fn ($o) => (string) $o, $options));
        if (is_int($answer)) {
            return $options[$answer] ?? null;
        }
        if (!is_string($answer)) {
            return null;
        }

        $a = trim($answer);
        if (in_array($a, $options, true)) {
            return $a;
        }

        // Stesso testo a meno di maiuscole/spazi/punteggiatura finale.
        $norm = fn (string $s) => rtrim(preg_replace('/\s+/u', ' ', mb_strtolower(trim($s))), " .;:");
        $matches = array_values(array_filter($options, fn ($o) => $norm($o) === $norm($a)));
        if (count($matches) === 1) {
            return $matches[0];
        }

        // Lettera: "b", "B", "b)", "(b)", "b.", "b) testo opzione".
        if (preg_match('/^\(?([a-d])[\).:]?(?:\s+(.*))?$/iu', $a, $m)) {
            $candidate = $options[ord(strtolower($m[1])) - ord('a')] ?? null;
            // Se dopo la lettera c'è del testo, deve combaciare con l'opzione.
            if ($candidate !== null && (!isset($m[2]) || $m[2] === '' || $norm($m[2]) === $norm($candidate))) {
                return $candidate;
            }
        }

        return null;
    }

    /** Chiave di dedup: testo domanda normalizzato (minuscole, spazi/punteggiatura collassati). */
    private function normalizeQuestion(string $text): string
    {
        $t = mb_strtolower(strip_tags($text));
        $t = preg_replace('/[^\p{L}\p{N}\s]/u', '', $t); // via punteggiatura
        $t = trim(preg_replace('/\s+/u', ' ', $t));

        return $t;
    }

    /**
     * Core parametrizzato: interroga Claude e restituisce le domande a risposta
     * multipla (NON persiste nulla). Riusabile dal mondo corsi e da Schola.
     *
     * @param  string|array  $content  testo sorgente, o elenco di sezioni (es. un
     *                                 elemento per modulo: il budget è ripartito tra tutte)
     * @param  string  $contextLabel  titolo/etichetta del contesto (corso, documento, ecc.)
     * @param  array   $options       ['audience' => string, 'subject_noun' => string]
     * @return array{questions: array, meta: array}|null  null in caso di errore API/parse
     */
    public function generateQuestions(string|array $content, string $contextLabel, int $numQuestions = 10, array $options = []): ?array
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

        // Limite contenuto configurabile: i pool grandi hanno bisogno di più
        // materiale per varietà. Single-call 12000 (era 6000): ripartito su tutti i
        // moduli, 6000 lasciava pochissimo testo per modulo nei corsi lunghi.
        $contentChars = (int) ($options['content_chars'] ?? self::SINGLE_CONTENT_CHARS);

        $userPrompt = "Genera {$numQuestions} domande a risposta multipla per il {$subjectNoun} '{$contextLabel}'.\n\n";
        $userPrompt .= "Ecco il contenuto su cui basare le domande:\n\n";
        $userPrompt .= $this->excerpt($content, $contentChars, (float) ($options['excerpt_phase'] ?? 0.0));

        // Anti-ripetizione tra batch del pool: l'elenco delle domande già generate
        // viene passato perché il modello ne produca di NUOVE (riduce i duplicati a monte).
        $avoid = $options['avoid'] ?? [];
        if (!empty($avoid)) {
            $userPrompt .= "\n\nNON ripetere né riformulare queste domande GIÀ generate (creane di diverse, su altri aspetti del contenuto):\n- "
                . implode("\n- ", array_slice($avoid, 0, 60));
        }

        $userPrompt .= "\n\nRispondi SOLO con JSON valido.";

        $res = $this->claude->messages([
            'system' => $systemPrompt,
            'messages' => [['role' => 'user', 'content' => $userPrompt]],
            'max_tokens' => 4096,
        ], array_merge(['feature' => 'quiz.generate'], $options['meter'] ?? []));

        if ($res->failed()) {
            Log::warning('QuizGeneratorService: API call failed', ['error' => $res->error]);
            return null;
        }

        $data = $res->jsonFromText();
        if (!$data || !isset($data['questions']) || !is_array($data['questions']) || empty($data['questions'])) {
            Log::warning('QuizGeneratorService: invalid JSON response');
            return null;
        }

        $questions = $this->sanitizeQuestions($data['questions']);
        if (empty($questions)) {
            Log::warning('QuizGeneratorService: nessuna domanda valida nella risposta', ['raw_count' => count($data['questions'])]);
            return null;
        }

        return [
            'questions' => $questions,
            'meta' => [
                'model' => config('services.anthropic.model'),
                'tokens_in' => $res->tokensIn(),
                'tokens_out' => $res->tokensOut(),
                'prompt_version' => self::PROMPT_VERSION,
                'questions_count' => count($questions),
            ],
        ];
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
