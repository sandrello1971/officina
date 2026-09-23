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

        return view('admin.rag.index', compact('documents', 'courses', 'selectedCourseId'));
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
