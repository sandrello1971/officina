<?php

namespace App\Services\Tenancy;

use App\Models\AiUsage;
use App\Models\Tenant;
use Carbon\CarbonInterface;

/**
 * Consumo AI per ente, letto dal DB di ciascun tenant. Usato dal report
 * `ai:usage --all-tenants` e dalla console di piattaforma.
 */
class TenantAiUsage
{
    /**
     * @return array<string, array{tenant:Tenant,calls:int,platform_usd:float,tenant_usd:float,budget:?float}>
     */
    public function summary(CarbonInterface $since): array
    {
        $out = [];

        foreach (Tenant::query()->orderBy('name')->get() as $tenant) {
            $row = $tenant->run(fn () => AiUsage::query()
                ->where('created_at', '>=', $since)
                ->selectRaw("COUNT(*) as calls,
                    COALESCE(SUM(CASE WHEN key_source = 'platform' THEN cost_usd END), 0) as platform_usd,
                    COALESCE(SUM(CASE WHEN key_source = 'tenant' THEN cost_usd END), 0) as tenant_usd")
                ->first());

            $out[$tenant->id] = [
                'tenant' => $tenant,
                'calls' => (int) $row->calls,
                'platform_usd' => (float) $row->platform_usd,
                'tenant_usd' => (float) $row->tenant_usd,
                'budget' => $tenant->ai_monthly_budget_usd !== null ? (float) $tenant->ai_monthly_budget_usd : null,
            ];
        }

        return $out;
    }
}
