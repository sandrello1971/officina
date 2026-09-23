<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\IngestRagVideoJob;
use App\Models\Course;
use App\Models\DocumentRag;
use App\Services\RagService;
use Illuminate\Http\Request;

class RagController extends Controller
{
    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'avi', 'webm'];

    public function index(Request $request)
    {
        $documents = DocumentRag::with(['course', 'module'])
            ->select('id', 'title', 'course_id', 'module_id', 'chunk_index', 'created_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        $courses = Course::orderBy('sort_order')->get();
        $selectedCourseId = $request->query('course_id');

        // Riuso tra corsi: documenti già caricati su ALTRI corsi, raggruppati
        // per (corso, titolo) — così non serve ricaricare lo stesso file per
        // ogni corso. Ha senso solo quando si arriva qui da un corso preciso.
        $existingElsewhere = collect();
        if ($selectedCourseId) {
            $existingElsewhere = DocumentRag::with('course:id,name,icon')
                ->where('course_id', '!=', $selectedCourseId)
                ->whereNotNull('course_id')
                ->orderBy('title')
                ->get(['id', 'title', 'course_id'])
                ->groupBy(fn ($d) => $d->course_id . '|' . $d->title)
                ->map(fn ($group) => [
                    'course_id' => $group->first()->course_id,
                    'course_name' => optional($group->first()->course)->name ?? '—',
                    'title' => $group->first()->title,
                ])
                ->values();
        }

        return view('admin.rag.index', compact('documents', 'courses', 'selectedCourseId', 'existingElsewhere'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|mimes:pdf,doc,docx,txt,pptx,mp4,mov,avi,webm|max:204800',
            'course_id' => 'required|uuid',
            'module_id' => 'nullable|uuid',
            'title' => 'nullable|string|max:255',
        ]);

        $ragService = app(RagService::class);
        $uploaded = 0;
        $skipped = 0;
        $queued = 0;
        $lastText = '';

        foreach ($request->file('files') as $file) {
            $title = $request->title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
            $ext = strtolower($file->getClientOriginalExtension());

            if (in_array($ext, self::VIDEO_EXTENSIONS, true)) {
                $path = $file->store('rag-documents', 'public');
                IngestRagVideoJob::dispatch($path, $title, $request->course_id, $request->module_id ?: null);
                $queued++;
                continue;
            }

            $path = $file->store('rag-documents', 'public');
            $text = $this->extractText($file);

            if (empty(trim($text))) {
                $skipped++;
                continue;
            }

            $ragService->indexDocument(
                $text,
                $title,
                $request->course_id,
                $request->module_id ?: null,
                $path
            );
            $uploaded++;
            $lastText .= "\n\n" . $text;
        }

        $successMsg = "{$uploaded} documento/i indicizzato/i";
        if ($queued > 0) {
            $successMsg .= ", {$queued} video in trascrizione (comparirà/comparranno tra qualche minuto)";
        }
        if ($skipped > 0) {
            $successMsg .= " ({$skipped} saltato/i per testo vuoto)";
        }

        if ($request->boolean('generate_quiz')) {
            $course = \App\Models\Course::find($request->course_id);
            if ($course && !empty(trim($lastText))) {
                $generator = app(\App\Services\QuizGeneratorService::class);
                $quiz = $generator->generateFromContent($course, $lastText, 10);
                if ($quiz) {
                    $successMsg .= " + Quiz generato automaticamente ({$quiz->questions()->count()} domande).";
                }
            }
        }

        return back()->with('success', $successMsg . '.');
    }

    /** Riusa un documento già indicizzato su un ALTRO corso: ricompone il testo dai suoi chunk e lo re-indicizza per il corso di destinazione (nessun riferimento condiviso: righe DocumentRag indipendenti, stesso trattamento di un upload). */
    public function attachExisting(Request $request)
    {
        $data = $request->validate([
            'source_course_id' => 'required|uuid',
            'title' => 'required|string',
            'target_course_id' => 'required|uuid|different:source_course_id',
        ]);

        $chunks = DocumentRag::where('course_id', $data['source_course_id'])
            ->where('title', $data['title'])
            ->orderBy('chunk_index')
            ->get(['content', 'chunk_index', 'file_path']);

        if ($chunks->isEmpty()) {
            return back()->with('error', 'Documento sorgente non trovato (forse è stato rimosso nel frattempo).');
        }

        $text = DocumentRag::reassembleGroup($chunks);
        app(RagService::class)->indexDocument($text, $data['title'], $data['target_course_id'], null, $chunks->first()->file_path);

        return back()->with('success', "«{$data['title']}» aggiunto a questo corso.");
    }

    private function extractText($file): string
    {
        return app(\App\Services\CourseIngestionService::class)->extractText($file);
    }

    public function destroy($id)
    {
        DocumentRag::findOrFail($id)->delete();
        return back()->with('success', 'Documento rimosso.');
    }
}
