<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\Student;
use App\Models\StudentModuleProgress;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function show(Course $course)
    {
        $this->ensureEnrolled($course);

        $student = Student::findOrFail(session('student_id'));
        $modules = $course->modules()->where('is_active', true)->with('materials')->get();

        $progressByModule = $student->moduleProgress()
            ->whereIn('module_id', $modules->pluck('id'))
            ->get()
            ->keyBy('module_id');

        return view('student.course.show', compact('course', 'modules', 'progressByModule'));
    }

    public function module(Course $course, Module $module)
    {
        $this->ensureEnrolled($course);
        abort_unless($module->course_id === $course->id, 404);

        $student = Student::findOrFail(session('student_id'));

        $progress = StudentModuleProgress::firstOrCreate(
            ['student_id' => $student->id, 'module_id' => $module->id],
            ['status' => 'in_progress', 'started_at' => now()]
        );

        if ($progress->status === 'not_started') {
            $progress->update(['status' => 'in_progress', 'started_at' => now()]);
        }

        $module->load('materials', 'quizzes');

        $materials = $module->materials;
        $quiz = $module->quizzes->first();
        $canvases = is_array($module->metadata ?? null) ? ($module->metadata['canvases'] ?? []) : [];

        $orderedModules = $course->modules()->where('is_active', true)->get();
        $currentIndex = $orderedModules->search(fn($m) => $m->id === $module->id);
        $prevModule = $currentIndex > 0 ? $orderedModules[$currentIndex - 1] : null;
        $nextModule = $currentIndex !== false && $currentIndex < $orderedModules->count() - 1 ? $orderedModules[$currentIndex + 1] : null;

        return view('student.course.module', compact('course', 'module', 'progress', 'materials', 'quiz', 'canvases', 'prevModule', 'nextModule'));
    }

    public function completeModule(Course $course, Module $module)
    {
        $this->ensureEnrolled($course);
        abort_unless($module->course_id === $course->id, 404);

        $student = Student::findOrFail(session('student_id'));

        StudentModuleProgress::updateOrCreate(
            ['student_id' => $student->id, 'module_id' => $module->id],
            ['status' => 'completed', 'completed_at' => now()]
        );

        return back()->with('success', 'Modulo completato!');
    }

    public function canvas(Course $course, Module $module, string $canvas)
    {
        $this->ensureEnrolled($course);
        abort_unless($module->course_id === $course->id, 404);

        return view('student.course.canvas', compact('course', 'module', 'canvas'));
    }

    private function ensureEnrolled(Course $course): void
    {
        $student = Student::findOrFail(session('student_id'));
        $enrolled = $student->courses()
            ->where('courses.id', $course->id)
            ->wherePivot('is_active', true)
            ->exists();

        abort_unless($enrolled, 403, 'Non sei iscritto a questo corso.');
    }
}
