<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

class CertificateController extends Controller
{
    public function download(Course $course)
    {
        $cert = $this->resolveCertificate($course);
        return $this->buildPdf($cert, $course)->download($this->filename($cert, $course));
    }

    public function show(Course $course)
    {
        $cert = $this->resolveCertificate($course);
        return $this->buildPdf($cert, $course)->stream('certificato.pdf');
    }

    /**
     * Carica il certificato per (studente loggato, corso). Verifica l'iscrizione
     * attiva al corso e l'esistenza del certificato (gating: solo se ha superato il
     * final quiz). Senza certificato emesso → 403.
     */
    private function resolveCertificate(Course $course): Certificate
    {
        $student = Student::findOrFail(session('student_id'));

        $enrolled = $student->courses()
            ->wherePivot('is_active', true)
            ->where('courses.id', $course->id)
            ->exists();
        abort_unless($enrolled, 403);

        $cert = Certificate::where('student_id', $student->id)
            ->where('course_id', $course->id)
            ->latest('issued_at')
            ->first();

        abort_unless(
            $cert,
            403,
            'Devi superare l\'esame finale prima di scaricare il certificato.'
        );

        return $cert;
    }

    /**
     * Costruisce il PDF dal Certificate. Tutti i campi anagrafici (codice, score,
     * data, nome certificazione) sono letti dallo snapshot del Certificate per
     * sopravvivere a eventuali modifiche/cancellazioni successive del corso.
     * $course resta come arricchimento opzionale per UI (slug nel filename).
     */
    private function buildPdf(Certificate $cert, Course $course)
    {
        $student = $cert->student;

        $verifyUrl = route('certificate.verify', ['code' => $cert->code]);
        $qrDataUri = Builder::create()
            ->writer(new PngWriter())
            ->data($verifyUrl)
            ->size(220)
            ->margin(8)
            ->build()
            ->getDataUri();

        $date = $cert->issued_at->locale('it')->isoFormat('D MMMM YYYY');

        return Pdf::loadView('pdf.certificate', [
            'cert' => $cert,
            'student' => $student,
            'course' => $course,
            'date' => $date,
            'verifyUrl' => $verifyUrl,
            'qrDataUri' => $qrDataUri,
        ])->setPaper('a4', 'landscape');
    }

    private function filename(Certificate $cert, Course $course): string
    {
        $student = $cert->student;
        return 'Certificato-'
            . str_replace(' ', '-', $course->name) . '-'
            . str_replace(' ', '-', $student->name) . '.pdf';
    }
}
