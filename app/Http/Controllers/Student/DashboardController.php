<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;

class DashboardController extends Controller
{
    public function index()
    {
        $student = Student::with(['courses' => function ($q) {
            $q->where('student_course.is_active', true);
        }])->findOrFail(session('student_id'));

        $courses = $student->courses->map(function ($course) use ($student) {
            $totalModules = $course->modules()->where('is_active', true)->count();
            $completed = $student->moduleProgress()
                ->whereHas('module', fn($q) => $q->where('course_id', $course->id))
                ->where('status', 'completed')
                ->count();

            $course->progress_pct = $totalModules > 0 ? round(($completed / $totalModules) * 100) : 0;
            $course->modules_total = $totalModules;
            $course->modules_done = $completed;
            return $course;
        });

        return view('student.dashboard', compact('student', 'courses'));
    }
}
