<?php

namespace Tests\Feature\P26;

use App\Jobs\RunGapScoutJob;
use App\Models\Course;
use App\Models\CourseFreshnessConfig;
use App\Models\GapScoutRun;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * P26.3 — Scheduler dello Scout di copertura. Gemello di FreshnessSchedulingTest:
 * stessa meccanica (interruttore globale, cadenza per corso, cap, run già in corso saltate).
 * Vincolo: lo scheduler dispatcha SOLO il job di rilevamento — nessuna scrittura sui corsi.
 */
class GapScoutSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::put('gap_scout_auto_enabled', '1');
    }

    private function course(string $name = 'Corso'): Course
    {
        return Course::create(['name' => $name, 'slug' => 'c-' . uniqid(), 'is_active' => true, 'sort_order' => 1]);
    }

    private function config(Course $course, string $gapCadence, ?string $gapLastRunAt): void
    {
        CourseFreshnessConfig::create([
            'course_id' => $course->id, 'web_search_enabled' => true, 'primary_sources' => [],
            'audience' => 'adult', 'proposals_enabled' => true,
            'gap_cadence' => $gapCadence, 'gap_last_run_at' => $gapLastRunAt,
        ]);
    }

    public function test_scheduler_seleziona_solo_i_corsi_scaduti(): void
    {
        Queue::fake();

        $due1 = $this->course('Scaduto mensile');
        $this->config($due1, 'monthly', now()->subMonths(2)->toDateTimeString());

        $due2 = $this->course('Mai eseguito');
        $this->config($due2, 'weekly', null);

        $fresh = $this->course('Fresco');
        $this->config($fresh, 'monthly', now()->subDays(3)->toDateTimeString());

        $off = $this->course('Disattivato');
        $this->config($off, 'off', null);

        $this->artisan('gap:scout-run-due')->assertExitCode(0);

        Queue::assertPushed(RunGapScoutJob::class, fn ($j) => $j->courseId === $due1->id);
        Queue::assertPushed(RunGapScoutJob::class, fn ($j) => $j->courseId === $due2->id);
        Queue::assertNotPushed(RunGapScoutJob::class, fn ($j) => $j->courseId === $fresh->id);
        Queue::assertNotPushed(RunGapScoutJob::class, fn ($j) => $j->courseId === $off->id);
        Queue::assertPushed(RunGapScoutJob::class, 2);
    }

    public function test_scheduler_rispetta_il_cap(): void
    {
        Queue::fake();
        for ($i = 0; $i < 6; $i++) {
            $c = $this->course("Scaduto {$i}");
            $this->config($c, 'weekly', null);
        }

        $this->artisan('gap:scout-run-due', ['--limit' => 3])->assertExitCode(0);

        Queue::assertPushed(RunGapScoutJob::class, 3);
    }

    public function test_scheduler_salta_corsi_con_run_gia_in_corso(): void
    {
        Queue::fake();
        $running = $this->course('In corso');
        $this->config($running, 'weekly', null);
        GapScoutRun::create(['course_id' => $running->id, 'status' => 'running', 'started_at' => now()]);

        $this->artisan('gap:scout-run-due')->assertExitCode(0);

        Queue::assertNotPushed(RunGapScoutJob::class);
    }

    public function test_default_gap_cadenza_e_off(): void
    {
        $course = $this->course();
        $cfg = CourseFreshnessConfig::create([
            'course_id' => $course->id, 'web_search_enabled' => true, 'primary_sources' => [],
        ]);

        $this->assertSame('off', $cfg->refresh()->gap_cadence);
    }

    /** Interruttore globale di costo: se gap_scout_auto_enabled è OFF, nessun run automatico. */
    public function test_scheduler_non_lancia_nulla_se_il_flag_globale_e_off(): void
    {
        Setting::put('gap_scout_auto_enabled', '0');
        $due = $this->course('Scaduto');
        $this->config($due, 'monthly', null);

        Queue::fake();
        $this->artisan('gap:scout-run-due')->assertExitCode(0);

        Queue::assertNothingPushed();
    }
}
