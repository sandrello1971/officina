<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Course;
use App\Models\Student;
use App\Services\RagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    public function __construct(private RagService $rag) {}

    public function show(Course $course)
    {
        $studentId = session('student_id');
        abort_unless($studentId, 403);

        $student = Student::findOrFail($studentId);
        $enrolled = $student->courses()->where('courses.id', $course->id)->exists();
        abort_unless($enrolled, 403);

        $conversation = ChatConversation::firstOrCreate(
            ['student_id' => $studentId, 'course_id' => $course->id, 'is_active' => true],
            ['title' => 'Chat ' . $course->name]
        );

        $messages = $conversation->messages()->orderBy('created_at')->get();

        return view('student.chat.show', compact('course', 'conversation', 'messages'));
    }

    public function minervaAsk(Request $request)
    {
        $studentId = session('student_id');
        abort_unless($studentId, 403);

        $data = $request->validate([
            'question' => 'required|string|max:4000',
            'history' => 'nullable|array',
            'history.*.role' => 'required_with:history|in:user,assistant',
            'history.*.content' => 'required_with:history|string',
            'mode' => 'nullable|in:summary,expand',
        ]);

        $student = Student::with(['courses' => fn($q) => $q->wherePivot('is_active', true)])
            ->findOrFail($studentId);

        $courseIds = $student->courses->pluck('id')->all();
        $courseNames = $student->courses->pluck('name')->all();
        $mode = $data['mode'] ?? 'summary';

        $docs = $this->rag->searchInCourses($data['question'], $courseIds, 4, true);
        $videoDocs = [];
        if (!empty($courseIds)) {
            foreach ($courseIds as $cid) {
                $found = $this->rag->searchVideos($data['question'], $cid, null, 1);
                foreach ($found as $f) $videoDocs[] = $f;
            }
            $videoDocs = array_slice($videoDocs, 0, 2);
        }

        $context = '';
        if (!empty($docs) && count($docs) > 0) {
            $context .= "📚 DOCUMENTI DEI CORSI:\n\n";
            foreach ($docs as $doc) {
                $content = is_array($doc) ? $doc['content'] : $doc->content;
                $title = is_array($doc) ? $doc['title'] : $doc->title;
                $context .= "--- {$title} ---\n{$content}\n\n";
            }
        }
        if (!empty($videoDocs)) {
            $context .= "\n🎬 CONTENUTO VIDEO:\n\n";
            foreach ($videoDocs as $doc) {
                $ts = $doc['timestamp'] ?? '';
                $tsStr = $ts ? " [{$ts}]" : '';
                $title = $doc['title'] ?? 'Video';
                $content = $doc['content'] ?? '';
                $context .= "--- {$title}{$tsStr} ---\n{$content}\n\n";
            }
        }

        $reply = $this->callClaudeForMinerva($data['question'], $data['history'] ?? [], $context, $courseNames, $mode);

        return response()->json([
            'answer' => $reply['content'],
            'tokens' => $reply['tokens'] ?? null,
            'mode' => $mode,
        ]);
    }

    private function callClaudeForMinerva(string $question, array $history, string $context, array $courseNames, string $mode): array
    {
        $coursesList = empty($courseNames) ? 'nessun corso attivo' : implode(', ', $courseNames);
        $isSingleCourse = count($courseNames) === 1;

        $lengthRule = $mode === 'expand'
            ? "Rispondi in modo approfondito e dettagliato, con esempi e sezioni. Usa markdown: ## per titoli di sezione, **grassetto**, liste, citazioni >."
            : "Rispondi in 2-3 frasi brevi, massimo 60 parole complessive. Una risposta diretta, senza liste, senza titoli, senza esempi multipli. Lo studente potrà chiedere l'approfondimento con un tasto dedicato. Usa al massimo un **grassetto** sul concetto centrale. NON usare bullet, numerazioni, markdown di struttura. Vai dritto al punto.";

        $scopeRule = $isSingleCourse
            ? "Lo studente ha accesso SOLO al corso: {$coursesList}. Rispondi basandoti sui contenuti di quel corso. Se la domanda tocca argomenti che vengono approfonditi in ALTRI corsi di Atheneum (non iscritti), accenna brevemente al fatto che 'altri corsi di Atheneum approfondiscono questo tema' — senza nominarli esplicitamente — e offri la risposta più utile possibile sul suo corso."
            : "Lo studente ha accesso ai corsi: {$coursesList}. Rispondi sui contenuti di tutti questi corsi, e anche sul contesto della piattaforma Atheneum Noscite.";

        $systemPrompt = <<<SYSTEM
Sei Minerva, l'assistente AI di Atheneum Noscite.

{$scopeRule}

{$lengthRule}

Regole:
- Rispondi in italiano
- Se citi un video con timestamp, formatta come [MM:SS] — lo studente può cliccarci
- Se citi un documento, cita il titolo
- Non inventare. Se l'informazione non è nei materiali forniti, dillo onestamente e usa il tuo buon senso generale
- Sii diretto, chiaro, incoraggiante

{$context}
SYSTEM;

        $messages = array_values($history);
        $messages[] = ['role' => 'user', 'content' => $question];

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => $mode === 'expand' ? 4096 : 200,
                'system' => $systemPrompt,
                'messages' => $messages,
            ]);

            if ($response->failed()) {
                \Log::error('Minerva Claude API failed', [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);
                return ['content' => 'Errore nella risposta. Riprova.', 'tokens' => null];
            }

            $body = $response->json();
            return [
                'content' => $body['content'][0]['text'] ?? 'Risposta vuota.',
                'tokens' => ($body['usage']['input_tokens'] ?? 0) + ($body['usage']['output_tokens'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return ['content' => 'Assistente momentaneamente non disponibile.', 'tokens' => null];
        }
    }

    public function sendMessage(Request $request)
    {
        $studentId = session('student_id');
        abort_unless($studentId, 403);

        $data = $request->validate([
            'conversation_id' => 'required|uuid',
            'message' => 'required|string|max:4000',
        ]);

        $conversation = ChatConversation::with('course')
            ->where('id', $data['conversation_id'])
            ->where('student_id', $studentId)
            ->firstOrFail();

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $data['message'],
        ]);

        $docs = $this->rag->search($data['message'], $conversation->course_id, 4);
        $videoDocs = $this->rag->searchVideos($data['message'], $conversation->course_id, null, 2);

        $context = '';
        if (!empty($docs) && count($docs) > 0) {
            $context .= "📚 DOCUMENTI DEL CORSO:\n\n";
            foreach ($docs as $doc) {
                $content = is_array($doc) ? $doc['content'] : $doc->content;
                $title = is_array($doc) ? $doc['title'] : $doc->title;
                $context .= "--- {$title} ---\n{$content}\n\n";
            }
        }

        if (!empty($videoDocs)) {
            $context .= "\n🎬 CONTENUTO VIDEO DEL CORSO:\n\n";
            foreach ($videoDocs as $doc) {
                $ts = $doc['timestamp'] ? " [{$doc['timestamp']}]" : '';
                $context .= "--- {$doc['title']}{$ts} ---\n{$doc['content']}\n\n";
            }
        }

        $reply = $this->callClaude($conversation, $data['message'], $context);

        $contextDocs = array_merge(
            array_map(fn($d) => is_array($d) ? $d['title'] : $d->title, $docs->all()),
            array_map(fn($d) => $d['title'] . ($d['timestamp'] ? ' [' . $d['timestamp'] . ']' : ''), $videoDocs)
        );

        $aiMessage = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $reply['content'],
            'tokens_used' => $reply['tokens'] ?? null,
            'context_documents' => $contextDocs,
        ]);

        $timestamps = [];
        preg_match_all('/\[(\d{1,2}:\d{2}(?::\d{2})?)\]/', $reply['content'], $matches);
        if (!empty($matches[1])) {
            $timestamps = array_values(array_unique($matches[1]));
        }

        return response()->json([
            'message' => $aiMessage->content,
            'id' => $aiMessage->id,
            'timestamps' => $timestamps,
        ]);
    }

    private function callClaude(ChatConversation $conversation, string $userMessage, string $context = ''): array
    {
        $history = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        $history[] = ['role' => 'user', 'content' => $userMessage];

        $courseName = $conversation->course?->name ?? 'Atheneum';

        $systemPrompt = <<<SYSTEM
Sei Minerva, l'assistente AI del corso {$courseName} di Atheneum Noscite.

Il tuo ruolo:
- Aiutare gli studenti a comprendere i contenuti del corso
- Rispondere basandoti sui DOCUMENTI e sui VIDEO del corso
- Quando citi informazioni da un video, indica SEMPRE il timestamp [MM:SS]
- Quando citi informazioni da un documento, cita il titolo del documento
- Chiarire concetti difficili con esempi pratici legati alle PMI italiane

Regole:
- Rispondi SEMPRE in italiano
- Basa le risposte PRINCIPALMENTE sui contenuti del corso forniti
- Se citi un timestamp video, formattalo come [MM:SS] — lo studente può cliccarci per saltare al punto
- Se non sai qualcosa, dillo onestamente
- Sii chiaro, diretto, incoraggiante

{$context}
SYSTEM;

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-5',
                'max_tokens' => 2048,
                'system' => $systemPrompt,
                'messages' => $history,
            ]);

            if ($response->failed()) {
                return ['content' => 'Errore nella risposta dell\'assistente. Riprova.', 'tokens' => null];
            }

            $body = $response->json();
            return [
                'content' => $body['content'][0]['text'] ?? 'Risposta vuota.',
                'tokens' => ($body['usage']['input_tokens'] ?? 0) + ($body['usage']['output_tokens'] ?? 0),
            ];
        } catch (\Throwable $e) {
            return ['content' => 'Assistente momentaneamente non disponibile.', 'tokens' => null];
        }
    }
}
