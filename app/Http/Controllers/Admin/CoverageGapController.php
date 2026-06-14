<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunGapScoutJob;
use App\Models\Admin;
use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\CoverageGap;
use App\Models\GapScoutRun;
use App\Models\TrustedSource;
use Illuminate\Http\Request;

/**
 * P26 Fase A — UI dello Scout di copertura (gated P26_ENABLED). Lancia l'analisi async per corso
 * e gestisce i gap candidati (accetta/scarta, HITL). Solo rilevamento: niente Compose/Insert.
 */
class CoverageGapController extends Controller
{
    public function __construct()
    {
        abort_unless(config('services.p26.enabled'), 404);
    }

    public function index()
    {
        $courses = Course::active()->with('freshnessConfig')
            ->withCount(['coverageGaps as suggested_gaps_count' => fn ($q) => $q->where('status', 'suggested')])
            ->orderBy('name')->get();

        return view('admin.coverage.index', compact('courses'));
    }

    public function show(Course $course)
    {
        $topic = trim((string) optional($course->freshnessConfig)->topic);
        $hasApprovedSources = $topic !== '' && TrustedSource::topic($topic)->approved()->exists();

        $gaps = CoverageGap::forCourse($course->id)->where('status', 'suggested')
            ->orderByDesc('confidence')->orderByDesc('created_at')->get();

        $lastRun = GapScoutRun::where('course_id', $course->id)->orderByDesc('created_at')->first();
        $sourceTopics = TrustedSource::query()->distinct()->orderBy('topic')->pluck('topic');

        return view('admin.coverage.show', compact('course', 'topic', 'hasApprovedSources', 'gaps', 'lastRun', 'sourceTopics'));
    }

    /** Imposta il topic del corso SCEGLIENDO tra i topic esistenti nelle fonti (no drift). */
    public function setTopic(Request $request, Course $course)
    {
        $valid = TrustedSource::query()->distinct()->pluck('topic')->all();
        $data = $request->validate(['topic' => ['required', 'string', 'in:' . implode(',', $valid ?: ['__none__'])]]);

        CourseFreshnessConfig::updateOrCreate(['course_id' => $course->id], ['topic' => $data['topic']]);

        return back()->with('success', "Topic del corso impostato a «{$data['topic']}».");
    }

    public function analyze(Course $course)
    {
        $topic = trim((string) optional($course->freshnessConfig)->topic);
        if ($topic === '') {
            return back()->with('error', 'Imposta prima il topic del corso (in base alle fonti disponibili).');
        }
        if (!TrustedSource::topic($topic)->approved()->exists()) {
            return back()->with('error', "Nessuna fonte attendibile approvata per «{$topic}». Aggiungi/approva fonti per questo dominio prima di analizzare.");
        }

        RunGapScoutJob::dispatch($course->id);

        return back()->with('success', "Analisi di copertura avviata per «{$course->name}». Gira in background (cerca solo nelle fonti approvate): ricarica tra poco per vedere i gap candidati.");
    }

    public function accept(CoverageGap $gap)
    {
        $gap->update(['status' => 'accepted', 'reviewed_by' => $this->adminId(), 'reviewed_at' => now()]);

        return back()->with('success', "Gap «{$gap->title}» accettato (entrerà nelle fasi di stesura).");
    }

    public function dismiss(CoverageGap $gap)
    {
        $gap->update(['status' => 'dismissed', 'reviewed_by' => $this->adminId(), 'reviewed_at' => now()]);

        return back()->with('success', 'Gap scartato.');
    }

    private function adminId(): ?string
    {
        return Admin::where('email', session('admin_email'))->value('id');
    }
}
