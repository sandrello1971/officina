<?php

namespace Tests\Feature;

use App\Mail\AssessmentReportToLead;
use App\Mail\LeadFallbackToSales;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PublicAssessmentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Decouple feature behavior tests from CSRF.
        // ThrottleRequests is intentionally LEFT ACTIVE: each test fires at most one
        // POST submit and the rate-limit cache is reset between tests, so the existing
        // suite stays green while the GET-not-rate-limited tests below remain meaningful.
        $this->withoutMiddleware([
            VerifyCsrfToken::class,
        ]);

        Mail::fake();
        // Prevent real HTTP calls if a test forgets to fake; each HTTP-touching test
        // registers its own pattern-specific Http::fake.
        Http::preventStrayRequests();

        Config::set('services.crm.lead_inbound_url', 'https://crm.test/api/v1/public/leads');
        Config::set('services.crm.lead_inbound_secret', 'test-secret');
        Config::set('services.crm.fallback_email', 'sales-test@noscite.it');
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => 'mario@example.it',
            'phone' => '+39 333 1234567',
            'company_name' => 'Test Srl',
            'role' => 'CEO',
            'gdpr_consent' => '1',
            'scores' => [
                'tools' => 2,
                'governance' => 1,
                'skills' => 2,
                'processes' => 2,
                'compliance' => 1,
            ],
        ], $overrides);
    }

    public function test_show_page_renders(): void
    {
        $this->get(route('assessment.show'))
            ->assertOk()
            ->assertSee('Mappa di Maturità', false);
    }

    public function test_submit_with_valid_data_sends_emails_and_calls_crm(): void
    {
        Http::fake([
            'https://crm.test/*' => Http::response(['lead_id' => 'fake-uuid', 'status' => 'new'], 201),
        ]);

        $this->post(route('assessment.submit'), $this->validPayload())
            ->assertRedirect();

        Mail::assertSent(AssessmentReportToLead::class);
        Mail::assertNotSent(LeadFallbackToSales::class);
        Http::assertSentCount(1);
    }

    public function test_submit_without_gdpr_consent_fails(): void
    {
        $this->post(route('assessment.submit'), $this->validPayload(['gdpr_consent' => null]))
            ->assertSessionHasErrors('gdpr_consent');
    }

    public function test_honeypot_blocks_bots(): void
    {
        $this->post(route('assessment.submit'), $this->validPayload(['website' => 'http://bot.com']))
            ->assertSessionHasErrors('website');
    }

    public function test_fallback_email_when_crm_fails(): void
    {
        Http::fake([
            'https://crm.test/*' => Http::response(['error' => 'simulated'], 500),
        ]);

        $this->post(route('assessment.submit'), $this->validPayload())
            ->assertRedirect();

        Mail::assertSent(AssessmentReportToLead::class);
        Mail::assertSent(LeadFallbackToSales::class);
    }

    public function test_invalid_score_range_rejected(): void
    {
        $payload = $this->validPayload();
        $payload['scores']['tools'] = 5;

        $this->post(route('assessment.submit'), $payload)
            ->assertSessionHasErrors('scores.tools');
    }

    public function test_invalid_email_rejected(): void
    {
        $this->post(route('assessment.submit'), $this->validPayload(['email' => 'not-an-email']))
            ->assertSessionHasErrors('email');
    }

    public function test_inferred_course_consilium_for_mid_score(): void
    {
        Http::fake([
            'https://crm.test/*' => Http::response(['lead_id' => 'x'], 201),
        ]);

        $this->post(route('assessment.submit'), $this->validPayload([
            'scores' => [
                'tools' => 3,
                'governance' => 2,
                'skills' => 3,
                'processes' => 2,
                'compliance' => 2,
            ],
        ]))->assertRedirect();

        Http::assertSent(function ($req) {
            return $req['recommended_course'] === 'CONSILIUM';
        });
    }

    public function test_get_show_is_not_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get(route('assessment.show'))->assertOk();
        }
    }

    public function test_get_thanks_is_not_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->get(route('assessment.thanks', ['lead' => 'test']))->assertOk();
        }
    }
}
