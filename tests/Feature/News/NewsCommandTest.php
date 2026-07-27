<?php

namespace Tests\Feature\News;

use App\Jobs\FetchAiNewsJob;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * ainews:fetch-weekly — interruttore globale di costo. Default OFF: nessun dispatch.
 */
class NewsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_flag_off_non_lancia_nulla(): void
    {
        Setting::put('ainews_auto_enabled', '0');
        Queue::fake();

        $this->artisan('ainews:fetch-weekly')->assertExitCode(0);

        Queue::assertNothingPushed();
    }

    public function test_flag_on_dispatcha_il_job(): void
    {
        Setting::put('ainews_auto_enabled', '1');
        Queue::fake();

        $this->artisan('ainews:fetch-weekly')->assertExitCode(0);

        Queue::assertPushed(FetchAiNewsJob::class, 1);
    }
}
