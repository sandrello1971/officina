<?php

namespace App\Models;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * Ente che usa Officina (vive nel DB central). Un tenant = un database
 * Postgres dedicato, servito su admin.<base_host> e learn.<base_host>.
 *
 * Il tenant "primario" (is_primary, cioè Effetto Glitch) è l'unico che può
 * avere il modulo Scuola e l'accesso break-glass da .env.
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    use HasDatabase, HasDomains;

    public const AI_KEY_PLATFORM = 'platform';
    public const AI_KEY_TENANT = 'tenant';
    public const AI_KEY_BOTH = 'both';

    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'status',
            'base_host',
            'licensed_modules',
            'ai_key_mode',
            'ai_monthly_budget_usd',
        ];
    }

    protected function casts(): array
    {
        return [
            'licensed_modules' => 'array',
            'ai_monthly_budget_usd' => 'decimal:2',
        ];
    }

    public function isActive(): bool
    {
        return ($this->status ?? 'active') === 'active';
    }

    public function isPrimary(): bool
    {
        return (bool) $this->getAttribute('is_primary');
    }

    public function hasModule(string $module): bool
    {
        if (! $this->isPrimary() && in_array($module, config('modules.primary_only', []), true)) {
            return false;
        }

        return in_array($module, (array) ($this->licensed_modules ?? []), true);
    }

    public function adminHost(): string
    {
        return 'admin.' . $this->base_host;
    }

    public function learnHost(): string
    {
        return 'learn.' . $this->base_host;
    }

    /** Allinea i record `domains` a admin.<base_host> e learn.<base_host>. */
    public function syncDomains(): void
    {
        $wanted = [$this->adminHost(), $this->learnHost()];
        $this->domains()->whereNotIn('domain', $wanted)->delete();
        foreach ($wanted as $host) {
            $this->domains()->firstOrCreate(['domain' => $host]);
        }
    }
}
