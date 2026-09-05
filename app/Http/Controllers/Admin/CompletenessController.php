<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompletenessFinding;
use App\Models\Course;
use App\Services\CompletenessAuditor;

/**
 * Completezza della consegna: slide/materiali/manuale/permessi presenti per ogni modulo.
 * A differenza di Freshness e Copertura (P26) non è gated da alcun flag — è un controllo
 * strutturale, non un programma di AI a pagamento — e il pulsante "Verifica ora" gira
 * sempre, sincrono (nessuna chiamata esterna, quindi nessun bisogno di coda).
 */
class CompletenessController extends Controller
{
    public function index()
    {
        $courses = Course::active()
            ->withCount(['completenessFindings as open_findings_count' => fn ($q) => $q->open()])
            ->orderBy('name')->get();

        return view('admin.completeness.index', compact('courses'));
    }

    public function show(Course $course)
    {
        $findings = $course->completenessFindings()->open()
            ->with('module')
            ->orderByDesc('severity')->orderBy('check_type')->get();

        return view('admin.completeness.show', compact('course', 'findings'));
    }

    /** Sincrono: nessuna chiamata AI/esterna, il controllo è filesystem + DB locale. */
    public function run(Course $course, CompletenessAuditor $auditor)
    {
        $r = $auditor->auditAndPersist($course);

        return back()->with('success', "Verifica completata per «{$course->name}»: {$r['created']} nuove segnalazioni, {$r['resolved']} risolte.");
    }

    public function dismiss(CompletenessFinding $finding)
    {
        $finding->update(['dismissed_at' => now()]);

        return back()->with('success', 'Segnalazione ignorata.');
    }
}
