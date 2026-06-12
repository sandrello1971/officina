<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Course;
use App\Models\UpdateProposal;
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

        return view('admin.freshness.proposals', compact('proposals', 'courses', 'courseFilter'));
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
