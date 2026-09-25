<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lega la sessione all'ente che l'ha creata. Lo store delle sessioni è
 * condiviso (redis) e gli id utente si ripetono fra DB diversi: senza questo
 * controllo un cookie di sessione dell'ente A, presentato sull'host dell'ente
 * B, autenticherebbe come l'utente con lo stesso id in B.
 */
class EnsureSessionBelongsToTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()) {
            $session = $request->session();
            $current = tenant()?->getTenantKey() ?? 'central';
            $owner = $session->get('_tenant');

            if ($owner !== null && $owner !== $current) {
                $session->invalidate();
                $session->regenerateToken();
            }
            $session->put('_tenant', $current);
        }

        return $next($request);
    }
}
