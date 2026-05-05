<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Mail\CertificationPassedMail;
use App\Models\Module;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class QuizController extends Controller
{
    public function show(Quiz $quiz)
    {
        $student = Student::findOrFail(session('student_id'));

        if ($student->is_demo && !$quiz->is_demo) {
            abort(403, 'In modalità demo puoi vedere solo il quiz di prova.');
        }
        if (!$student->is_demo && $quiz->is_demo) {
            abort(404);
        }

        $questions = $quiz->questions()->orderBy('sort_order')->get();
        if ($quiz->randomize_questions) {
            $questions = $questions->shuffle()->values();
        }

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

        if ($student->is_demo) {
            return response()->json(['attempt_id' => 'demo-' . uniqid()]);
        }

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
        $studentId = session('student_id');
        abort_unless($studentId, 403);

        $student = Student::findOrFail($studentId);

        // Branch demo: nessuna persistenza, calcoliamo lo score lato server dalle
        // domande del DB per restituire le corrections al client. start() per gli
        // studenti demo restituisce 'demo-XXX' invece di un UUID valido, quindi la
        // validazione `attempt_id|exists` non si applica qui.
        if ($student->is_demo) {
            $data = Validator::make($request->all(), [
                'answers' => 'present|array',
                'answers.*' => 'nullable|string|max:1000',
            ])->validate();

            return response()->json(array_merge(
                ['success' => true, 'demo' => true],
                $this->scorePayload($quiz, $data['answers'])
            ));
        }

        $data = Validator::make($request->all(), [
            'attempt_id' => 'required|uuid|exists:quiz_attempts,id',
            'answers' => 'present|array',
            'answers.*' => 'nullable|string|max:1000',
        ])->validate();

        $attempt = QuizAttempt::where('id', $data['attempt_id'])
            ->where('student_id', $studentId)
            ->where('quiz_id', $quiz->id)
            ->firstOrFail();

        // Anti-replay: 409 esplicito (non 404) per dare al frontend un segnale
        // utile a disabilitare il bottone in caso di doppio submit accidentale.
        abort_if($attempt->completed_at !== null, 409, 'Tentativo già consegnato.');

        $questions = $quiz->questions()->get()->keyBy('id');
        $totalPoints = $questions->sum('points') ?: $questions->count();
        $earnedPoints = 0;

        DB::transaction(function () use ($attempt, $questions, $data, &$earnedPoints) {
            QuizAnswer::where('attempt_id', $attempt->id)->delete();

            // Iteriamo sulle domande del quiz (autorevole), non sulle chiavi del payload:
            // question_id ignote nel request vengono silenziosamente ignorate, e le
            // domande non risposte risultano answer=null, is_correct=false, points=0.
            foreach ($questions as $qid => $question) {
                $given = $data['answers'][$qid] ?? null;
                $isCorrect = $given !== null && $given === $question->correct_answer;
                $points = $isCorrect ? ($question->points ?? 1) : 0;
                $earnedPoints += $points;

                QuizAnswer::create([
                    'attempt_id' => $attempt->id,
                    'question_id' => $qid,
                    'answer' => $given,
                    'is_correct' => $isCorrect,
                    'points_earned' => $points,
                ]);
            }
        });

        $score = $totalPoints > 0 ? (int) round(($earnedPoints / $totalPoints) * 100) : 0;
        $passed = $score >= $quiz->passing_score;

        $timeSpent = (int) max(0, $attempt->started_at->diffInSeconds(now()));

        // Time-limit superato: accettiamo comunque il submit ma logghiamo per audit.
        if ($quiz->time_limit_minutes && $timeSpent > $quiz->time_limit_minutes * 60) {
            Log::warning('Quiz submit oltre time_limit', [
                'attempt_id' => $attempt->id,
                'student_id' => $studentId,
                'time_spent_seconds' => $timeSpent,
                'time_limit_seconds' => $quiz->time_limit_minutes * 60,
            ]);
        }

        $attempt->update([
            'completed_at' => now(),
            'score' => $score,
            'passed' => $passed,
            'time_spent_seconds' => $timeSpent,
        ]);

        if ($passed && $quiz->course_id && !$quiz->module_id) {
            $course = $quiz->course;
            if ($course) {
                try {
                    Mail::to($student->email)
                        ->queue(new CertificationPassedMail($student, $course, $score));
                } catch (\Throwable $e) {
                    Log::error('Email certificato fallita: ' . $e->getMessage());
                }
            }
        }

        return response()->json(array_merge(
            ['success' => true],
            $this->scorePayload($quiz, $data['answers'], $questions, $score, $passed)
        ));
    }

    public function result(Quiz $quiz, QuizAttempt $attempt)
    {
        $studentId = session('student_id');
        abort_unless($studentId && $attempt->student_id === $studentId, 403);
        abort_unless($attempt->quiz_id === $quiz->id, 404);

        $attempt->load(['answers.question']);

        return view('student.quiz.result', compact('quiz', 'attempt'));
    }

    /**
     * Costruisce il payload (score, passed, corrections) restituito al client dopo
     * il submit. corrections è una mappa { qid: {correct_answer, explanation} }
     * per consentire lookup O(1) lato view.
     */
    private function scorePayload(
        Quiz $quiz,
        array $answers,
        $questions = null,
        ?int $score = null,
        ?bool $passed = null
    ): array {
        $questions = $questions ?? $quiz->questions()->get()->keyBy('id');

        if ($score === null) {
            $totalPoints = $questions->sum('points') ?: $questions->count();
            $earned = 0;
            foreach ($questions as $qid => $q) {
                $given = $answers[$qid] ?? null;
                if ($given !== null && $given === $q->correct_answer) {
                    $earned += ($q->points ?? 1);
                }
            }
            $score = $totalPoints > 0 ? (int) round(($earned / $totalPoints) * 100) : 0;
            $passed = $score >= $quiz->passing_score;
        }

        return [
            'score' => $score,
            'passed' => $passed,
            'corrections' => $questions->mapWithKeys(fn($q, $qid) => [
                $qid => [
                    'correct_answer' => $q->correct_answer,
                    'explanation' => $q->explanation,
                ],
            ]),
        ];
    }
}
