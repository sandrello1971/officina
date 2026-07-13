<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FailedJobTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): self
    {
        Admin::create(['name' => 'A', 'email' => 'fj@ente.it', 'password' => 'pw', 'is_active' => true]);
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => 'fj@ente.it']);
    }

    private function seedFailed(string $uuid): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid,
            'connection' => 'database',
            'queue' => 'default',
            'payload' => json_encode(['displayName' => 'App\\Jobs\\GenerateVideoJob']),
            'exception' => "RuntimeException: boom qualcosa\n#0 /app/x.php",
            'failed_at' => now(),
        ]);
    }

    public function test_vista_elenca_i_job_falliti(): void
    {
        $this->seedFailed((string) Str::uuid());

        $this->actingAdmin()->get('/admin/failed-jobs')
            ->assertOk()
            ->assertSee('GenerateVideoJob')
            ->assertSee('boom qualcosa');
    }

    public function test_forget_rimuove_il_job(): void
    {
        $uuid = (string) Str::uuid();
        $this->seedFailed($uuid);
        $this->assertSame(1, DB::table('failed_jobs')->count());

        $this->actingAdmin()->post("/admin/failed-jobs/{$uuid}/forget");

        $this->assertSame(0, DB::table('failed_jobs')->count());
    }
}
