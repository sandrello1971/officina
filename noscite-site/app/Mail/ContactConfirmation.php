<?php

namespace App\Mail;

use App\Models\ContactMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ContactMessage $contact)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Grazie per averci contattato — Noscite',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.contact-confirmation',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
