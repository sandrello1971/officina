<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AssessmentReportToLead extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $leadData,
        public string $pdfPath,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address('noreply@noscite.it', 'Noscite Srls'),
            replyTo: [new Address(config('services.crm.fallback_email', 'sales@noscite.it'), 'Sales Noscite')],
            subject: 'Il tuo report Maturità AI — Mappa Noscite',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.assessment-report-to-lead',
            with: [
                'lead' => $this->leadData,
            ],
        );
    }

    public function attachments(): array
    {
        $safeName = preg_replace('/[^A-Za-z0-9_-]/', '_', $this->leadData['company_name']);

        return [
            Attachment::fromPath($this->pdfPath)
                ->as('Report_Maturita_AI_' . $safeName . '.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
