<?php

namespace Tests\Feature\Tenancy;

use App\Models\AiUsage;
use App\Models\Tenant;
use App\Services\Ai\AiUnavailableException;
use App\Services\Ai\ClaudeClient;
use App\Support\TenantConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TenantAiUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'ok']],
            'usage' => ['input_tokens' => 1000, 'output_tokens' => 500],
            'model' => 'claude-sonnet-4-5',
        ], 200)]);

        tenancy()->end();
        config(['services.anthropic.key' => 'platform-key']);
        TenantConfig::rebaseline();
    }

    private function enter(array $attributes, ?string $ownKey = null): void
    {
        $this->tenant->update($attributes);
        tenancy()->initialize($this->tenant->fresh());
        if ($ownKey !== null) {
            atheneum_setting_put('api_key_anthropic_encrypted', Crypt::encryptString($ownKey));
            tenancy()->end();
            tenancy()->initialize($this->tenant->fresh());
        }
    }

    private function ask(): void
    {
        app(ClaudeClient::class)->messages(['messages' => [['role' => 'user', 'content' => 'hi']]], ['feature' => 't']);
    }

    public function test_il_metering_registra_la_chiave_usata(): void
    {
        $this->enter(['ai_key_mode' => Tenant::AI_KEY_BOTH], ownKey: 'ente-key');
        $this->ask();

        $this->assertSame('tenant', AiUsage::where('feature', 't')->latest('created_at')->firstOrFail()->key_source);
        Http::assertSent(fn ($r) => $r->header('x-api-key')[0] === 'ente-key');
    }

    public function test_budget_esaurito_blocca_solo_la_chiave_di_piattaforma(): void
    {
        $this->enter(['ai_key_mode' => Tenant::AI_KEY_PLATFORM, 'ai_monthly_budget_usd' => 0.01]);
        AiUsage::create(['feature' => 'x', 'model' => 'm', 'tokens_in' => 0, 'tokens_out' => 0,
            'cost_usd' => 0.02, 'status' => 'ok', 'key_source' => 'platform', 'created_at' => now()]);

        try {
            $this->ask();
            $this->fail('Attesa AiUnavailableException');
        } catch (AiUnavailableException $e) {
            $this->assertStringContainsString('Budget AI mensile esaurito', $e->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_budget_non_si_applica_alla_chiave_dell_ente(): void
    {
        $this->enter(['ai_key_mode' => Tenant::AI_KEY_TENANT, 'ai_monthly_budget_usd' => 0.01], ownKey: 'ente-key');
        AiUsage::create(['feature' => 'x', 'model' => 'm', 'tokens_in' => 0, 'tokens_out' => 0,
            'cost_usd' => 5, 'status' => 'ok', 'key_source' => 'platform', 'created_at' => now()]);

        $this->ask();
        Http::assertSentCount(1);
    }

    public function test_modalita_ente_senza_chiave_non_ricade_sulla_piattaforma(): void
    {
        $this->enter(['ai_key_mode' => Tenant::AI_KEY_TENANT]);

        $this->expectException(AiUnavailableException::class);
        $this->ask();
    }
}
