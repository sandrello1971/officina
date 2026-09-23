<?php

namespace App\Http\Controllers\Student\Concerns;

use App\Models\Course;
use App\Models\Student;

trait DeterminesTeachingMode
{
    /**
     * True se il formatore accede al corso SENZA esservi iscritto come
     * discente reale: sia che lo insegni davvero, sia che lo stia solo
     * consultando (accesso esteso a tutti i corsi attivi per i formatori).
     * In questa modalità niente progressi/tentativi quiz vengono salvati.
     */
    protected function isTeachingMode(Student $student, Course $course): bool
    {
        if (!$student->isInstructor()) {
            return false;
        }

        if ($student->auto_enroll_all_courses) {
            return false;
        }

        $enrolledAsStudent = $student->courses()
            ->where('courses.id', $course->id)
            ->wherePivot('is_active', true)
            ->exists();

        return !$enrolledAsStudent;
    }

    /** True se il formatore insegna il corso (a prescindere dall'iscrizione). */
    protected function teaches(Student $student, Course $course): bool
    {
        return $student->isInstructor()
            && $student->taughtCourses()->where('courses.id', $course->id)->exists();
    }

    /**
     * True se il formatore può accedere in sola consultazione a QUALUNQUE
     * corso attivo, anche uno che non insegna (accesso esteso richiesto per
     * il portale learn.*: QA, supporto, verifica contenuti). Non sblocca
     * materiali/privilegi riservati al formatore reale: quelli restano
     * legati a teaches().
     */
    protected function browsesAnyCourse(Student $student, Course $course): bool
    {
        return $student->isInstructor() && $course->is_active;
    }
}
