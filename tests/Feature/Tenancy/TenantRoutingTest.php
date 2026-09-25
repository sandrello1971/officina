<?php

namespace Tests\Feature\Tenancy;

use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Risoluzione dell'ente dall'host, moduli, credenziali e sessione.
 *
 * Qui tutti gli enti puntano al DB di test (niente clone): si verifica il
 * perimetro HTTP/config, non l'isolamento dei dati, che richiede DB reali.
 */
class TenantRoutingTest extends TestCase
{
    use RefreshDatabase;

    private function secondaryTenant(array $attributes = []): Tenant
    {
        $tenant = Tenant::create(array_merge([
            'id' => 'altro',
            'name' => 'Ente Altro',
            'slug' => 'altro',
            'status' => 'active',
            'base_host' => 'altro.officina.test',
            'licensed_modules' => config('modules.default'),
            'ai_key_mode' => Tenant::AI_KEY_PLATFORM,
            'tenancy_db_name' => config('database.connections.pgsql.database'),
            'tenancy_create_database' => false,
        ], $attributes));
        $tenant->syncDomains();

        return $tenant;
    }

    public function test_host_sconosciuto_da_404(): void
    {
        $this->get('https://learn.sconosciuto.test/login')->assertNotFound();
    }

    public function test_host_base_reindirizza_a_learn(): void
    {
        $this->secondaryTenant();

        $this->get('https://altro.officina.test/login')
            ->assertRedirect('https://learn.altro.officina.test/login');
        $this->get('http://altro.officina.test:8765/login')
            ->assertRedirect('http://learn.altro.officina.test:8765/login');
    }

    public function test_ente_sospeso_da_403(): void
    {
        $this->secondaryTenant(['status' => 'suspended']);

        $this->get('https://learn.altro.officina.test/login')->assertForbidden();
    }

    public function test_route_genera_url_sull_host_dell_ente_corrente(): void
    {
        $this->secondaryTenant();

        $this->get('https://learn.altro.officina.test/login')->assertOk();
        $this->assertSame('https://learn.altro.officina.test/login', route('student.login'));
        $this->assertSame('https://admin.altro.officina.test/login', route('admin.login'));
    }

    public function test_scuola_solo_sull_ente_primario(): void
    {
        $this->secondaryTenant(['licensed_modules' => array_keys(config('modules.modules'))]);

        // Anche se "licenziato", sul secondario il modulo Scuola non esiste.
        $this->get('https://learn.altro.officina.test/scuola')->assertNotFound();
        $this->get('https://learn.altro.officina.test/docente')->assertNotFound();
        $this->assertFalse(Tenant::find('altro')->hasModule('scuola'));

        // Sul primario il gate non interviene (redirect al login del gate school_admin).
        $this->get($this->learnUrl('/scuola'))->assertStatus(302);
    }

    public function test_modulo_spento_da_404_sulle_sue_rotte(): void
    {
        $this->tenant->update(['licensed_modules' => []]);
        session(['admin_logged_in' => true]);

        $this->withSession(['admin_logged_in' => true])
            ->get($this->adminUrl('/news'))->assertNotFound();
    }

    public function test_break_glass_env_rifiutato_sugli_enti_secondari(): void
    {
        config(['admin.email' => 'root@example.test', 'admin.password_hash' => Hash::make('segreta-123')]);
        $this->secondaryTenant();

        $this->post('https://admin.altro.officina.test/login', ['email' => 'root@example.test', 'password' => 'segreta-123'])
            ->assertSessionHasErrors('email');

        $this->post($this->adminUrl('/login'), ['email' => 'root@example.test', 'password' => 'segreta-123'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_sessione_di_un_ente_non_vale_su_un_altro(): void
    {
        $this->secondaryTenant();
        $s = Student::create(['name' => 'S', 'email' => 's' . uniqid() . '@e.it', 'password' => bcrypt('x'),
            'role' => 'student', 'is_active' => true, 'must_change_password' => false]);

        // Sessione nata sul primario, ripresentata sull'host dell'altro ente.
        $this->withSession(['_tenant' => $this->tenant->id, 'student_id' => $s->id])
            ->get('https://learn.altro.officina.test/dashboard')
            ->assertRedirect();

        $this->assertNull(session('student_id'));
        $this->assertSame('altro', session('_tenant'));
    }

    public function test_chiave_ai_segue_la_modalita_dell_ente(): void
    {
        tenancy()->end();
        config(['services.anthropic.key' => 'platform-key']);
        \App\Support\TenantConfig::rebaseline();
        tenancy()->initialize($this->tenant);
        atheneum_setting_put('api_key_anthropic_encrypted', Crypt::encryptString('ente-key'));

        $cases = [
            Tenant::AI_KEY_PLATFORM => ['platform-key', 'platform'],
            Tenant::AI_KEY_TENANT => ['ente-key', 'tenant'],
            Tenant::AI_KEY_BOTH => ['ente-key', 'tenant'],
        ];
        foreach ($cases as $mode => [$key, $source]) {
            tenancy()->end();
            $this->tenant->update(['ai_key_mode' => $mode]);
            tenancy()->initialize($this->tenant->fresh());

            $this->assertSame($key, config('services.anthropic.key'), $mode);
            $this->assertSame($source, config('services.anthropic.key_source'), $mode);
        }

        // Modalità "tenant" senza chiave dell'ente: vuota, mai quella di piattaforma.
        atheneum_setting_put('api_key_anthropic_encrypted', '');
        tenancy()->end();
        $this->tenant->update(['ai_key_mode' => Tenant::AI_KEY_TENANT]);
        tenancy()->initialize($this->tenant->fresh());
        $this->assertSame('', config('services.anthropic.key'));
    }
}
