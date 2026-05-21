<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    public function store(StoreMessageRequest $request, Conversation $conversation)
    {
        $user = Student::findOrFail(session('student_id'));

        if (!$user->can('reply', $conversation)) {
            abort(403);
        }

        $data = $request->validated();

        DB::transaction(function () use ($conversation, $user, $data) {
            Message::create([
                'conversation_id' => $conversation->id,
                'sender_id'       => $user->id,
                'body'            => $data['body'],
            ]);

            $conversation->update(['last_message_at' => now()]);
        });

        // Nessuna email su messaggi follow-up (decisione 7a).
        // Fase C: qui broadcast Reverb MessageSent event.

        return redirect()->route('student.messages.show', $conversation);
    }
}
