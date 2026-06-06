<?php

namespace App\Http\Controllers\Docente;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateArtifactJob;
use App\Models\TeachingArtifact;
use App\Models\TeachingDocument;
use Illuminate\Http\Request;

// Generazione artefatti lavorati da un materiale grezzo "ready".
// Tipi generabili: summary | mindmap | conceptmap | quiz | outline.
// (transcript NON è generabile qui: nasce automaticamente dall'estrazione.)
class ArtifactGenerationController extends Controller
{
    private const GENERABLE = ['summary', 'mindmap', 'conceptmap', 'quiz', 'outline'];

    private function teacherId(): string
    {
        return session('student_id');
    }

    /**
     * Crea un artefatto in stato "generating" e dispatcha il job di generazione.
     */
    public function store(Request $request, TeachingDocument $document)
    {
        abort_unless($document->teacher_id === $this->teacherId(), 403);
        abort_unless($document->status === 'ready' && !empty($document->extracted_text), 422,
            'Il materiale non è ancora pronto: attendi la fine dell\'estrazione del testo.');

        $data = $request->validate([
            'type' => 'required|in:' . implode(',', self::GENERABLE),
            'level' => 'nullable|in:breve,medio,dispensa',
            'num_questions' => 'nullable|integer|min:3|max:20',
        ]);

        $options = $this->options($data);

        $artifact = TeachingArtifact::create([
            'teaching_document_id' => $document->id,
            'teacher_id' => $document->teacher_id,
            'type' => $data['type'],
            'title' => $this->defaultTitle($data['type'], $document->title),
            'subject_id' => $document->subject_id,
            'status' => 'generating',
            'generation_meta' => ['requested_options' => $options],
        ]);

        GenerateArtifactJob::dispatch($artifact->id, $options);

        return redirect()->route('docente.artifacts.show', $artifact)
            ->with('success', 'Generazione avviata. L\'artefatto sarà pronto a breve.');
    }

    /**
     * Rigenera un artefatto esistente: SOVRASCRIVE il contenuto (conferma
     * esplicita richiesta lato UI). Non applicabile alla trascrizione.
     */
    public function regenerate(Request $request, TeachingArtifact $artifact)
    {
        abort_unless($artifact->teacher_id === $this->teacherId(), 403);
        abort_unless(in_array($artifact->type, self::GENERABLE, true), 422,
            'Questo tipo di artefatto non può essere rigenerato.');
        abort_unless($artifact->teachingDocument !== null, 422,
            'Artefatto senza materiale di origine: rigenerazione non disponibile.');

        $data = $request->validate([
            'level' => 'nullable|in:breve,medio,dispensa',
            'num_questions' => 'nullable|integer|min:3|max:20',
        ]);

        $options = $this->options(array_merge(['type' => $artifact->type], $data));

        $artifact->update([
            'status' => 'generating',
            'generation_meta' => array_merge((array) $artifact->generation_meta, [
                'requested_options' => $options,
            ]),
        ]);

        GenerateArtifactJob::dispatch($artifact->id, $options);

        return redirect()->route('docente.artifacts.show', $artifact)
            ->with('success', 'Rigenerazione avviata: il contenuto verrà sovrascritto.');
    }

    private function options(array $data): array
    {
        return match ($data['type']) {
            'summary' => ['level' => $data['level'] ?? 'medio'],
            'quiz' => ['num_questions' => (int) ($data['num_questions'] ?? 10)],
            default => [],
        };
    }

    private function defaultTitle(string $type, string $docTitle): string
    {
        $prefix = match ($type) {
            'summary' => 'Riassunto',
            'mindmap' => 'Mappa mentale',
            'conceptmap' => 'Mappa concettuale',
            'quiz' => 'Quiz',
            'outline' => 'Scaletta',
            default => 'Artefatto',
        };

        return "{$prefix} — {$docTitle}";
    }
}
