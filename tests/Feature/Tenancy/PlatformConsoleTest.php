<?php

namespace Tests\Feature\Tenancy;

use App\Models\Admin;
use App\Models\PlatformUser;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class PlatformConsoleTest extends TestCase
{
    use RefreshDatabase;

    private function url(string $path = '/'): string
    {
        return 'https://' . config('platform.domain') . '/' . ltrim($path, '/');
    }

    private function operator(): PlatformUser
    {
        return PlatformUser::create(['name' => 'Op', 'email' => 'op@piattaforma.test', 'password' => 'segreta-123', 'is_active' => true]);
    }

    private function secondary(): Tenant
    {
        $t = Tenant::create([
            'id' => 'altro', 'name' => 'Altro', 'slug' => 'altro', 'base_host' => 'altro.officina.test',
            'licensed_modules' => ['ai_news'], 'ai_key_mode' => 'platform',
            'tenancy_db_name' => config('database.connections.pgsql.database'), 'tenancy_create_database' => false,
        ]);
        $t->syncDomains();

        return $t;
    }

    public function test_senza_accesso_rimanda_al_login(): void
    {
        $this->get($this->url('/'))->assertRedirect(route('platform.login'));
    }

    public function test_la_console_non_e_raggiungibile_dagli_host_degli_enti(): void
    {
        $this->get($this->adminUrl('/enti/nuovo'))->assertNotFound();
    }

    public function test_login_richiede_password_e_2fa_obbligatorio(): void
    {
        $op = $this->operator();

        $this->post($this->url('/login'), ['email' => $op->email, 'password' => 'sbagliata'])->assertSessionHasErrors('email');

        $this->post($this->url('/login'), ['email' => $op->email, 'password' => 'segreta-123'])->assertRedirect(route('platform.2fa'));
        // Password corretta ma 2FA non ancora superato: la console resta chiusa.
        $this->get($this->url('/'))->assertRedirect(route('platform.login'));

        $this->get($this->url('/2fa'))->assertOk()->assertSee('Configura il 2FA');
        $secret = $op->fresh()->twoFactorSecret();
        $this->post($this->url('/2fa'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post($this->url('/2fa'), ['code' => (new Google2FA())->getCurrentOtp($secret)])
            ->assertRedirect(route('platform.tenants.index'));

        $this->assertNotNull($op->fresh()->two_factor_confirmed_at);
        $this->get($this->url('/'))->assertOk()->assertSee('Effetto Glitch');
    }

    public function test_aggiornamento_ente_scarta_scuola_e_riallinea_i_domini(): void
    {
        $op = $this->operator();
        $tenant = $this->secondary();

        $this->withSession(['platform_user_id' => $op->id])->put($this->url('/enti/altro'), [
            'name' => 'Altro Ente', 'base_host' => 'formazione.altro.it', 'status' => 'suspended',
            'modules' => ['ai_news', 'scuola'], 'ai_key_mode' => 'tenant', 'ai_monthly_budget_usd' => '25',
        ])->assertSessionHasNoErrors();

        $tenant->refresh();
        $this->assertSame(['ai_news'], $tenant->licensed_modules);
        $this->assertSame('suspended', $tenant->status);
        $this->assertEqualsCanonicalizing(
            ['admin.formazione.altro.it', 'learn.formazione.altro.it'],
            $tenant->domains()->pluck('domain')->all()
        );
    }

    public function test_host_gia_usato_da_un_altro_ente_rifiutato(): void
    {
        $op = $this->operator();
        $this->secondary();

        $this->withSession(['platform_user_id' => $op->id])->put($this->url('/enti/altro'), [
            'name' => 'Altro', 'base_host' => config('domains.base'), 'status' => 'active',
            'modules' => [], 'ai_key_mode' => 'platform',
        ])->assertSessionHasErrors('base_host');
    }

    public function test_reset_password_admin_dell_ente(): void
    {
        $op = $this->operator();
        $admin = Admin::create(['name' => 'A', 'email' => 'a@ente.it', 'password' => 'vecchia', 'is_active' => true]);

        $res = $this->withSession(['platform_user_id' => $op->id])
            ->post($this->url("/enti/{$this->tenant->id}/admin/{$admin->id}/password"));

        $password = $res->getSession()->get('credentials')['password'];
        $this->assertTrue(Hash::check($password, $admin->fresh()->password));
    }

    public function test_eliminazione_protetta(): void
    {
        $op = $this->operator();
        $this->secondary();

        $this->withSession(['platform_user_id' => $op->id])->delete($this->url("/enti/{$this->tenant->id}"))->assertForbidden();
        $this->withSession(['platform_user_id' => $op->id])->delete($this->url('/enti/altro'), ['confirm_slug' => 'sbagliato'])
            ->assertSessionHasErrors('confirm_slug');

        // Registrato con tenancy_create_database=false: il record sparisce, il DB resta.
        $this->withSession(['platform_user_id' => $op->id])->delete($this->url('/enti/altro'), ['confirm_slug' => 'altro'])
            ->assertRedirect(route('platform.tenants.index'));
        $this->assertNull(Tenant::find('altro'));
    }
}
