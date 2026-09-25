<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\Concerns\DeterminesTeachingMode;
use App\Models\Course;
use App\Models\Student;
use App\Services\AttendanceRegisterPdfBuilder;
use App\Services\AttendanceService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Prospetto ore del corso lato FORMATORE (sincrono + FAD), dentro l'area
 * /learn. Giornate e appelli si gestiscono per edizione (CourseEditionController).
 */
class CourseAttendanceController extends Controller
{
    use DeterminesTeachingMode;

    public function __construct(private AttendanceService $attendance)
    {
    }

    /** Solo il formatore che insegna il corso può accedere. */
    private function guard(Course $course): Student
    {
        $student = Student::findOrFail(session('student_id'));
        abort_unless($this->teaches($student, $course), 403);

        return $student;
    }

    public function register(Course $course)
    {
        $this->guard($course);
        $rows = $this->attendance->courseRegister($course);
        $sessions = $course->sessions()->orderBy('scheduled_at')->get();

        return view('student.course.attendance.register', compact('course', 'rows', 'sessions'));
    }

    public function studentDetail(Course $course, Student $student)
    {
        $this->guard($course);
        $records = $this->attendance->studentCourseDetail($course, $student);
        $row = $this->attendance->courseRegister($course)->firstWhere('student.id', $student->id);

        return view('student.course.attendance.student', compact('course', 'student', 'records', 'row'));
    }

    public function registerPdf(Course $course, AttendanceRegisterPdfBuilder $builder): StreamedResponse
    {
        $this->guard($course);
        $rows = $this->attendance->courseRegister($course);
        $sessions = $course->sessions()->orderBy('scheduled_at')->get();
        $bytes = $builder->buildCourseRegister($course, $rows, $sessions);

        return response()->streamDownload(fn () => print($bytes), 'registro-frequenza-' . $course->slug . '.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }
}
