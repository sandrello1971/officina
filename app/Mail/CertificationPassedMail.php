<?php

namespace App\Mail;

use App\Models\Course;
use App\Models\Student;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CertificationPassedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Student $student,
        public Course $course,
        public int $score
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🎓 Esame finale superato — ' . $this->course->name,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.certification-passed',
        );
    }

    public function attachments(): array
    {
        $code = strtoupper(substr(md5($this->student->id . $this->course->id), 0, 12));
        $date = now()->locale('it')->isoFormat('D MMMM YYYY');

        $pdf = Pdf::loadView('pdf.certificate', [
            'student' => $this->student,
            'course' => $this->course,
            'score' => $this->score,
            'date' => $date,
            'code' => $code,
        ])->setPaper('a4', 'landscape');

        return [
            Attachment::fromData(fn() => $pdf->output(), 'Certificato-' . $this->course->name . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
