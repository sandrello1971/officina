<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateCourseOutlineJob;
use App\Models\Admin;
use App\Models\Course;
use App\Models\CourseGenerationRun;
use Illuminate\Http\Request;

/**
 * Motore generazione corsi da KB — Fase 0: il formatore compila il brief
 * (target, livello, durata, obiettivi, tono, vincoli) prima che l'AI generi
 * anche solo la struttura del corso. Nessuna generazione parte "alla cieca"
 * dai soli materiali caricati.
 */
class CourseGenerationController extends Controller
{
    public function create(Course $course)
    {
        abort_if($course->courseLevelMaterials()->doesntExist(), 422,
            'Carica prima i materiali sorgente del corso (documenti da cui generare i contenuti).');

        $runningRun = $course->generationRuns()->where('status', 'running')->latest()->first();

        return view('admin.course-generation.brief', compact('course', 'runningRun'));
    }

    public function store(Request $request, Course $course)
    {
        $data = $request->validate([
            'target' => 'nullable|string|max:500',
            'level' => 'nullable|string|max:100',
            'duration_hours' => 'nullable|integer|min:1|max:1000',
            'modules_count' => 'nullable|integer|min:1|max:20',
            'objectives' => 'nullable|string|max:2000',
            'tone' => 'nullable|string|max:100',
            'language' => 'nullable|string|max:50',
            'constraints' => 'nullable|string|max:2000',
        ]);

        if ($course->generationRuns()->where('status', 'running')->exists()) {
            return back()->with('error', 'Una generazione è già in corso per questo corso: aspetta che finisca prima di lanciarne un\'altra.');
        }

        $run = CourseGenerationRun::create([
            'course_id' => $course->id,
            'status' => 'running',
            'phase' => 'outline',
            'brief' => array_filter($data, fn ($v) => $v !== null && $v !== ''),
            'triggered_by' => $this->adminId(),
            'started_at' => now(),
        ]);

        GenerateCourseOutlineJob::dispatch($run->id)->afterResponse();

        return redirect()->route('admin.course-generation.show', $run)
            ->with('success', 'Generazione avviata: proposta di struttura del corso in elaborazione, richiede qualche minuto.');
    }

    private function adminId(): ?string
    {
        return Admin::where('email', session('admin_email'))->value('id');
    }
}
