<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\DocumentRag;
use App\Services\RagService;
use Illuminate\Http\Request;

class RagController extends Controller
{
    public function index()
    {
        $documents = DocumentRag::with(['course', 'module'])
            ->select('id', 'title', 'course_id', 'module_id', 'chunk_index', 'created_at')
            ->orderByDesc('created_at')
            ->paginate(20);

        $courses = Course::orderBy('sort_order')->get();

        return view('admin.rag.index', compact('documents', 'courses'));
    }

    public function upload(Request $request)
    {
        $request->validate([
            'files' => 'required|array|min:1',
            'files.*' => 'file|mimes:pdf,doc,docx,txt|max:20480',
            'course_id' => 'required|uuid',
            'module_id' => 'nullable|uuid',
            'title' => 'nullable|string|max:255',
        ]);

        $ragService = app(RagService::class);
        $uploaded = 0;
        $skipped = 0;
        $lastText = '';

        foreach ($request->file('files') as $file) {
            $title = $request->title ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
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
        $extension = strtolower($file->getClientOriginalExtension());
        $tmpPath = $file->getPathname();

        if ($extension === 'txt') {
            return file_get_contents($tmpPath);
        }

        if (in_array($extension, ['doc', 'docx'])) {
            $output = shell_exec('extract-text ' . escapeshellarg($tmpPath) . ' 2>/dev/null');
            return $output ?: '';
        }

        if ($extension === 'pdf') {
            $output = shell_exec('pdftotext ' . escapeshellarg($tmpPath) . ' - 2>/dev/null');
            return $output ?: '';
        }

        return '';
    }

    public function destroy($id)
    {
        DocumentRag::findOrFail($id)->delete();
        return back()->with('success', 'Documento rimosso.');
    }
}
