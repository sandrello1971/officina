<?php

namespace Tests\Feature\Ai;

use App\Models\AiUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiUsageReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_aggrega_per_feature(): void
    {
        AiUsage::create(['feature' => 'quiz.generate', 'model' => 'claude-sonnet-4-5',
            'tokens_in' => 1000, 'tokens_out' => 500, 'cost_usd' => 0.0105, 'status' => 'ok', 'created_at' => now()]);
        AiUsage::create(['feature' => 'freshness.verify', 'model' => 'claude-opus-4-8',
            'tokens_in' => 2000, 'tokens_out' => 300, 'cost_usd' => 0.0525, 'status' => 'ok', 'created_at' => now()]);

        $this->artisan('ai:usage')
            ->expectsOutputToContain('quiz.generate')
            ->expectsOutputToContain('freshness.verify')
            ->assertExitCode(0);
    }

    public function test_report_vuoto_non_esplode(): void
    {
        $this->artisan('ai:usage --days=1')->assertExitCode(0);
    }
}
