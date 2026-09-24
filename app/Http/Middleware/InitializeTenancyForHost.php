<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Database\Models\Domain;
use Symfony\Component\HttpFoundation\Response;

/**
 * Risolve l'ente dall'host (admin.B / learn.B) e inizializza la tenancy PRIMA
 * della sessione: gira in testa al gruppo web.
 *
 * - host di piattaforma (tenancy.central_domains, vetrina, console) → nessun tenant;
 * - host base B di un ente → redirect a learn.B;
 * - host sconosciuto → 404; ente sospeso → 403.
 *
 * Toglie anche il parametro di dominio {tenant_host} dalla rotta: Laravel lo
 * passerebbe come primo argomento a ogni controller.
 */
class InitializeTenancyForHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        $central = [...config('tenancy.central_domains', []), config('domains.site'), config('platform.domain')];

        if (! in_array($host, $central, true)) {
            $domain = Domain::query()->where('domain', $host)->first();

            if (! $domain) {
                $tenant = Tenant::query()->where('base_host', $host)->first();
                if ($tenant) {
                    return redirect()->away('https://' . $tenant->learnHost() . $request->getRequestUri());
                }
                abort(404);
            }

            /** @var Tenant $tenant */
            $tenant = $domain->tenant;
            if (! $tenant->isActive()) {
                abort(403, 'Questo spazio è temporaneamente sospeso.');
            }

            tenancy()->initialize($tenant);
        } elseif (tenancy()->initialized) {
            // Host di piattaforma in un processo che ha già servito un ente
            // (worker long-running, test): mai ereditarne il contesto.
            tenancy()->end();
        }

        $request->route()?->forgetParameter('tenant_host');

        return $next($request);
    }
}
