<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoApiTransport;
use Tests\TestCase;

/**
 * Il mailer 'brevo' (config/mail.php) richiede la registrazione del transport
 * Symfony in AppServiceProvider: senza, Laravel dà "Unsupported mail transport
 * [brevo]". Questo test blinda quella registrazione.
 */
class BrevoMailTransportTest extends TestCase
{
    public function test_brevo_transport_si_risolve(): void
    {
        config([
            'services.brevo.key' => 'xkeysib-test',
            'mail.mailers.brevo' => ['transport' => 'brevo', 'key' => 'xkeysib-test'],
        ]);
        Mail::purge('brevo');

        $transport = Mail::mailer('brevo')->getSymfonyTransport();

        $this->assertInstanceOf(BrevoApiTransport::class, $transport);
    }
}
