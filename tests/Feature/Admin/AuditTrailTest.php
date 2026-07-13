<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function actingAdmin(): self
    {
        Admin::create(['name' => 'A', 'email' => 'audit@ente.it', 'password' => 'pw', 'is_active' => true]);
        return $this->withSession(['admin_logged_in' => true, 'admin_email' => 'audit@ente.it']);
    }

    public function test_azione_admin_mutante_viene_registrata(): void
    {
        $this->actingAdmin()->post('/admin/quizzes', ['title' => 'Quiz X', 'passing_score' => 70]);

        $log = AuditLog::where('area', 'admin')->first();
        $this->assertNotNull($log, 'La POST admin deve essere registrata');
        $this->assertSame('admin', $log->actor_type);
        $this->assertSame('audit@ente.it', $log->actor_label);
        $this->assertSame('POST', $log->method);
        $this->assertStringContainsString('quizzes', $log->action);
        $this->assertNotNull($log->created_at);
    }

    public function test_get_admin_non_viene_registrata(): void
    {
        $this->actingAdmin()->get('/admin/quizzes');
        $this->assertSame(0, AuditLog::count(), 'Le GET non vanno registrate');
    }

    public function test_area_pubblica_non_registrata(): void
    {
        $this->post('/verify', ['code' => 'xyz']); // rotta pubblica non-admin/docente
        $this->assertSame(0, AuditLog::where('area', 'admin')->orWhere('area', 'docente')->count());
    }

    public function test_vista_audit_admin_carica(): void
    {
        AuditLog::create(['area' => 'admin', 'actor_type' => 'admin', 'actor_label' => 'x@e.it',
            'action' => 'admin.quizzes.store', 'method' => 'POST', 'path' => '/admin/quizzes',
            'status' => 302, 'created_at' => now()]);

        $this->actingAdmin()->get('/admin/audit')
            ->assertOk()
            ->assertSee('admin.quizzes.store');
    }
}
