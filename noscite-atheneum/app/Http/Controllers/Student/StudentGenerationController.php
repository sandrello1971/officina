<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\ResolvesScholaAccess;
use App\Jobs\StudentGenerateArtifactJob;
use App\Models\ArtifactPublication;
use App\Models\SchoolClass;
use App\Models\StudentGeneratedArtifact;
use App\Services\Schola\ScholaUsage;
use Illuminate\Http\Request;

// Auto-generazione lato studente (mindmap | quiz di autoverifica) dal contenuto
// dell'artefatto pubblicato. Richiede students_can_generate. Rate limit §8.2.
class StudentGenerationController extends Controller
{
    use ResolvesScholaAccess;

    public function store(Request $request, SchoolClass $class, ArtifactPublication $publication)
    {
        $student = $this->currentStudent();
        $this->assertActiveEnrollment($class, $student->id);
        $this->assertPublicationInClass($publication, $class);

        abort_unless($publication->students_can_generate, 403,
            'L\'auto-generazione non è abilitata per questo materiale.');

        $data = $request->validate([
            'type' => 'required|in:mindmap,quiz',
            'num_questions' => 'nullable|integer|min:3|max:15',
        ]);

        // Rate limit giornaliero (§8.2): blocco gentile a soglia raggiunta.
        $usage = app(ScholaUsage::class);
        if (!$usage->generationStatus($student->id)['allowed']) {
            return back()->with('error', $usage->limitMessage('generation'));
        }

        $options = $data['type'] === 'quiz'
            ? ['num_questions' => (int) ($data['num_questions'] ?? 8)]
            : [];

        $gen = StudentGeneratedArtifact::create([
            'student_id' => $student->id,
            'artifact_publication_id' => $publication->id,
            'type' => $data['type'],
            'status' => 'generating',
        ]);

        // afterResponse: risposta immediata anche con QUEUE=sync (feedback UX).
        StudentGenerateArtifactJob::dispatch($gen->id, $options)->afterResponse();

        return redirect()
            ->route('student.classes.artifact.show', [$class, $publication])
            ->with('success', 'Generazione avviata: sarà pronta tra poco.');
    }

    /** Polling stato di una generazione dello studente. */
    public function status(SchoolClass $class, ArtifactPublication $publication, StudentGeneratedArtifact $generated)
    {
        $student = $this->currentStudent();
        $this->assertActiveEnrollment($class, $student->id);
        abort_unless($generated->student_id === $student->id
            && $generated->artifact_publication_id === $publication->id, 403);

        return response()->json([
            'status' => $generated->status,
            'type' => $generated->type,
            'quiz_id' => $generated->quiz_id,
            'failure_reason' => $generated->failure_reason,
        ]);
    }
}
