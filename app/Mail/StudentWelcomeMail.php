<?php

namespace App\Mail;

use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StudentWelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Student $student,
        public string $tempPassword,
        public array $courseNames = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Le tue credenziali per ' . atheneum_setting('instance_name', 'Officina'),
        );
    }

    public function content(): Content
    {

        return new Content(
            markdown: 'emails.student-welcome',
            with: [
                'student' => $this->student,
                'tempPassword' => $this->tempPassword,
                'courseNames' => $this->courseNames,
                'loginUrl' => route('student.login'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
