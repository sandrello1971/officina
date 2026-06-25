<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateVideoScriptJob;
use App\Models\Lesson;
use App\Models\LessonVideo;

// V1 — video narrato di una lezione (lato docente). Per ora solo la generazione del
// COPIONE (Claude) dalla presentazione PUBBLICATA. Niente TTS/mp4 (V3). Solo proprietario.
class LessonVideoController extends Controller
{
    private function authorizeOwner(Lesson $lesson): void
    {
        abort_unless($lesson->teacher_id === session('student_id'), 403);
    }

    /** Presentazione pubblicata corrente della lezione (sorgente del video), o null. */
    private function publishedPresentation(Lesson $lesson)
    {
        return $lesson->presentations()->where('status', 'ready')
            ->whereNotNull('published_at')->latest('published_at')->first();
    }

    /** Video agganciato alla presentazione pubblicata corrente, o null. */
    private function currentVideo(Lesson $lesson): ?LessonVideo
    {
        $published = $this->publishedPresentation($lesson);

        return $published
            ? $lesson->videos()->where('presentation_id', $published->id)->latest()->first()
            : null;
    }

    /** Avvia la generazione del copione dalla presentazione pubblicata. */
    public function generateScript(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);

        $published = $this->publishedPresentation($lesson);
        abort_unless($published, 422,
            'Pubblica prima le slide: il video si genera dalla presentazione pubblicata.');
        abort_unless(!empty($published->spec), 422,
            'Rigenera le slide dal sistema per abilitare il copione del video.');

        $video = $lesson->videos()->firstOrCreate(
            ['presentation_id' => $published->id],
            ['status' => 'pending', 'script_status' => 'none']
        );

        if ($video->status === 'generating') {
            return redirect()->route('docente.lessons.show', $lesson)
                ->with('success', 'Generazione copione già in corso.');
        }

        $video->update(['status' => 'generating']);
        GenerateVideoScriptJob::dispatch($video->id, 'lesson')->afterResponse();

        return redirect()->route('docente.lessons.show', $lesson)
            ->with('success', 'Generazione del copione avviata. Sarà pronto a breve.');
    }

    public function status(Lesson $lesson)
    {
        $this->authorizeOwner($lesson);
        $video = $this->currentVideo($lesson);

        return response()->json([
            'status' => $video?->status ?? 'none',
            'script_status' => $video?->script_status ?? 'none',
            'failure_reason' => $video?->generation_meta['failure_reason'] ?? null,
        ]);
    }
}
