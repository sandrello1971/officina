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
     * True se il formatore ha diritto ai materiali riservati (manuale
     * formatore, sezioni per modulo): iscritto al corso, abilitato a tutti
     * i corsi (auto_enroll_all_courses) o docente esplicito del corso.
     * La semplice consultazione via browsesAnyCourse() NON basta.
     */
    protected function accessesInstructorMaterials(Student $student, Course $course): bool
    {
        if (!$student->isInstructor()) {
            return false;
        }

        if ($student->auto_enroll_all_courses) {
            return true;
        }

        return $student->courses()
                ->where('courses.id', $course->id)
                ->wherePivot('is_active', true)
                ->exists()
            || $this->teaches($student, $course);
    }

    /**
     * True se il formatore può vedere il lavoro dei discenti del corso
     * (canvas compilati): docente esplicito o formatore di piattaforma
     * (auto_enroll_all_courses). Un formatore iscritto come discente NON
     * vede le schede degli altri iscritti.
     */
    protected function reviewsStudentWork(Student $student, Course $course): bool
    {
        return $student->isInstructor()
            && ($student->auto_enroll_all_courses || $this->teaches($student, $course));
    }

    /**
     * True se il formatore gestisce edizioni, appelli e registro del corso:
     * stessa regola di reviewsStudentWork() (docente esplicito o formatore
     * di piattaforma con auto_enroll_all_courses).
     */
    protected function managesAttendance(Student $student, Course $course): bool
    {
        return $this->reviewsStudentWork($student, $course);
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
