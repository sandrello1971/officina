<?php

namespace App\Http\Controllers\Scuola;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Scuola\Concerns\ResolvesSchoolAccess;
use App\Models\Student;
use App\Models\Subject;
use App\Services\Schola\TeacherImportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Elenco + inserimento singolo docenti della PROPRIA scuola (tenancy via
// ResolvesSchoolAccess). L'inserimento singolo riusa TeacherImportService.
class TeacherController extends Controller
{
    use ResolvesSchoolAccess;

    public function index(): View
    {
        $school = $this->currentSchool();

        $teachers = Student::where('school_id', $school->id)
            ->where('role', 'professor')
            ->with('teachableSubjects:id,name')
            ->orderBy('name')
            ->get();

        return view('scuola.docenti.index', compact('teachers'));
    }

    public function create(): View
    {
        $this->currentSchool();
        return view('scuola.docenti.create', ['subjects' => Subject::orderBy('name')->get()]);
    }

    public function store(Request $request, TeacherImportService $service)
    {
        $school = $this->currentSchool();

        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'cognome' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'materie' => 'sometimes|array',
            'materie.*' => 'uuid|exists:subjects,id',
        ]);

        $subjectNames = Subject::whereIn('id', $data['materie'] ?? [])->pluck('name')->all();

        $out = $service->commitSingle([
            'nome' => $data['nome'], 'cognome' => $data['cognome'],
            'email' => $data['email'], 'materie' => $subjectNames,
        ], $school);

        return $this->feedback($out, route('scuola.docenti.index'), 'Docente');
    }

    /** Traduce l'esito del commit singolo in messaggi UX coerenti. */
    private function feedback(array $out, string $back, string $label)
    {
        $row = $out['row'] ?? null;
        $result = $out['result'] ?? [];
        $status = $row['status'] ?? 'error';

        if ($status === 'conflict') {
            return redirect()->back()->with('error', "$label non aggiunto: l'email appartiene già a un account di un'altra scuola.");
        }
        if ($status === 'error') {
            return redirect()->back()->with('error', "$label non aggiunto: " . ($row['message'] ?? 'dati non validi.'));
        }
        if ($status === 'attach') {
            return redirect($back)->with('success', "$label agganciato all'account esistente (eventuali corsi/ruoli preservati).");
        }
        if (($result['updated'] ?? 0) > 0) {
            return redirect($back)->with('success', "$label già presente: aggiornato.");
        }

        return redirect($back)->with('success', "$label aggiunto. Invito inviato via email.");
    }
}
