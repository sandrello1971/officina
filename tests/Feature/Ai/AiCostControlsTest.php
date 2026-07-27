<?php

namespace Tests\Feature\Ai;

use App\Models\AiUsage;
use App\Services\Ai\ClaudeClient;
use App\Support\WebSearchTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Controlli di costo trasversali: potatura dei sampling parameter non supportati,
 * metering delle ricerche web (fatturate a parte, fuori dai token) e scelta della
 * versione del tool web_search in base al modello.
 */
class AiCostControlsTest extends TestCase
{
    use RefreshDatabase;

    private function ok(array $extraUsage = []): array
    {
        return [
            'content' => [['type' => 'text', 'text' => 'ok']],
            'usage' => array_merge(['input_tokens' => 1000, 'output_tokens' => 500], $extraUsage),
        ];
    }

    // ===== Sampling parameter =====

    public function test_temperature_rimossa_sui_modelli_che_non_la_accettano(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->ok(), 200)]);

        app(ClaudeClient::class)->messages([
            'model' => 'claude-opus-4-8',
            'temperature' => 0.5,
            'top_p' => 0.9,
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ], ['feature' => 'test.sampling']);

        Http::assertSent(fn ($request) => !isset($request['temperature']) && !isset($request['top_p']));
    }

    public function test_temperature_conservata_sui_modelli_che_la_accettano(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->ok(), 200)]);

        app(ClaudeClient::class)->messages([
            'model' => 'claude-haiku-4-5',
            'temperature' => 0.5,
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ], ['feature' => 'test.sampling']);

        Http::assertSent(fn ($request) => ($request['temperature'] ?? null) === 0.5);
    }

    // ===== Costo delle ricerche web =====

    public function test_le_ricerche_web_entrano_nel_costo_e_nei_meta(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(
            $this->ok(['server_tool_use' => ['web_search_requests' => 4]]), 200
        )]);

        app(ClaudeClient::class)->messages([
            'model' => 'claude-opus-4-8',
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ], ['feature' => 'test.search']);

        $u = AiUsage::firstOrFail();
        // token: 5.0*1000/1e6 + 25.0*500/1e6 = 0.005 + 0.0125 = 0.0175
        // ricerche: 4/1000 * 10.0 = 0.04  →  totale 0.0575
        $this->assertEquals('0.057500', $u->cost_usd);
        $this->assertSame(4, $u->meta['web_searches']);
    }

    public function test_senza_ricerche_i_meta_restano_invariati(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->ok(), 200)]);

        app(ClaudeClient::class)->messages([
            'model' => 'claude-haiku-4-5',
            'messages' => [['role' => 'user', 'content' => 'hi']],
        ], ['feature' => 'test.nosearch']);

        $u = AiUsage::firstOrFail();
        // Haiku a listino: 1.0*1000/1e6 + 5.0*500/1e6 = 0.001 + 0.0025 = 0.0035
        $this->assertEquals('0.003500', $u->cost_usd);
        $this->assertNull($u->meta);
    }

    public function test_haiku_e_a_listino(): void
    {
        $this->assertNotNull(config('services.anthropic.prices.claude-haiku-4-5'));
    }

    // ===== Versione del tool web_search =====

    public function test_dynamic_filtering_sui_modelli_che_lo_supportano(): void
    {
        $this->assertSame('web_search_20260209', WebSearchTool::typeFor('claude-opus-4-8'));
        $this->assertSame('web_search_20260209', WebSearchTool::typeFor('claude-sonnet-4-6'));
    }

    public function test_versione_base_sui_modelli_piu_vecchi(): void
    {
        // News AI gira su Sonnet 4.5: mandargli la _20260209 sarebbe un 400.
        $this->assertSame('web_search_20250305', WebSearchTool::typeFor('claude-sonnet-4-5'));
        $this->assertSame('web_search_20250305', WebSearchTool::typeFor(null));
    }

    public function test_definizione_completa_del_tool(): void
    {
        $this->assertSame(
            ['type' => 'web_search_20260209', 'name' => 'web_search', 'max_uses' => 3],
            WebSearchTool::definition('claude-opus-4-8', 3)
        );
    }
}
