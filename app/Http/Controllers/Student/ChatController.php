<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
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

    public function sendMessage(Request $request)
    {
        $studentId = session('student_id');
        abort_unless($studentId, 403);

        $data = $request->validate([
            'conversation_id' => 'required|uuid',
            'message' => 'required|string|max:4000',
        ]);

        $conversation = ChatConversation::where('id', $data['conversation_id'])
            ->where('student_id', $studentId)
            ->firstOrFail();

        ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $data['message'],
        ]);

        $reply = $this->callClaude($conversation, $data['message']);

        $assistantMessage = ChatMessage::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $reply['content'],
            'tokens_used' => $reply['tokens'] ?? null,
        ]);

        return response()->json([
            'reply' => $assistantMessage->content,
            'id' => $assistantMessage->id,
        ]);
    }

    private function callClaude(ChatConversation $conversation, string $userMessage): array
    {
        $history = $conversation->messages()
            ->orderBy('created_at')
            ->get()
            ->map(fn($m) => ['role' => $m->role, 'content' => $m->content])
            ->toArray();

        $history[] = ['role' => 'user', 'content' => $userMessage];

        $systemPrompt = "Sei l'assistente formativo di Atheneum Noscite. "
            . "Rispondi in italiano, chiaro e diretto, senza gergo tecnocratico. "
            . "Stai aiutando uno studente del corso: " . ($conversation->course?->name ?? 'Atheneum') . ". "
            . "Se non sai qualcosa, dillo esplicitamente.";

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
