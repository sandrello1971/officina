<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\StudentModuleProgress;

class DashboardController extends Controller
{
    public function index()
    {
        $student = Student::findOrFail(session('student_id'));

        $courses = $student->courses()
            ->wherePivot('is_active', true)
            ->with('modules')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($course) use ($student) {
                $totalModules = $course->modules->count();
                $completedModules = StudentModuleProgress::where('student_id', $student->id)
                    ->whereIn('module_id', $course->modules->pluck('id'))
                    ->where('status', 'completed')
                    ->count();
                $course->progress_pct = $totalModules > 0 ? round(($completedModules / $totalModules) * 100) : 0;
                $course->modules_done = $completedModules;
                $course->modules_total = $totalModules;
                return $course;
            });

        $modulesCompleted = StudentModuleProgress::where('student_id', $student->id)
            ->where('status', 'completed')
            ->count();
        $quizzesPassed = QuizAttempt::where('student_id', $student->id)
            ->where('passed', true)
            ->count();
        $overallProgress = $courses->avg('progress_pct') ?? 0;

        $stats = [
            'courses' => $courses->count(),
            'modules_completed' => $modulesCompleted,
            'quizzes_passed' => $quizzesPassed,
            'overall_progress' => round($overallProgress),
        ];

        $lastProgress = StudentModuleProgress::where('student_id', $student->id)
            ->where('status', 'in_progress')
            ->with('module.course')
            ->latest('updated_at')
            ->first();

        $lastModule = null;
        if ($lastProgress && $lastProgress->module && $lastProgress->module->course) {
            $lastModule = [
                'module_id' => $lastProgress->module_id,
                'title' => $lastProgress->module->title,
                'course_slug' => $lastProgress->module->course->slug,
            ];
        }

        return view('student.dashboard', compact('student', 'courses', 'stats', 'lastModule'));
    }
}
