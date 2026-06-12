<?php

namespace Tests\Feature\Freshness;

use App\Models\Course;
use App\Models\FreshnessRun;
use App\Services\Freshness\FreshnessClaimExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feedback a schermo dell'esito dei controlli (run async):
 *  A) l'errore Anthropic porta il CORPO della risposta (es. "credit balance too low"), non solo "HTTP 400".
 *  B) l'admin mostra un pannello "Ultimi controlli" con stato + motivo dei run falliti.
 */
class RunFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): array
    {
        return ['admin_logged_in' => true, 'admin_email' => 'a@ente.it'];
    }

    private function makeCourse(): Course
    {
        return Course::create(['name' => 'INTERFERENZA', 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    // ---- A) il motivo riporta il corpo dell'errore API ----

    public function test_errore_fase1_include_il_messaggio_anthropic(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'type' => 'error',
            'error' => ['type' => 'invalid_request_error',
                        'message' => 'Your credit balance is too low to access the Anthropic API.'],
        ], 400)]);

        try {
            app(FreshnessClaimExtractor::class)->extract([
                ['id' => 'b1', 'type' => 'P', 'text' => 'Nel 2024 il mercato vale 1 miliardo di euro.'],
            ]);
            $this->fail('Attesa RuntimeException per HTTP 400.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Fase 1', $e->getMessage());
            $this->assertStringContainsString('HTTP 400', $e->getMessage());
            $this->assertStringContainsString('credit balance is too low', $e->getMessage());
        }
    }

    // ---- B) il pannello a schermo ----

    public function test_pannello_mostra_run_fallito_con_motivo(): void
    {
        $course = $this->makeCourse();
        FreshnessRun::create([
            'course_id' => $course->id, 'status' => 'failed', 'started_at' => now(), 'finished_at' => now(),
            'claims_found' => 0, 'proposals_created' => 0,
            'failure_reason' => 'Anthropic API errore Fase 1: HTTP 400 — Your credit balance is too low to access the Anthropic API.',
        ]);

        $this->withSession($this->admin())
            ->get(route('admin.freshness.proposals.index'))
            ->assertOk()
            ->assertSee('Ultimi controlli')
            ->assertSee('Fallito')
            ->assertSee('credit balance is too low'); // il motivo è visibile a schermo
    }

    public function test_pannello_mostra_completato_con_conteggi(): void
    {
        $course = $this->makeCourse();
        FreshnessRun::create([
            'course_id' => $course->id, 'status' => 'completed', 'started_at' => now(), 'finished_at' => now(),
            'claims_found' => 3, 'proposals_created' => 2, 'failure_reason' => null,
        ]);

        $this->withSession($this->admin())
            ->get(route('admin.freshness.proposals.index'))
            ->assertOk()
            ->assertSee('Completato')
            ->assertSee('3 claim, 2 proposte');
    }

    public function test_nessun_pannello_senza_run(): void
    {
        $this->withSession($this->admin())
            ->get(route('admin.freshness.proposals.index'))
            ->assertOk()
            ->assertDontSee('Ultimi controlli');
    }
}
