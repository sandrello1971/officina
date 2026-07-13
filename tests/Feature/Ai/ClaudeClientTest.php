<?php

namespace Tests\Feature\Ai;

use App\Models\AiUsage;
use App\Services\Ai\ClaudeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClaudeClientTest extends TestCase
{
    use RefreshDatabase;

    private function ok(int $in = 1000, int $out = 500, string $text = 'ciao'): array
    {
        return ['content' => [['type' => 'text', 'text' => $text]],
                'usage' => ['input_tokens' => $in, 'output_tokens' => $out],
                'model' => 'claude-sonnet-4-5'];
    }

    public function test_metering_su_successo_con_costo_e_contesto(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response($this->ok(), 200)]);
        $courseId = (string) Str::uuid();

        $res = app(ClaudeClient::class)->messages(
            ['messages' => [['role' => 'user', 'content' => 'hi']]],
            ['feature' => 'test.feature', 'course_id' => $courseId, 'actor_type' => 'admin']
        );

        $this->assertTrue($res->ok);
        $this->assertSame('ciao', $res->text());

        $u = AiUsage::firstOrFail();
        $this->assertSame('test.feature', $u->feature);
        $this->assertSame('claude-sonnet-4-5', $u->model);
        $this->assertSame(1000, $u->tokens_in);
        $this->assertSame(500, $u->tokens_out);
        // 3.0*1000/1e6 + 15.0*500/1e6 = 0.003 + 0.0075 = 0.0105
        $this->assertEquals('0.010500', $u->cost_usd);
        $this->assertSame($courseId, $u->course_id);
        $this->assertSame('ok', $u->status);
    }

    public function test_errore_non_ritentabile_viene_registrato(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'bad']], 400)]);

        $res = app(ClaudeClient::class)->messages(
            ['messages' => [['role' => 'user', 'content' => 'hi']]],
            ['feature' => 'test.err']
        );

        $this->assertTrue($res->failed());
        $u = AiUsage::where('feature', 'test.err')->firstOrFail();
        $this->assertSame('error', $u->status);
        $this->assertSame(0, $u->tokens_in);
    }

    public function test_retry_su_429_poi_successo(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push(['error' => ['message' => 'overloaded']], 429)
            ->push($this->ok(10, 5, 'ok'), 200)]);

        $res = app(ClaudeClient::class)->messages(
            ['messages' => [['role' => 'user', 'content' => 'hi']]],
            ['feature' => 'test.retry']
        );

        $this->assertTrue($res->ok);
        $this->assertSame('ok', $res->text());
        // solo la chiamata finale riuscita viene messa a metering
        $this->assertSame(1, AiUsage::where('feature', 'test.retry')->count());
    }
}
