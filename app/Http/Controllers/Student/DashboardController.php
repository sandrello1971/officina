<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\StudentModuleProgress;
use App\Support\StudentCourseAccess;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function __construct(private StudentCourseAccess $courseAccess)
    {
    }

    /**
     * Dashboard = panoramica: statistiche, "riprendi", classi, anteprima corsi.
     * L'elenco completo dei corsi vive in courses() (/learn/corsi).
     */
    public function index()
    {
        $student = Student::findOrFail(session('student_id'));

        $myClasses = $this->myClasses($student);
        $courses = $this->buildCourses($student);

        // Statistiche aggregate SOLO sulle iscrizioni (un formatore non "completa"
        // i corsi che insegna).
        $enrolledCourses = $courses->where('is_teaching', false);

        $stats = [
            'courses'           => $courses->count(),
            'modules_completed' => StudentModuleProgress::where('student_id', $student->id)
                ->where('status', 'completed')->count(),
            'quizzes_passed'    => QuizAttempt::where('student_id', $student->id)
                ->where('passed', true)->count(),
            'overall_progress'  => (int) round($enrolledCourses->avg('progress_pct') ?? 0),
        ];

        $lastModule = $this->lastModule($student);

        // Anteprima: i corsi non completati (in corso o da iniziare), i più avanti
        // prima; fallback ai primi corsi se tutti completati. Massimo 4.
        $coursePreview = $courses
            ->where('is_teaching', false)
            ->filter(fn ($c) => $c->progress_pct < 100)
            ->sortByDesc('progress_pct')
            ->take(4)
            ->values();
        if ($coursePreview->isEmpty()) {
            $coursePreview = $courses->take(4)->values();
        }

        return view('student.dashboard', compact(
            'student', 'stats', 'lastModule', 'myClasses', 'coursePreview', 'courses'
        ));
    }

    /**
     * Elenco completo dei corsi navigabili, raggruppati per categoria.
     */
    public function courses()
    {
        $student = Student::findOrFail(session('student_id'));

        $courses = $this->buildCourses($student);
        $coursesByCategory = $courses->groupBy(fn ($c) => $c->category?->name ?? 'Altri corsi');
        $myClasses = $this->myClasses($student);

        return view('student.courses', compact('student', 'courses', 'coursesByCategory', 'myClasses'));
    }

    /**
     * Corsi navigabili dello studente arricchiti con progressi e flag docenza.
     */
    private function buildCourses(Student $student): Collection
    {
        return $this->courseAccess->navigableCourses($student)
            ->loadMissing('modules', 'category')
            ->map(function ($course) use ($student) {
                $totalModules = $course->modules->count();
                $course->modules_total = $totalModules;
                $course->is_teaching = ($course->access_kind ?? 'enrolled') === 'teaching';

                if ($course->is_teaching) {
                    $course->progress_pct = null;
                    $course->modules_done = null;
                    return $course;
                }

                $completedModules = StudentModuleProgress::where('student_id', $student->id)
                    ->whereIn('module_id', $course->modules->pluck('id'))
                    ->where('status', 'completed')
                    ->count();
                $course->progress_pct = $totalModules > 0 ? (int) round(($completedModules / $totalModules) * 100) : 0;
                $course->modules_done = $completedModules;
                return $course;
            });
    }

    private function myClasses(Student $student): Collection
    {
        return $student->schoolClasses()
            ->with('subject')
            ->wherePivot('status', '!=', 'removed')
            ->orderBy('name')
            ->get();
    }

    private function lastModule(Student $student): ?array
    {
        $lastProgress = StudentModuleProgress::where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->with('module.course')
            ->latest('updated_at')
            ->first();

        if ($lastProgress && $lastProgress->module && $lastProgress->module->course) {
            return [
                'module_id'   => $lastProgress->module_id,
                'title'       => $lastProgress->module->title,
                'course_name' => $lastProgress->module->course->name,
                'course_slug' => $lastProgress->module->course->slug,
            ];
        }

        return null;
    }
}
