<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunGapScoutJob;
use App\Models\Admin;
use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\CourseSource;
use App\Models\CoverageGap;
use App\Models\GapDraft;
use App\Models\GapInsertion;
use App\Models\GapScoutRun;
use App\Models\Module;
use App\Models\TrustedSource;
use App\Services\GapInserter;
use App\Services\GapPlacer;
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

        // Gap ACCETTATI (Fase B): ognuno con la sua bozza (se generata).
        $accepted = CoverageGap::forCourse($course->id)->where('status', 'accepted')
            ->with('draft')->orderByDesc('updated_at')->get();

        $lastRun = GapScoutRun::where('course_id', $course->id)->orderByDesc('created_at')->first();
        $sourceTopics = TrustedSource::query()->distinct()->orderBy('topic')->pluck('topic');

        return view('admin.coverage.show', compact('course', 'topic', 'hasApprovedSources', 'gaps', 'accepted', 'lastRun', 'sourceTopics'));
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

    // ===================== Fase B — Compose bozze =====================

    /** Genera (o rigenera) la bozza per un gap ACCETTATO. Async. NON inserisce nulla. */
    public function generate(CoverageGap $gap)
    {
        abort_unless($gap->status === 'accepted', 422, 'La bozza si genera solo su un gap accettato.');

        \App\Jobs\RunGapComposeJob::dispatch($gap->id);

        return back()->with('success', "Generazione bozza avviata per «{$gap->title}» (formatore + studente). Ricarica tra poco.");
    }

    public function draftView(CoverageGap $gap)
    {
        abort_unless($gap->status === 'accepted', 404);
        $draft = $gap->draft;
        $course = $gap->course;

        // Per la UI di Posizione (Fase C): heading formatore + moduli studente.
        $source = CourseSource::where('course_id', $course->id)->orderByDesc('created_at')->orderByDesc('id')->first();
        $headings = collect($source?->blocks ?? [])
            ->filter(fn ($b) => in_array(($b['type'] ?? ''), ['PART', 'H1', 'H2'], true) && trim((string) ($b['text'] ?? '')) !== '')
            ->map(fn ($b) => ['id' => $b['id'], 'text' => $b['text']])->values();
        $modules = $course->modules()->get(['id', 'title']);
        $insertion = $draft ? GapInsertion::where('gap_draft_id', $draft->id)->where('status', 'inserted')->latest()->first() : null;
        $isMinor = optional($course->freshnessConfig)->audience === 'minor';

        return view('admin.coverage.draft', compact('gap', 'draft', 'headings', 'modules', 'insertion', 'isMinor'));
    }

    /** Salva le modifiche dell'admin al testo della bozza (resta editabile prima dell'approvazione). */
    public function updateDraft(Request $request, GapDraft $draft)
    {
        $data = $request->validate([
            'formatore_html' => 'nullable|string',
            'studente_html' => 'nullable|string',
        ]);
        $draft->update([
            'formatore_html' => $data['formatore_html'] ?? $draft->formatore_html,
            'studente_html' => $data['studente_html'] ?? $draft->studente_html,
        ]);

        return back()->with('success', 'Bozza salvata.');
    }

    /** Approva la bozza: pronta per la Fase D (inserimento). NON inserisce nulla ora. */
    public function approveDraft(GapDraft $draft)
    {
        $draft->update(['status' => 'approved', 'reviewed_by' => $this->adminId(), 'reviewed_at' => now()]);

        return back()->with('success', 'Bozza approvata: pronta per l\'inserimento (Fase D). Nessuna modifica al corso è stata fatta.');
    }

    public function discardDraft(GapDraft $draft)
    {
        $draft->update(['status' => 'discarded', 'reviewed_by' => $this->adminId(), 'reviewed_at' => now()]);

        return back()->with('success', 'Bozza scartata.');
    }

    // ===================== Fase C — Place (posizione) =====================

    /** L'agente PROPONE una posizione (formatore + studente). L'admin poi conferma/sposta. Isolato. */
    public function proposePlace(CoverageGap $gap)
    {
        $draft = $gap->draft;
        abort_unless($draft, 404);

        try {
            $p = app(GapPlacer::class)->propose($draft);
        } catch (\Throwable $e) {
            return back()->with('error', 'Proposta posizione non riuscita: ' . $e->getMessage());
        }

        $moduleId = Module::where('course_id', $gap->course_id)->whereKey($p['student_module_id'])->value('id');
        $draft->update([
            'place_formatore_block_id' => $p['formatore_after_block_id'],
            'place_student_module_id' => $moduleId,
            'place_student_anchor' => $p['student_anchor'],
            'placement_confirmed' => false, // è una proposta: va confermata a mano
        ]);

        return back()->with('success', 'Posizione proposta — rivedi e conferma. ' . $p['reason']);
    }

    /** L'admin CONFERMA (o corregge) la posizione: solo da qui l'inserimento è abilitato. */
    public function confirmPlace(Request $request, CoverageGap $gap)
    {
        $draft = $gap->draft;
        abort_unless($draft, 404);

        $data = $request->validate([
            'place_formatore_block_id' => 'required|string',
            'place_student_module_id' => 'nullable|string',
            'place_student_anchor' => 'nullable|string',
        ]);

        // Lo studente è opzionale; se indicato, il modulo dev'essere del corso e l'ancora presente.
        $moduleId = null;
        if (!empty($data['place_student_module_id'])) {
            $module = Module::where('course_id', $gap->course_id)->find($data['place_student_module_id']);
            if (!$module) {
                return back()->with('error', 'Modulo studente non valido per questo corso.');
            }
            $anchor = trim((string) ($data['place_student_anchor'] ?? ''));
            if ($anchor === '' || mb_strpos(strip_tags((string) $module->content), $anchor) === false) {
                return back()->with('error', 'Ancora studente assente nel modulo scelto: copia una frase esatta dal modulo.');
            }
            $moduleId = $module->id;
        }

        $draft->update([
            'place_formatore_block_id' => $data['place_formatore_block_id'],
            'place_student_module_id' => $moduleId,
            'place_student_anchor' => $moduleId ? $data['place_student_anchor'] : null,
            'placement_confirmed' => true,
        ]);

        return back()->with('success', 'Posizione confermata: ora puoi inserire.');
    }

    // ===================== Fase D — Insert / Revert =====================

    public function insert(Request $request, CoverageGap $gap)
    {
        $draft = $gap->draft;
        abort_unless($draft, 404);

        try {
            app(GapInserter::class)->insert($draft, $request->boolean('minor_confirmed'));
        } catch (\Throwable $e) {
            if ($e->getMessage() === 'minor_confirmation_required') {
                return back()->with('error', '⚠ Corso per MINORI: serve la conferma esplicita per inserire. Nessuna modifica fatta.');
            }
            return back()->with('error', 'Inserimento non riuscito: ' . $e->getMessage());
        }

        return back()->with('success', 'Sezione inserita nel corso (formatore + studente). È reversibile: usa «Annulla inserimento».');
    }

    public function revert(GapInsertion $insertion)
    {
        app(GapInserter::class)->revert($insertion);

        return back()->with('success', 'Inserimento annullato: il corso è tornato esattamente allo stato precedente.');
    }

    private function adminId(): ?string
    {
        return Admin::where('email', session('admin_email'))->value('id');
    }
}
