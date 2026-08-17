<?php

namespace Tests\Feature\Freshness;

use App\Jobs\VerifyFreshnessClaimJob;
use App\Models\Course;
use App\Models\FreshnessClaim;
use App\Models\FreshnessRun;
use App\Models\UpdateProposal;
use App\Services\Freshness\FreshnessAgent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * P25.4 — il job di verifica per-claim: aggiorna il claim, e chiude la run (Fase 3 +
 * status) solo quando è l'ultimo claim non verificato. Un claim fallito lascia
 * verdict=null (stessa resilienza del loop sincrono che sostituisce) senza mai
 * bloccare la chiusura della run.
 */
class VerifyFreshnessClaimJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeRunWithClaims(int $count, Course $course): FreshnessRun
    {
        $run = FreshnessRun::create(['course_id' => $course->id, 'status' => 'running', 'started_at' => now()]);
        for ($i = 0; $i < $count; $i++) {
            FreshnessClaim::create([
                'run_id' => $run->id,
                'course_id' => $course->id,
                'content_source' => 'instructor',
                'block_id' => "b{$i}",
                'sentence_ref' => 1,
                'claim_text' => "claim {$i}",
                'category' => 'data',
            ]);
        }

        return $run;
    }

    public function test_verifica_claim_e_lo_aggiorna(): void
    {
        $course = Course::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        $run = $this->makeRunWithClaims(1, $course);
        $claim = FreshnessClaim::where('run_id', $run->id)->firstOrFail();

        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['verdict' => 'attuale', 'confidence' => 0.6])]],
        ], 200)]);

        (new VerifyFreshnessClaimJob($claim->id))->handle(app(\App\Services\Freshness\FreshnessVerifier::class), app(FreshnessAgent::class));

        $claim->refresh();
        $this->assertSame('attuale', $claim->verdict);
        $this->assertNotNull($claim->verified_at);
        $this->assertSame('completed', $run->fresh()->status); // unico claim → chiude la run
    }

    public function test_non_chiude_la_run_se_restano_claim_da_verificare(): void
    {
        $course = Course::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        $run = $this->makeRunWithClaims(2, $course);
        $claims = FreshnessClaim::where('run_id', $run->id)->get();

        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['verdict' => 'attuale', 'confidence' => 0.6])]],
        ], 200)]);

        (new VerifyFreshnessClaimJob($claims[0]->id))->handle(app(\App\Services\Freshness\FreshnessVerifier::class), app(FreshnessAgent::class));

        $this->assertSame('running', $run->fresh()->status); // resta 1 claim non verificato
        $this->assertNull($claims[1]->fresh()->verdict);
    }

    public function test_claim_fallito_resta_non_verificato_e_non_blocca_la_run(): void
    {
        $course = Course::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        $run = $this->makeRunWithClaims(1, $course);
        $claim = FreshnessClaim::where('run_id', $run->id)->firstOrFail();

        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

        (new VerifyFreshnessClaimJob($claim->id))->handle(app(\App\Services\Freshness\FreshnessVerifier::class), app(FreshnessAgent::class));

        $claim->refresh();
        $this->assertNull($claim->verdict); // fallito → lasciato non verificato, non un'eccezione
        // Era l'unico claim: la run si chiude comunque (nessun claim resta "pending" per sempre).
        $this->assertSame('completed', $run->fresh()->status);
    }

    public function test_finish_run_e_idempotente_su_doppia_chiamata(): void
    {
        $course = Course::create(['name' => 'C', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
        $run = FreshnessRun::create(['course_id' => $course->id, 'status' => 'running', 'started_at' => now()]);
        $claim = FreshnessClaim::create([
            'run_id' => $run->id, 'course_id' => $course->id, 'content_source' => 'instructor',
            'block_id' => 'b', 'sentence_ref' => 1, 'claim_text' => 'x', 'category' => 'data',
            'verdict' => 'obsoleto', 'verified_at' => now(),
        ]);

        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode(['after' => 'y', 'reason' => 'z'])]],
        ], 200)]);

        $agent = app(FreshnessAgent::class);
        $agent->finishRun($run->fresh());
        $agent->finishRun($run->fresh()); // simula la race: due job arrivano entrambi "ultimi"

        $this->assertSame('completed', $run->fresh()->status);
        $this->assertCount(1, UpdateProposal::where('freshness_claim_id', $claim->id)->get()); // niente doppioni
    }
}
