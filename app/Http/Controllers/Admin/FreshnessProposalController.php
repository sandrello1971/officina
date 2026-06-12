<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunFreshnessAgentJob;
use App\Models\Admin;
use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\UpdateProposal;
use App\Services\Freshness\ProposalApplicator;
use Illuminate\Http\Request;

/**
 * P25.3b — Coda HITL delle proposte di aggiornamento corsi.
 *
 * ADDITIVO: nuova sezione admin, non tocca CourseController/ModuleController né alcun
 * percorso di modifica manuale del corso.
 *
 * HITL (non negoziabile): qui si VEDE il diff e si cambia SOLO lo status delle proposte
 * (approved/rejected). NESSUN endpoint applica alcunché al contenuto del corso —
 * l'applicazione reale (course_sources/modules) è P25.3c e consuma solo 'approved'.
 */
class FreshnessProposalController extends Controller
{
    /** Coda: proposte PENDING raggruppate per corso (il diff è sempre mostrato). */
    public function index(Request $request)
    {
        $courseFilter = $request->query('course');

        $query = UpdateProposal::with(['course', 'claim'])
            ->pending()
            ->orderBy('course_id')
            ->orderByDesc('created_at');

        if ($courseFilter) {
            $query->where('course_id', $courseFilter);
        }

        $proposals = $query->get()->groupBy('course_id');

        // Corsi che hanno almeno una proposta pending (per il filtro) + audience dalla config.
        $courses = Course::whereHas('updateProposals', fn ($q) => $q->where('status', 'pending'))
            ->with('freshnessConfig')
            ->orderBy('name')
            ->get();

        // Tutti i corsi attivi (per il pannello "Controlli & cadenze": lancio manuale +
        // gestione cadenza/audience + applicazione delle approvate).
        $allCourses = Course::active()
            ->with('freshnessConfig')
            ->withCount(['updateProposals as approved_count' => fn ($q) => $q->where('status', 'approved')])
            ->orderBy('name')
            ->get();

        return view('admin.freshness.proposals', compact('proposals', 'courses', 'courseFilter', 'allCourses'));
    }

    /**
     * P25.3d — Lancia un controllo (freshness-run) ASINCRONO su un corso. Solo dispatch:
     * il run gira sulla queue (chiamate AI lente). NON applica nulla.
     */
    public function run(Request $request)
    {
        $validated = $request->validate(['course_id' => 'required|uuid|exists:courses,id']);
        $course = Course::find($validated['course_id']);

        RunFreshnessAgentJob::dispatch($course->id);

        return back()->with('success', "Controllo avviato per «{$course->name}». L'estrazione fa chiamate AI e può richiedere qualche minuto: le proposte appariranno qui a breve.");
    }

    /** P25.3d — Imposta la cadenza dello scheduler per un corso. */
    public function setCadence(Request $request, Course $course)
    {
        $validated = $request->validate(['cadence' => 'required|in:off,weekly,monthly,quarterly']);

        CourseFreshnessConfig::updateOrCreate(
            ['course_id' => $course->id],
            ['cadence' => $validated['cadence']]
        );

        return back()->with('success', "Cadenza aggiornata per «{$course->name}»: {$validated['cadence']}.");
    }

    /** P25.3e — Override manuale (autorevole) dell'audience: marca audience_overridden. */
    public function setAudience(Request $request, Course $course)
    {
        $validated = $request->validate(['audience' => 'required|in:adult,minor']);

        CourseFreshnessConfig::updateOrCreate(
            ['course_id' => $course->id],
            ['audience' => $validated['audience'], 'audience_overridden' => true]
        );

        return back()->with('success', "Audience aggiornato per «{$course->name}»: {$validated['audience']} (override manuale).");
    }

    /**
     * P25.3e — Applica le proposte APPROVED al lato formatore (versioning + rollback in 3c).
     * Doppio gate per i corsi MINORI: serve la conferma esplicita aggiuntiva (confirm_minor).
     */
    public function apply(Request $request, Course $course, ProposalApplicator $applicator)
    {
        $audience = optional($course->freshnessConfig)->audience ?? 'adult';
        $confirmed = $request->boolean('confirm_minor');

        // Gate 2 (umano) per i minori: senza conferma esplicita → bloccato, nessuna modifica.
        if ($audience === 'minor' && !$confirmed) {
            return back()->with('error', "⚠ Corso per MINORI: serve la conferma esplicita di applicazione. Nessuna modifica applicata a «{$course->name}».");
        }

        $res = $applicator->apply($course, minorConfirmed: $confirmed);

        if (($res['blocked'] ?? null) === 'minor_confirmation_required') {
            return back()->with('error', "⚠ Conferma minori richiesta: nessuna modifica applicata a «{$course->name}».");
        }

        $msg = "Applicate {$res['applied']} proposte su «{$course->name}»";
        if ($res['version_to']) {
            $msg .= " (v{$res['version_from']} → v{$res['version_to']})";
        }
        if (!empty($res['failed'])) {
            $msg .= '. ' . count($res['failed']) . ' non applicate (before non trovato/non unico).';
        }

        return back()->with('success', $msg . '.');
    }

    /**
     * Approva una proposta. Se l'admin ha editato l'`after` (campo diverso) → la modifica
     * viene registrata con after_edited_by_human=true. Solo su proposte 'pending'.
     */
    public function approve(Request $request, UpdateProposal $proposal)
    {
        abort_unless($proposal->status === 'pending', 422, 'La proposta non è più in attesa.');

        $data = [
            'status' => 'approved',
            'reviewed_by' => $this->adminId(),
            'reviewed_at' => now(),
        ];

        $newAfter = trim((string) $request->input('after', ''));
        if ($newAfter !== '' && $newAfter !== $proposal->after) {
            $data['after'] = $newAfter;
            $data['after_edited_by_human'] = true;
        }

        $proposal->update($data);

        return back()->with('success', 'Proposta approvata. Verrà applicata in fase di applicazione (P25.3c).');
    }

    /** Rifiuta una proposta. Solo su proposte 'pending'. */
    public function reject(UpdateProposal $proposal)
    {
        abort_unless($proposal->status === 'pending', 422, 'La proposta non è più in attesa.');

        $proposal->update([
            'status' => 'rejected',
            'reviewed_by' => $this->adminId(),
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Proposta rifiutata.');
    }

    /** Azione massiva sulle proposte selezionate (solo cambio status, mai applicazione). */
    public function bulk(Request $request)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'ids' => 'required|array',
            'ids.*' => 'uuid',
        ]);

        $status = $validated['action'] === 'approve' ? 'approved' : 'rejected';

        $count = UpdateProposal::whereIn('id', $validated['ids'])
            ->where('status', 'pending')
            ->update([
                'status' => $status,
                'reviewed_by' => $this->adminId(),
                'reviewed_at' => now(),
            ]);

        return back()->with('success', "{$count} proposte aggiornate ({$status}).");
    }

    /** Admin loggato (sessione custom) → uuid per l'audit. Null se non risolvibile. */
    private function adminId(): ?string
    {
        return Admin::where('email', session('admin_email'))->value('id');
    }
}
