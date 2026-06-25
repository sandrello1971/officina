<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateVideoScriptJob;
use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleVideo;

// V1 — video narrato di un MODULO (lato admin). Solo generazione del COPIONE dalla
// presentazione PUBBLICATA. Gemello di Docente\LessonVideoController.
class ModuleVideoController extends Controller
{
    private function ensureInCourse(Course $course, Module $module): void
    {
        abort_unless($module->course_id === $course->id, 404);
    }

    private function publishedPresentation(Module $module)
    {
        return $module->presentations()->where('status', 'ready')
            ->whereNotNull('published_at')->latest('published_at')->first();
    }

    private function currentVideo(Module $module): ?ModuleVideo
    {
        $published = $this->publishedPresentation($module);

        return $published
            ? $module->videos()->where('presentation_id', $published->id)->latest()->first()
            : null;
    }

    public function generateScript(Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);

        $published = $this->publishedPresentation($module);
        abort_unless($published, 422,
            'Pubblica prima le slide: il video si genera dalla presentazione pubblicata.');
        abort_unless(!empty($published->spec), 422,
            'Rigenera le slide dal sistema per abilitare il copione del video.');

        $video = $module->videos()->firstOrCreate(
            ['presentation_id' => $published->id],
            ['status' => 'pending', 'script_status' => 'none']
        );

        if ($video->status === 'generating') {
            return back()->with('success', 'Generazione copione già in corso.');
        }

        $video->update(['status' => 'generating']);
        GenerateVideoScriptJob::dispatch($video->id, 'module')->afterResponse();

        return back()->with('success', 'Generazione del copione avviata. Sarà pronto a breve.');
    }

    public function status(Course $course, Module $module)
    {
        $this->ensureInCourse($course, $module);
        $video = $this->currentVideo($module);

        return response()->json([
            'status' => $video?->status ?? 'none',
            'script_status' => $video?->script_status ?? 'none',
            'failure_reason' => $video?->generation_meta['failure_reason'] ?? null,
        ]);
    }
}
