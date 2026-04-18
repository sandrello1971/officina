<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    public function download(Course $course)
    {
        $student = Student::findOrFail(session('student_id'));

        $enrolled = $student->courses()
            ->wherePivot('is_active', true)
            ->where('courses.id', $course->id)
            ->exists();

        if (!$enrolled) abort(403);

        $finalQuiz = Quiz::where('course_id', $course->id)
            ->whereNull('module_id')
            ->where('is_active', true)
            ->first();

        $attempt = null;
        if ($finalQuiz) {
            $attempt = QuizAttempt::where('quiz_id', $finalQuiz->id)
                ->where('student_id', $student->id)
                ->where('passed', true)
                ->orderBy('score', 'desc')
                ->first();
        }

        $code = strtoupper(substr(md5($student->id . $course->id), 0, 12));
        $date = now()->locale('it')->isoFormat('D MMMM YYYY');

        $pdf = Pdf::loadView('pdf.certificate', [
            'student' => $student,
            'course' => $course,
            'score' => $attempt?->score,
            'date' => $date,
            'code' => $code,
        ])->setPaper('a4', 'landscape');

        $filename = 'Certificato-' . str_replace(' ', '-', $course->name) . '-' . str_replace(' ', '-', $student->name) . '.pdf';

        return $pdf->download($filename);
    }

    public function show(Course $course)
    {
        $student = Student::findOrFail(session('student_id'));

        $enrolled = $student->courses()
            ->wherePivot('is_active', true)
            ->where('courses.id', $course->id)
            ->exists();

        if (!$enrolled) abort(403);

        $code = strtoupper(substr(md5($student->id . $course->id), 0, 12));
        $date = now()->locale('it')->isoFormat('D MMMM YYYY');

        $attempt = QuizAttempt::whereHas('quiz', fn($q) => $q->where('course_id', $course->id)->whereNull('module_id'))
            ->where('student_id', $student->id)
            ->where('passed', true)
            ->orderBy('score', 'desc')
            ->first();

        $pdf = Pdf::loadView('pdf.certificate', [
            'student' => $student,
            'course' => $course,
            'score' => $attempt?->score,
            'date' => $date,
            'code' => $code,
        ])->setPaper('a4', 'landscape');

        return $pdf->stream('certificato.pdf');
    }
}
