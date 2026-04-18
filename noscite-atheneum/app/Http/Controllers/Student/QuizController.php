<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\Student;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function show(Quiz $quiz)
    {
        $student = Student::findOrFail(session('student_id'));

        $questions = $quiz->questions()->orderBy('sort_order')->get();

        $pastAttempts = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $student->id)
            ->whereNotNull('completed_at')
            ->orderBy('created_at', 'desc')
            ->get();

        $course = $quiz->course ?? $quiz->module?->course;

        $nextModule = null;
        if ($quiz->module_id && $quiz->module) {
            $nextModule = Module::where('course_id', $quiz->module->course_id)
                ->where('sort_order', '>', $quiz->module->sort_order)
                ->orderBy('sort_order')
                ->first();
        }

        return view('student.quiz.show', compact('quiz', 'questions', 'pastAttempts', 'course', 'nextModule'));
    }

    public function start(Request $request, Quiz $quiz)
    {
        $student = Student::findOrFail(session('student_id'));

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $student->id,
            'started_at' => now(),
            'attempt_number' => QuizAttempt::where('quiz_id', $quiz->id)
                ->where('student_id', $student->id)
                ->count() + 1,
        ]);

        return response()->json(['attempt_id' => $attempt->id]);
    }

    public function submit(Request $request, Quiz $quiz)
    {
        $attempt = QuizAttempt::find($request->attempt_id);
        if ($attempt) {
            $attempt->update([
                'completed_at' => now(),
                'score' => $request->score,
                'passed' => $request->passed,
                'time_spent_seconds' => now()->diffInSeconds($attempt->started_at),
            ]);
        }
        return response()->json(['success' => true]);
    }

    public function result(Quiz $quiz, QuizAttempt $attempt)
    {
        $studentId = session('student_id');
        abort_unless($studentId && $attempt->student_id === $studentId, 403);

        $attempt->load('answers.question');

        return view('student.quiz.result', compact('quiz', 'attempt'));
    }
}
