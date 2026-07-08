<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Gate B — nav admin: gruppo "Corsi" espandibile (auto-aperto sulla rotta attiva)
 * e footer (email + Esci) pinnato in fondo al flex (non più position:absolute).
 */
class AdminNavTest extends TestCase
{
    use RefreshDatabase;

    private string $adminEmail = 'admin@ente.it';

    private function actingAdmin()
    {
        Admin::create([
            'name' => 'Admin', 'email' => $this->adminEmail,
            'password' => 'secret-pw', 'is_active' => true,
        ]);

        return $this->withSession(['admin_logged_in' => true, 'admin_email' => $this->adminEmail]);
    }

    public function test_courses_group_is_expanded_on_courses_route(): void
    {
        $res = $this->actingAdmin()->get(route('admin.courses.index'));

        $res->assertOk();
        // Gruppo auto-aperto sulla rotta corrente + sotto-voci presenti.
        $res->assertSee('open: true', false);
        $res->assertSee('Tutti i corsi');
        $res->assertSee('Categorie');
        $res->assertSee('Tag');
        $res->assertSee('Aggiornamenti');
    }

    public function test_courses_group_collapsed_on_dashboard_and_footer_present(): void
    {
        $res = $this->actingAdmin()->get(route('admin.dashboard'));

        $res->assertOk();
        // Fuori dalle rotte del gruppo → collassato.
        $res->assertSee('open: false', false);
        // Footer flex: email + Esci sempre presenti (fix overlap Impostazioni/Esci).
        $res->assertSee('sidebar-footer', false);
        $res->assertSee($this->adminEmail);
        $res->assertSee('Esci');
    }
}
