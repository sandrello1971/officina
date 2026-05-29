<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LeadFallbackToSales extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public array $leadData,
        public string $errorReason,
    ) {}

    public function envelope(): Envelope
    {
        $name = $this->leadData['company_name'] ?? 'N/D';

        return new Envelope(
            subject: "[FALLBACK CRM KO] Nuovo lead Canvas — {$name} — INSERIRE MANUALMENTE",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.lead-fallback-to-sales',
            with: [
                'lead' => $this->leadData,
                'errorReason' => $this->errorReason,
            ],
        );
    }
}
