<?php

namespace Tests\Feature\Tenancy;

use App\Models\Admin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantFailedJobsTest extends TestCase
{
    use RefreshDatabase;

    private function seedFailed(?string $tenantId, string $name): string
    {
        $uuid = (string) Str::uuid();
        DB::table('failed_jobs')->insert([
            'uuid' => $uuid, 'connection' => 'redis', 'queue' => 'default',
            'payload' => json_encode(array_filter(['displayName' => $name, 'tenant_id' => $tenantId])),
            'exception' => "RuntimeException: boom\n#0", 'failed_at' => now(),
        ]);

        return $uuid;
    }

    public function test_ogni_ente_vede_e_tocca_solo_i_propri_job_falliti(): void
    {
        $altro = Tenant::create([
            'id' => 'altro', 'name' => 'Altro', 'slug' => 'altro', 'base_host' => 'altro.officina.test',
            'licensed_modules' => [], 'tenancy_db_name' => config('database.connections.pgsql.database'),
            'tenancy_create_database' => false,
        ]);
        $altro->syncDomains();
        Admin::create(['name' => 'A', 'email' => 'a@ente.it', 'password' => 'pw', 'is_active' => true]);

        $this->seedFailed($this->tenant->id, 'JobDelPrimario');
        $this->seedFailed(null, 'JobStorico');
        $foreign = $this->seedFailed('altro', 'JobDellAltroEnte');

        $admin = ['admin_logged_in' => true, 'admin_email' => 'a@ente.it'];

        $this->withSession($admin)->get($this->adminUrl('/failed-jobs'))
            ->assertOk()->assertSee('JobDelPrimario')->assertSee('JobStorico')->assertDontSee('JobDellAltroEnte');

        $this->withSession($admin)->post($this->adminUrl("/failed-jobs/{$foreign}/forget"))->assertNotFound();

        $this->withSession($admin)->post($this->adminUrl('/failed-jobs/flush'));
        $this->assertSame(['JobDellAltroEnte'], DB::table('failed_jobs')->pluck('payload')
            ->map(fn ($p) => json_decode($p, true)['displayName'])->all());
    }
}
