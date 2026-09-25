<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Attendance\EditionController;
use App\Http\Controllers\Student\Concerns\DeterminesTeachingMode;
use App\Models\Course;
use App\Models\Student;
use App\Support\EditionRoutes;

/** Edizioni e registro presenze per il formatore che insegna il corso (area learn). */
class CourseEditionController extends EditionController
{
    use DeterminesTeachingMode;

    private ?Student $current = null;

    protected function authorizeCourse(Course $course): void
    {
        $this->current = Student::findOrFail(session('student_id'));
        abort_unless($this->teaches($this->current, $course), 403);
    }

    protected function layout(): string
    {
        return 'layouts.student';
    }

    protected function routes(Course $course): EditionRoutes
    {
        return new EditionRoutes('student.course.editions', $course, bySlug: true);
    }

    protected function actor(): ?string
    {
        return $this->current?->email ?? session('student_email');
    }
}
