<?php

namespace Tests\Feature\Freshness;

use App\Jobs\GenerateFreshnessProposalsJob;
use App\Models\Course;
use App\Models\FreshnessClaim;
use App\Models\FreshnessRun;
use App\Models\UpdateProposal;
use App\Services\Freshness\FreshnessAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * P25.4 — la Fase 3 gira in un job a parte (non più dentro l'ultimo VerifyFreshnessClaimJob),
 * proprio per non ereditarne il timeout stretto (dimensionato per UNA verifica).
 */
class GenerateFreshnessProposalsJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeRunWithObsoleteClaim(): array
    {
        $course = Course::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        $run = FreshnessRun::create(['course_id' => $course->id, 'status' => 'running', 'started_at' => now(), 'finished_at' => now()]);
        $claim = FreshnessClaim::create([
            'run_id' => $run->id, 'course_id' => $course->id, 'content_source' => 'instructor',
            'block_id' => 'b', 'sentence_ref' => 1, 'claim_text' => 'x obsoleto', 'category' => 'data',
            'verdict' => 'obsoleto', 'verified_at' => now(),
        ]);

        return [$course, $run, $claim];
    }

    public function test_genera_proposta_e_completa_la_run(): void
    {
        [$course, $run, $claim] = $this->makeRunWithObsoleteClaim();

        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['after' => 'y aggiornato', 'reason' => 'z'])]],
        ], 200)]);

        (new GenerateFreshnessProposalsJob($run->id))->handle(app(FreshnessAgent::class));

        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertSame(1, $run->proposals_created);
        $this->assertDatabaseHas('update_proposals', ['freshness_claim_id' => $claim->id, 'status' => 'pending']);
    }

    public function test_failed_marca_la_run_fallita_invece_di_lasciarla_bloccata(): void
    {
        [$course, $run] = $this->makeRunWithObsoleteClaim();

        $job = new GenerateFreshnessProposalsJob($run->id);
        $job->failed(new RuntimeException('timeout simulato'));

        $run->refresh();
        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('timeout simulato', $run->failure_reason);
    }
}
