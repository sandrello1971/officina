<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\Services\CourseIngestionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CourseIngestController extends Controller
{
    public function __construct(private CourseIngestionService $ingest) {}

    public function form()
    {
        return view('admin.courses.ingest.form');
    }

    public function parse(Request $request)
    {
        $request->validate([
            'manual_file' => 'required|file|mimes:pdf,docx,doc,txt|max:51200',
            'exam_file' => 'nullable|file|mimes:pdf,docx,doc,txt|max:20480',
            'color' => 'nullable|string|max:20',
            'icon' => 'nullable|string|max:20',
            'certification_name' => 'nullable|string|max:255',
            'duration_hours' => 'nullable|integer',
        ]);

        set_time_limit(0);
        ini_set('memory_limit', '512M');

        try {
            $manualText = $this->ingest->extractText($request->file('manual_file'));
            if (empty(trim($manualText))) {
                return back()->withInput()->with('error', 'Impossibile estrarre testo dal manuale. Verifica il file.');
            }

            $manualData = $this->ingest->parseManualToModules($manualText);

            $examData = null;
            if ($request->hasFile('exam_file')) {
                $examText = $this->ingest->extractText($request->file('exam_file'));
                if (!empty(trim($examText))) {
                    $examData = $this->ingest->parseExamToQuestions($examText);
                }
            }

            $session = [
                'manual' => $manualData,
                'exam' => $examData,
                'settings' => [
                    'color' => $request->input('color', '#55B1AE'),
                    'icon' => $request->input('icon', '✦'),
                    'certification_name' => $request->input('certification_name'),
                    'duration_hours' => $request->input('duration_hours'),
                ],
            ];
            session(['course_ingest' => $session]);

            return redirect()->route('admin.courses.ingest.preview');
        } catch (\Exception $e) {
            Log::error('Course ingest parse failed: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Parsing fallito: ' . $e->getMessage());
        }
    }

    public function preview()
    {
        $data = session('course_ingest');
        if (!$data) {
            return redirect()->route('admin.courses.ingest.form')->with('error', 'Nessun dato in sessione. Ricarica i documenti.');
        }

        return view('admin.courses.ingest.preview', ['data' => $data]);
    }

    public function confirm(Request $request)
    {
        $data = session('course_ingest');
        if (!$data) {
            return redirect()->route('admin.courses.ingest.form')->with('error', 'Sessione scaduta. Ricarica.');
        }

        $input = $request->validate([
            'course_name' => 'required|string|max:255',
            'course_slug' => 'nullable|string|max:255',
            'course_description' => 'nullable|string',
            'course_short_description' => 'nullable|string|max:255',
            'modules' => 'required|array|min:1',
            'modules.*.title' => 'required|string|max:255',
            'modules.*.description' => 'nullable|string',
            'modules.*.content_html' => 'nullable|string',
            'modules.*.include' => 'nullable|string',
            'quiz_title' => 'nullable|string|max:255',
            'quiz_passing_score' => 'nullable|integer|min:0|max:100',
            'questions' => 'nullable|array',
            'questions.*.question' => 'nullable|string',
            'questions.*.options' => 'nullable|array',
            'questions.*.correct_answer' => 'nullable|string',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.include' => 'nullable|string',
        ]);

        $settings = $data['settings'] ?? [];

        try {
            $courseId = DB::transaction(function () use ($input, $settings) {
                $course = Course::create([
                    'name' => $input['course_name'],
                    'slug' => $input['course_slug'] ?: Str::slug($input['course_name']),
                    'description' => $input['course_description'] ?? null,
                    'short_description' => $input['course_short_description'] ?? null,
                    'color' => $settings['color'] ?? '#55B1AE',
                    'icon' => $settings['icon'] ?? '✦',
                    'certification_name' => $settings['certification_name'] ?? null,
                    'duration_hours' => $settings['duration_hours'] ?? null,
                    'is_active' => true,
                    'sort_order' => (Course::max('sort_order') ?? 0) + 1,
                ]);

                $sort = 0;
                foreach ($input['modules'] as $m) {
                    if (empty($m['include'])) continue;
                    Module::create([
                        'course_id' => $course->id,
                        'title' => $m['title'],
                        'description' => $m['description'] ?? null,
                        'content' => $m['content_html'] ?? null,
                        'is_active' => true,
                        'sort_order' => $sort++,
                    ]);
                }

                if (!empty($input['questions'])) {
                    $included = array_values(array_filter($input['questions'], fn($q) => !empty($q['include'])));
                    if (!empty($included)) {
                        $quiz = Quiz::create([
                            'course_id' => $course->id,
                            'module_id' => null,
                            'title' => $input['quiz_title'] ?? ('Esame finale — ' . $course->name),
                            'description' => 'Esame finale del corso',
                            'passing_score' => $input['quiz_passing_score'] ?? 70,
                            'time_limit_minutes' => null,
                            'max_attempts' => 3,
                            'randomize_questions' => true,
                            'show_results_immediately' => true,
                            'is_active' => true,
                        ]);

                        $qSort = 0;
                        foreach ($included as $q) {
                            $options = array_values(array_filter($q['options'] ?? [], fn($o) => is_string($o) && trim($o) !== ''));
                            if (count($options) !== 4) continue;
                            QuizQuestion::create([
                                'quiz_id' => $quiz->id,
                                'question' => $q['question'],
                                'type' => 'multiple_choice',
                                'options' => $options,
                                'correct_answer' => $q['correct_answer'] ?? $options[0],
                                'explanation' => $q['explanation'] ?? '',
                                'points' => 1,
                                'sort_order' => $qSort++,
                            ]);
                        }
                    }
                }

                return $course->id;
            });

            session()->forget('course_ingest');
            return redirect("/admin/courses/{$courseId}/edit")->with('success', 'Corso creato dai documenti!');
        } catch (\Exception $e) {
            Log::error('Course ingest confirm failed: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Creazione fallita: ' . $e->getMessage());
        }
    }

    public function cancel()
    {
        session()->forget('course_ingest');
        return redirect()->route('admin.courses.index')->with('success', 'Ingestione annullata.');
    }
}
