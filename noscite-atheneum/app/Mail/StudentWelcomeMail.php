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

    public function __construct(public Student $student, public string $tempPassword, public array $courseNames = [])
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Le tue credenziali per Atheneum Noscite',
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
                'loginUrl' => 'https://atheneum.noscite.it/learn/login',
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
