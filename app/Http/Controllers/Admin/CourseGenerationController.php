<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateCourseOutlineJob;
use App\Models\Admin;
use App\Models\Course;
use App\Models\CourseGenerationRun;
use App\Models\CourseTopic;
use App\Models\DocumentRag;
use App\Models\TrustedSource;
use App\Services\CourseGeneration\CourseKbSourceRanker;
use App\Services\SourceSuggester;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Motore generazione corsi da KB.
 * Fase 0 (brief): il formatore indica argomento, target, livello, obiettivi,
 * tono, vincoli — nessuna generazione parte "alla cieca" dai soli materiali.
 * Fase 1 (sources): dato l'argomento, propone/filtra le fonti da usare (tra
 * quelle già caricate sul corso e — su richiesta — fonti esterne autorevoli),
 * SEMPRE con conferma umana prima di generare qualunque cosa.
 */
class CourseGenerationController extends Controller
{
    public function create(Course $course)
    {
        if (!$this->hasAnySource($course)) {
            return redirect()->route('admin.rag.index', ['course_id' => $course->id])
                ->with('error', 'Carica prima almeno un documento (o video) sorgente del corso da cui generare i contenuti.');
        }

        $openRun = $course->generationRuns()->where('status', '!=', 'failed')->where('phase', '!=', 'done')->latest()->first();
        if ($openRun) {
            return redirect()->route('admin.course-generation.show', $openRun);
        }

        return view('admin.course-generation.brief', compact('course'));
    }

    public function store(Request $request, Course $course)
    {
        $data = $request->validate([
            'topic' => 'required|string|max:255',
            'target' => 'nullable|string|max:500',
            'level' => 'nullable|string|max:100',
            'duration_hours' => 'nullable|integer|min:1|max:1000',
            'modules_count' => 'nullable|integer|min:1|max:20',
            'objectives' => 'nullable|string|max:2000',
            'tone' => 'nullable|string|max:100',
            'language' => 'nullable|string|max:50',
            'constraints' => 'nullable|string|max:2000',
        ]);

        if ($course->generationRuns()->where('status', 'running')->where('phase', '!=', 'sources')->exists()) {
            return back()->with('error', 'Una generazione è già in corso per questo corso: aspetta che finisca prima di lanciarne un\'altra.');
        }

        $this->setCourseTopic($course, $data['topic']);
        $brief = array_filter($data, fn ($v) => $v !== null && $v !== '');
        $brief['topic'] = $data['topic'];

        $run = CourseGenerationRun::create([
            'course_id' => $course->id,
            'status' => 'running',
            'phase' => 'sources',
            'brief' => $brief,
            'triggered_by' => $this->adminId(),
            'started_at' => now(),
        ]);

        return redirect()->route('admin.course-generation.sources', $run);
    }

    /** Tappa "Seleziona le fonti": ranking dei documenti già presenti + proposta fonti esterne. */
    public function sources(CourseGenerationRun $run, CourseKbSourceRanker $ranker)
    {
        abort_unless($run->isSourcesPending(), 422, 'Questa run non è più in fase di selezione fonti.');

        $run->load('course');
        $topic = (string) ($run->brief['topic'] ?? '');
        $topicSlug = Str::slug($topic);

        $p26Enabled = (bool) config('services.p26.enabled');
        $ranked = $ranker->rank($run->course, $topic);
        $trustedSources = $p26Enabled
            ? TrustedSource::where('topic', $topicSlug)->orderByDesc('created_at')->get()
            : collect();

        return view('admin.course-generation.sources', compact('run', 'ranked', 'trustedSources', 'p26Enabled'));
    }

    /** Chiama SourceSuggester per l'argomento del run — isolato: un errore non blocca la pagina. */
    public function suggestExternalSources(CourseGenerationRun $run, SourceSuggester $suggester)
    {
        abort_unless($run->isSourcesPending(), 422, 'Questa run non è più in fase di selezione fonti.');
        abort_unless(config('services.p26.enabled'), 404);

        $topic = (string) ($run->brief['topic'] ?? '');

        try {
            $result = $suggester->suggest($topic);
            return back()->with('success', "{$result['created']} fonte/i esterna/e proposta/e (da approvare in \"Fonti attendibili\").");
        } catch (Throwable $e) {
            return back()->with('error', 'Proposta fonti esterne non riuscita: ' . $e->getMessage());
        }
    }

    /** Conferma la selezione delle fonti e avvia la generazione della struttura del corso. */
    public function confirmSources(Request $request, CourseGenerationRun $run)
    {
        abort_unless($run->isSourcesPending(), 422, 'Questa run non è più in fase di selezione fonti.');

        $data = $request->validate([
            'sources' => 'required|array|min:1',
            'sources.*' => 'string',
        ]);

        $selected = collect($data['sources'])->map(function (string $key) {
            [$type, $identifier] = explode(':', $key, 2);
            return $type === 'document_rag'
                ? ['source_type' => 'document_rag', 'title' => $identifier]
                : ['source_type' => 'material', 'id' => $identifier];
        })->values()->all();

        $run->update([
            'phase' => 'outline',
            'status' => 'running',
            'selected_sources' => $selected,
        ]);

        GenerateCourseOutlineJob::dispatch($run->id)->afterResponse();

        return redirect()->route('admin.course-generation.show', $run)
            ->with('success', 'Generazione avviata: proposta di struttura del corso in elaborazione, richiede qualche minuto.');
    }

    private function hasAnySource(Course $course): bool
    {
        return DocumentRag::where('course_id', $course->id)->exists()
            || $course->courseLevelMaterials()->exists();
    }

    /** Un solo topic primary per corso: aggiorna quello esistente invece di duplicarlo. */
    private function setCourseTopic(Course $course, string $topic): void
    {
        $slug = Str::slug($topic);
        if ($slug === '') {
            return;
        }

        $primary = CourseTopic::where('course_id', $course->id)->where('weight', 'primary')->first();
        if ($primary) {
            $primary->update(['topic' => $slug]);
        } else {
            CourseTopic::create(['course_id' => $course->id, 'topic' => $slug, 'weight' => 'primary']);
        }
    }

    private function adminId(): ?string
    {
        return Admin::where('email', session('admin_email'))->value('id');
    }
}
