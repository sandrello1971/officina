<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\DeterminesTeachingMode;
use App\Models\Course;
use App\Models\Material;
use App\Models\Student;
use App\Services\InstructorManualSplitterService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;
use Symfony\Component\Process\Process;

class InstructorMaterialController extends Controller
{
    use DeterminesTeachingMode;

    public function show(Course $course, Material $material, InstructorManualSplitterService $splitter)
    {
        $this->authorizeAccess($course, $material);

        // Materiali riservati caricati come file HTML (es. chiavi dei laboratori):
        // nessun content_html né sezioni, si mostra il file così com'è.
        if (empty($material->content_html) && $material->file_path
            && str_ends_with(strtolower($material->file_path), '.html')
            && Storage::disk('local')->exists($material->file_path)) {
            $material->content_html = Storage::disk('local')->get($material->file_path);
        } else {
            $material->content_html = $splitter->injectAnchorsIntoMainHtml($material);
        }

        $student = Student::findOrFail(session('student_id'));

        $sectionsWithNotes = [];
        if ($student->isInstructor()) {
            $counts = \App\Models\InstructorNote::visibleTo($student->id)
                ->where('course_id', $course->id)
                ->whereNotNull('instructor_manual_section_id')
                ->selectRaw('instructor_manual_section_id, COUNT(*) as n')
                ->groupBy('instructor_manual_section_id')
                ->get();
            foreach ($counts as $c) {
                $sectionsWithNotes[$c->instructor_manual_section_id] = $c->n;
            }
        }

        return view('student.instructor.material', [
            'course' => $course,
            'material' => $material,
            'sectionsWithNotes' => $sectionsWithNotes,
        ]);
    }

    public function download(Course $course, Material $material)
    {
        $this->authorizeAccess($course, $material);

        if (!$material->file_path || !Storage::disk('local')->exists($material->file_path)) {
            // Il file importato può mancare (manuali caricati prima che il file
            // venisse conservato): il contenuto però è salvato in content_html,
            // ed è quello che il formatore legge a schermo. Si scarica quello.
            if (trim(strip_tags((string) $material->content_html)) === '') {
                abort(404, 'File non trovato');
            }

            return $this->downloadFromContent($material);
        }

        return response()->download(
            Storage::disk('local')->path($material->file_path),
            ($material->title ?? 'manuale') . '.' . (pathinfo($material->file_path, PATHINFO_EXTENSION) ?: 'docx')
        );
    }

    /**
     * .docx generato con pandoc dal contenuto salvato; se pandoc non c'è o
     * fallisce, lo stesso contenuto come pagina .html autonoma.
     */
    private function downloadFromContent(Material $material)
    {
        $title = $material->title ?: 'Manuale formatore';
        $html = '<!DOCTYPE html><html lang="it"><head><meta charset="utf-8"><title>' . e($title) . '</title></head><body>'
            . $material->content_html
            . (copyright_notice() !== '' ? '<p><small>' . e(copyright_notice()) . '</small></p>' : '')
            . '</body></html>';

        $dir = sys_get_temp_dir() . '/manuale-' . Str::uuid();
        @mkdir($dir, 0700, true);
        file_put_contents("{$dir}/manuale.html", $html);

        try {
            $process = new Process(['pandoc', "{$dir}/manuale.html", '--from=html', '--to=docx', '-o', "{$dir}/manuale.docx"]);
            $process->setTimeout(120);
            $process->run();
            @unlink("{$dir}/manuale.html");

            if ($process->isSuccessful() && is_file("{$dir}/manuale.docx")) {
                return response()->download("{$dir}/manuale.docx", "{$title}.docx")->deleteFileAfterSend();
            }

            Log::warning('[manuale formatore] pandoc non ha prodotto il .docx: si scarica l\'HTML', [
                'material_id' => $material->id, 'error' => trim($process->getErrorOutput()),
            ]);
        } catch (ProcessException $e) {
            Log::warning('[manuale formatore] pandoc non disponibile: si scarica l\'HTML', [
                'material_id' => $material->id, 'error' => $e->getMessage(),
            ]);
        }

        @unlink("{$dir}/manuale.html");
        @unlink("{$dir}/manuale.docx");
        @rmdir($dir);

        return response()->streamDownload(fn () => print($html), "{$title}.html", ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function authorizeAccess(Course $course, Material $material): void
    {
        $student = Student::findOrFail(session('student_id'));

        if (!$student->isInstructor()) {
            abort(403, 'Accesso riservato ai docenti.');
        }

        if (!$this->accessesInstructorMaterials($student, $course)) {
            abort(403, 'Non insegni questo corso.');
        }

        if (!$material->is_instructor_only) {
            abort(404);
        }

        if ($material->course_id !== $course->id) {
            abort(404);
        }
    }
}
