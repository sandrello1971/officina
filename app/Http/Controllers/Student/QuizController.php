<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use Illuminate\Http\Request;

class QuizController extends Controller
{
    public function show(Quiz $quiz)
    {
        $studentId = session('student_id');
        abort_unless($studentId, 403);

        $quiz->load('questions');

        $previousAttempts = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $studentId)
            ->orderByDesc('created_at')
            ->get();

        return view('student.quiz.show', compact('quiz', 'previousAttempts'));
    }

    public function start(Quiz $quiz)
    {
        $studentId = session('student_id');
        abort_unless($studentId, 403);

        $nextAttempt = QuizAttempt::where('quiz_id', $quiz->id)
            ->where('student_id', $studentId)
            ->max('attempt_number') ?? 0;

        if ($quiz->max_attempts && $nextAttempt >= $quiz->max_attempts) {
            return back()->withErrors(['quiz' => 'Hai esaurito i tentativi disponibili.']);
        }

        $attempt = QuizAttempt::create([
            'quiz_id' => $quiz->id,
            'student_id' => $studentId,
            'started_at' => now(),
            'attempt_number' => $nextAttempt + 1,
        ]);

        $questions = $quiz->questions;
        if ($quiz->randomize_questions) {
            $questions = $questions->shuffle();
        }

        return view('student.quiz.attempt', compact('quiz', 'attempt', 'questions'));
    }

    public function submit(Request $request, Quiz $quiz)
    {
        $studentId = session('student_id');
        abort_unless($studentId, 403);

        $attempt = QuizAttempt::where('id', $request->attempt_id)
            ->where('student_id', $studentId)
            ->firstOrFail();

        $answers = $request->input('answers', []);
        $totalPoints = 0;
        $earnedPoints = 0;

        foreach ($quiz->questions as $question) {
            $userAnswer = $answers[$question->id] ?? null;
            $isCorrect = $this->checkAnswer($question, $userAnswer);
            $points = $isCorrect ? $question->points : 0;

            $totalPoints += $question->points;
            $earnedPoints += $points;

            QuizAnswer::create([
                'attempt_id' => $attempt->id,
                'question_id' => $question->id,
                'answer' => is_array($userAnswer) ? json_encode($userAnswer) : $userAnswer,
                'is_correct' => $isCorrect,
                'points_earned' => $points,
            ]);
        }

        $score = $totalPoints > 0 ? round(($earnedPoints / $totalPoints) * 100) : 0;

        $attempt->update([
            'completed_at' => now(),
            'score' => $score,
            'passed' => $score >= $quiz->passing_score,
            'time_spent_seconds' => now()->diffInSeconds($attempt->started_at),
        ]);

        return redirect()->route('student.quiz.result', [$quiz, $attempt]);
    }

    public function result(Quiz $quiz, QuizAttempt $attempt)
    {
        $studentId = session('student_id');
        abort_unless($studentId && $attempt->student_id === $studentId, 403);

        $attempt->load('answers.question');

        return view('student.quiz.result', compact('quiz', 'attempt'));
    }

    private function checkAnswer($question, $userAnswer): bool
    {
        if (empty($userAnswer)) {
            return false;
        }

        if ($question->type === 'open') {
            return false;
        }

        $correct = trim((string) $question->correct_answer);
        $given = is_array($userAnswer) ? implode(',', $userAnswer) : trim((string) $userAnswer);

        return strcasecmp($correct, $given) === 0;
    }
}
