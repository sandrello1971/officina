<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Student;
use App\Models\StudentModuleProgress;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function show(Course $course)
    {
        $student = $this->checkAccess($course);
        $modules = $course->modules()->where('is_active', true)->orderBy('sort_order')->get();

        $progress = StudentModuleProgress::where('student_id', $student->id)
            ->whereIn('module_id', $modules->pluck('id'))
            ->get()->keyBy('module_id');

        $modules->each(function ($module) use ($progress) {
            $p = $progress->get($module->id);
            $module->progress_status = $p?->status ?? 'not_started';
        });

        $totalModules = $modules->count();
        $completedModules = $modules->where('progress_status', 'completed')->count();
        $progressPercent = $totalModules > 0 ? round(($completedModules / $totalModules) * 100) : 0;

        $finalQuiz = Quiz::where('course_id', $course->id)
            ->whereNull('module_id')
            ->where('is_active', true)
            ->first();

        $certificationPassed = false;
        if ($finalQuiz) {
            $certificationPassed = QuizAttempt::where('quiz_id', $finalQuiz->id)
                ->where('student_id', $student->id)
                ->where('passed', true)
                ->exists();
        }

        $progressByModule = $progress;

        return view('student.course.show', compact(
            'course', 'modules', 'progressPercent',
            'completedModules', 'totalModules', 'finalQuiz', 'certificationPassed', 'progressByModule'
        ));
    }

    public function module(Course $course, Module $module)
    {
        $student = $this->checkAccess($course);
        abort_unless($module->course_id === $course->id, 404);

        $progress = StudentModuleProgress::firstOrCreate(
            ['student_id' => $student->id, 'module_id' => $module->id],
            ['status' => 'in_progress', 'started_at' => now()]
        );

        if ($progress->status === 'not_started') {
            $progress->update(['status' => 'in_progress', 'started_at' => now()]);
        }

        $materials = $module->materials()->orderBy('sort_order')->get();
        $prevModule = $course->modules()->where('sort_order', '<', $module->sort_order)->orderBy('sort_order', 'desc')->first();
        $nextModule = $course->modules()->where('sort_order', '>', $module->sort_order)->orderBy('sort_order')->first();
        $canvases = is_array($module->metadata ?? null) ? ($module->metadata['canvases'] ?? []) : [];

        $quiz = Quiz::where('module_id', $module->id)->where('is_active', true)->first();

        $finalQuiz = null;
        $isLastModule = !$nextModule;

        if ($isLastModule) {
            $finalQuiz = Quiz::where('course_id', $course->id)
                ->whereNull('module_id')
                ->where('is_active', true)
                ->first();

            $totalModules = $course->modules()->count();
            $completedModules = StudentModuleProgress::where('student_id', $student->id)
                ->whereIn('module_id', $course->modules()->pluck('id'))
                ->where('status', 'completed')
                ->count();

            if ($completedModules < ceil($totalModules * 0.7)) {
                $finalQuiz = null;
            }
        }

        $certificationPassed = false;
        if ($finalQuiz) {
            $certificationPassed = QuizAttempt::where('quiz_id', $finalQuiz->id)
                ->where('student_id', $student->id)
                ->where('passed', true)
                ->exists();
        }

        $isDemo = $student->is_demo;

        $note = \App\Models\StudentNote::where('student_id', $student->id)
            ->where('module_id', $module->id)
            ->first();

        if ($isDemo && $module->content) {
            $lines = explode("\n", $module->content);
            $module->content = implode("\n", array_slice($lines, 0, 20));
            $module->content .= '
            <div style="margin-top:24px; padding:20px; background:linear-gradient(135deg,#1A1F1F,#3A8C89); border-radius:12px; text-align:center;">
                <div style="color:#55B1AE; font-weight:700; margin-bottom:8px;">✦ Stai usando la versione Demo</div>
                <p style="color:#8A9696; font-size:0.875rem; margin-bottom:16px;">
                    Visualizzi solo un\'anteprima del contenuto.<br>
                    Acquista il corso per accedere a tutti i materiali.
                </p>
                <a href="https://atheneum.noscite.it/contatti"
                   style="padding:10px 24px; background:#E28A53; color:white; border-radius:8px; font-size:0.875rem; font-weight:700; text-decoration:none;">
                    Acquista il corso completo →
                </a>
            </div>';
        }

        return view('student.course.module', compact(
            'course', 'module', 'materials', 'quiz', 'finalQuiz',
            'certificationPassed', 'progress', 'prevModule', 'nextModule',
            'canvases', 'isDemo', 'note'
        ));
    }

    public function completeModule(Course $course, Module $module)
    {
        $student = $this->checkAccess($course);
        abort_unless($module->course_id === $course->id, 404);

        StudentModuleProgress::updateOrCreate(
            ['student_id' => $student->id, 'module_id' => $module->id],
            ['status' => 'completed', 'completed_at' => now()]
        );

        return back()->with('success', 'Modulo completato!');
    }

    public function canvas(Course $course, Module $module, string $canvas)
    {
        $this->checkAccess($course);
        abort_unless($module->course_id === $course->id, 404);

        return view('student.course.canvas', compact('course', 'module', 'canvas'));
    }

    private function checkAccess(Course $course): Student
    {
        $student = Student::findOrFail(session('student_id'));
        $enrolled = $student->courses()
            ->where('courses.id', $course->id)
            ->wherePivot('is_active', true)
            ->exists();

        abort_unless($enrolled, 403, 'Non sei iscritto a questo corso.');

        return $student;
    }
}
