<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Jobs\IngestPublicationRagJob;
use App\Jobs\PurgeWithdrawnPublicationJob;
use App\Models\ArtifactPublication;
use App\Models\SchoolClass;
use App\Models\TeachingArtifact;
use Illuminate\Http\Request;

// Pubblicazione di un artefatto su una o più classi del docente, e ritiro.
// L'ingestion RAG (scope='class') è asincrona: feedback UX via rag_status +
// polling. abort_unless owner ovunque.
class PublicationController extends Controller
{
    private function teacherId(): string
    {
        return session('student_id');
    }

    private function authorizeArtifact(TeachingArtifact $artifact): void
    {
        abort_unless($artifact->teacher_id === $this->teacherId(), 403);
    }

    public function store(Request $request, TeachingArtifact $artifact)
    {
        $this->authorizeArtifact($artifact);
        abort_unless($artifact->status === 'ready', 422, 'Solo gli artefatti pronti possono essere pubblicati.');

        $data = $request->validate([
            'class_ids' => 'required|array|min:1',
            'class_ids.*' => 'uuid',
            'students_can_generate' => 'sometimes|boolean',
            'downloadable' => 'sometimes|boolean',
        ]);

        // Solo classi del docente (defense in depth: niente pubblicazione altrui).
        $ownClassIds = SchoolClass::where('teacher_id', $this->teacherId())
            ->whereIn('id', $data['class_ids'])
            ->pluck('id')
            ->all();
        abort_if(count($ownClassIds) !== count(array_unique($data['class_ids'])), 403, 'Classe non valida.');

        foreach ($ownClassIds as $classId) {
            $publication = ArtifactPublication::updateOrCreate(
                ['teaching_artifact_id' => $artifact->id, 'school_class_id' => $classId],
                [
                    'students_can_generate' => (bool) ($data['students_can_generate'] ?? true),
                    'downloadable' => (bool) ($data['downloadable'] ?? false),
                    'published_at' => now(),
                    'rag_status' => 'pending',
                    'rag_failure_reason' => null,
                ]
            );

            IngestPublicationRagJob::dispatch($publication->id);
        }

        return redirect()->route('docente.artifacts.show', $artifact)
            ->with('success', 'Pubblicazione avviata: indicizzazione in corso…');
    }

    public function destroy(ArtifactPublication $publication)
    {
        $artifact = $publication->artifact;
        abort_unless($artifact && $artifact->teacher_id === $this->teacherId(), 403);

        $publicationId = $publication->id;
        $publication->delete();

        // Pulizia RAG dei chunk class (idempotente, per publication_id).
        PurgeWithdrawnPublicationJob::dispatch($publicationId);

        return redirect()->route('docente.artifacts.show', $artifact)
            ->with('success', 'Pubblicazione ritirata.');
    }

    public function status(TeachingArtifact $artifact)
    {
        $this->authorizeArtifact($artifact);

        $publications = $artifact->publications()->with('schoolClass')->get()->map(fn ($p) => [
            'id' => $p->id,
            'school_class_id' => $p->school_class_id,
            'class_name' => $p->schoolClass?->name,
            'rag_status' => $p->rag_status,
            'rag_failure_reason' => $p->rag_failure_reason,
        ]);

        return response()->json(['publications' => $publications]);
    }
}
