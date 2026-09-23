<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\DeterminesTeachingMode;
use App\Models\Course;
use App\Models\Material;
use App\Models\Student;
use App\Models\StudentCanvasData;
use App\Services\CanvasDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Schede dei discenti lato FORMATORE: i canvas compilati dagli iscritti al
 * corso, in sola lettura. È la "consegna" dei laboratori.
 */
class CanvasReviewController extends Controller
{
    use DeterminesTeachingMode;

    public function index(Course $course)
    {
        $this->guard($course);

        $canvases = $this->compilableCanvases($course);
        $counts = StudentCanvasData::whereIn('material_id', $canvases->pluck('id'))
            ->whereIn('student_id', $this->enrolledStudentIds($course))
            ->selectRaw('material_id, COUNT(*) as n, MAX(updated_at) as last_at')
            ->groupBy('material_id')
            ->get()
            ->keyBy('material_id');

        return view('student.course.canvas-review.index', [
            'course' => $course,
            'canvases' => $canvases,
            'counts' => $counts,
            'enrolledCount' => count($this->enrolledStudentIds($course)),
        ]);
    }

    public function show(Course $course, Material $material, CanvasDocument $canvas)
    {
        $this->guard($course);
        abort_unless(
            $material->file_type === 'canvas'
                && !$material->is_instructor_only
                && ($material->course_id ?? $material->module?->course_id) === $course->id,
            404
        );

        $html = $material->file_path && Storage::disk('local')->exists($material->file_path)
            ? Storage::disk('local')->get($material->file_path)
            : '';
        $labels = $canvas->fieldLabels($html);

        $rows = StudentCanvasData::where('material_id', $material->id)
            ->whereIn('student_id', $this->enrolledStudentIds($course))
            ->with('student:id,name,email')
            ->orderByDesc('updated_at')
            ->get();

        $submissions = $rows->map(fn (StudentCanvasData $row) => [
            'student' => $row->student,
            'updated_at' => $row->updated_at,
            'fields' => $this->presentFields((array) $row->data, $labels),
        ]);

        return view('student.course.canvas-review.show', [
            'course' => $course,
            'material' => $material,
            'submissions' => $submissions,
            'enrolledCount' => count($this->enrolledStudentIds($course)),
        ]);
    }

    private function guard(Course $course): Student
    {
        $student = Student::findOrFail(session('student_id'));
        abort_unless($this->reviewsStudentWork($student, $course), 403);

        return $student;
    }

    /** Canvas del corso con campi compilabili, nell'ordine dei moduli. */
    private function compilableCanvases(Course $course): Collection
    {
        return Material::query()
            ->where('file_type', 'canvas')
            ->where(fn ($q) => $q->where('is_instructor_only', false)->orWhereNull('is_instructor_only'))
            ->where(fn ($q) => $q->where('materials.course_id', $course->id)
                ->orWhereIn('module_id', $course->modules()->select('id')))
            ->with('module:id,title,sort_order')
            ->get()
            ->filter(fn (Material $m) => $m->file_path
                && Storage::disk('local')->exists($m->file_path)
                && str_contains(Storage::disk('local')->get($m->file_path), 'data-field'))
            ->sortBy([fn ($m) => $m->module?->sort_order ?? PHP_INT_MAX, fn ($m) => $m->sort_order])
            ->values();
    }

    private function enrolledStudentIds(Course $course): array
    {
        return once(fn () => $course->students()
            ->wherePivot('is_active', true)
            ->pluck('students.id')
            ->all());
    }

    /**
     * Campi nell'ordine del canvas, con etichetta. I valori JSON (tabelle a
     * righe dinamiche salvate come stringa) diventano liste di righe.
     *
     * @return array<int, array{label:string, text:?string, rows:?array}>
     */
    private function presentFields(array $data, array $labels): array
    {
        $keys = array_values(array_unique(array_merge(array_keys($labels), array_keys($data))));
        $fields = [];

        foreach ($keys as $key) {
            if (!array_key_exists($key, $data)) {
                continue;
            }
            $value = $data[$key];
            if (is_string($value) && str_ends_with($key, '_json')) {
                $decoded = json_decode($value, true);
                $value = is_array($decoded) ? $decoded : $value;
            }

            $fields[] = [
                'label' => $labels[$key] ?? ucfirst(str_replace(['_json', '_'], ['', ' '], $key)),
                'text' => is_array($value) ? null : (string) $value,
                'rows' => is_array($value) ? $this->normalizeRows($value) : null,
            ];
        }

        return $fields;
    }

    private function normalizeRows(array $value): array
    {
        return array_map(
            fn ($row) => is_array($row)
                ? array_map(fn ($v) => is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE), $row)
                : ['' => (string) (is_scalar($row) ? $row : json_encode($row, JSON_UNESCAPED_UNICODE))],
            array_values($value)
        );
    }
}
