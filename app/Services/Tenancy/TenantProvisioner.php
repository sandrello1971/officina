<?php

namespace App\Services\Tenancy;

use App\Models\Admin;
use App\Models\Tenant;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\Models\Domain;

/**
 * Crea un ente end-to-end: record central + domini, database clonato dal
 * template (pipeline TenantCreated), impostazioni di base, storage e primo
 * admin. Unica fonte di verità per CLI (tenant:create) e console di piattaforma.
 */
class TenantProvisioner
{
    /**
     * @param  array{name:string,base_host:string,admin_email:string,admin_name?:?string,slug?:?string,modules?:?array<string>,ai_key_mode?:?string,ai_monthly_budget_usd?:float|string|null}  $params
     * @return array{tenant:Tenant,temp_password:string,db:string}
     */
    public function provision(array $params): array
    {
        $name = trim((string) ($params['name'] ?? ''));
        $baseHost = self::normalizeHost((string) ($params['base_host'] ?? ''));
        $adminEmail = strtolower(trim((string) ($params['admin_email'] ?? '')));

        if ($name === '' || $baseHost === '' || $adminEmail === '') {
            throw new \InvalidArgumentException('Obbligatori: nome, host base, email admin.');
        }
        if (! filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Email admin non valida: {$adminEmail}");
        }

        $slug = Str::slug((string) ($params['slug'] ?? '') ?: $name);
        $this->assertAvailable($slug, $baseHost);

        $modules = self::sanitizeModules($params['modules'] ?? config('modules.default'), primary: false);
        $aiMode = (string) ($params['ai_key_mode'] ?? Tenant::AI_KEY_PLATFORM);
        if (! in_array($aiMode, [Tenant::AI_KEY_PLATFORM, Tenant::AI_KEY_TENANT, Tenant::AI_KEY_BOTH], true)) {
            throw new \InvalidArgumentException("Modalità chiave AI non valida: {$aiMode}");
        }

        // Pipeline TenantCreated: clone del template + migrate (no-op se allineato).
        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'base_host' => $baseHost,
            'licensed_modules' => $modules,
            'ai_key_mode' => $aiMode,
            'ai_monthly_budget_usd' => $params['ai_monthly_budget_usd'] ?? null,
        ]);
        $tenant->syncDomains();

        $tempPassword = Str::password(16);

        $tenant->run(function () use ($name, $adminEmail, $params, $tempPassword) {
            $this->provisionStorage();

            foreach ([
                'instance_name' => $name,
                'platform_owner' => $name,
                'contact_email' => $adminEmail,
                // Le automazioni a consumo partono spente: le accende l'ente.
                'ainews_auto_enabled' => '0',
                'freshness_auto_enabled' => '0',
                'gap_scout_auto_enabled' => '0',
            ] as $key => $value) {
                atheneum_setting_put($key, $value);
            }

            // Tema slide/PDF neutro: il tema Glitch è il brand di Effetto Glitch.
            \App\Models\BrandProfile::create(['base_theme' => \App\Enums\BaseTheme::Classico->value]);

            Admin::create([
                'name' => $params['admin_name'] ?? $adminEmail,
                'email' => $adminEmail,
                'password' => $tempPassword,
                'is_active' => true,
                'can_sign_certificates' => true,
            ]);
        });

        return [
            'tenant' => $tenant,
            'temp_password' => $tempPassword,
            'db' => $tenant->database()->getName(),
        ];
    }

    public function assertAvailable(string $slug, string $baseHost, ?string $exceptTenantId = null): void
    {
        $tenants = Tenant::query()->when($exceptTenantId, fn ($q) => $q->where('id', '!=', $exceptTenantId));
        if ((clone $tenants)->where('slug', $slug)->exists()) {
            throw new \InvalidArgumentException("Esiste già un ente con slug '{$slug}'.");
        }
        if ((clone $tenants)->where('base_host', $baseHost)->exists()) {
            throw new \InvalidArgumentException("L'host base '{$baseHost}' è già in uso.");
        }
        if (in_array($baseHost, config('tenancy.central_domains', []), true)) {
            throw new \InvalidArgumentException("'{$baseHost}' è un dominio di piattaforma.");
        }
        foreach (['admin.' . $baseHost, 'learn.' . $baseHost] as $host) {
            $taken = Domain::query()->where('domain', $host)
                ->when($exceptTenantId, fn ($q) => $q->where('tenant_id', '!=', $exceptTenantId))
                ->exists();
            if ($taken) {
                throw new \InvalidArgumentException("Il dominio '{$host}' è già assegnato.");
            }
        }
    }

    public static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('#^https?://#', '', $host);
        $host = rtrim(explode('/', $host)[0], '.');
        if ($host !== '' && ! preg_match('/^(?=.{4,200}$)([a-z0-9]([a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $host)) {
            throw new \InvalidArgumentException("Host non valido: {$host}");
        }

        return $host;
    }

    /** Solo moduli noti; i moduli riservati al primario sono scartati per gli altri. */
    public static function sanitizeModules(array $modules, bool $primary): array
    {
        $known = array_keys(config('modules.modules', []));
        $modules = array_values(array_unique(array_intersect(array_map('trim', $modules), $known)));
        if (! $primary) {
            $modules = array_values(array_diff($modules, config('modules.primary_only', [])));
        }

        return $modules;
    }

    /** In contesto tenant storage_path() è già suffissato con tenant<id>. */
    private function provisionStorage(): void
    {
        foreach (['app/private', 'app/public', 'framework/cache', 'framework/views', 'logs'] as $dir) {
            $path = storage_path($dir);
            if (! is_dir($path)) {
                @mkdir($path, 0775, true);
            }
        }
    }
}
