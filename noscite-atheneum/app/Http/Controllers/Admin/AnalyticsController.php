<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\StudentModuleProgress;

class AnalyticsController extends Controller
{
    public function index()
    {
        $totalStudents = Student::count();
        $activeStudents = Student::where('is_active', true)->count();
        $studentsLoggedLast30Days = Student::where('last_login_at', '>=', now()->subDays(30))->count();

        $modulesCompleted = StudentModuleProgress::where('status', 'completed')->count();
        $modulesInProgress = StudentModuleProgress::where('status', 'in_progress')->count();

        $quizAttempts = QuizAttempt::whereNotNull('completed_at')->count();
        $quizPassed = QuizAttempt::where('passed', true)->count();
        $avgScore = round((float) QuizAttempt::whereNotNull('score')->avg('score'), 1);

        $courseStats = Course::withCount(['students', 'modules', 'quizzes'])
            ->orderBy('sort_order')
            ->get()
            ->map(function ($c) {
                $completed = StudentModuleProgress::whereHas('module', fn($q) => $q->where('course_id', $c->id))
                    ->where('status', 'completed')->count();
                $c->modules_completed = $completed;
                return $c;
            });

        return view('admin.analytics.index', compact(
            'totalStudents', 'activeStudents', 'studentsLoggedLast30Days',
            'modulesCompleted', 'modulesInProgress',
            'quizAttempts', 'quizPassed', 'avgScore',
            'courseStats'
        ));
    }
}
