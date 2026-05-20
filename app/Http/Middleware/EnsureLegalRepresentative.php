<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLegalRepresentative
{
    /**
     * Verifica che l'admin attualmente loggato corrisponda al legale
     * rappresentante configurato (l'unico autorizzato a firmare i
     * certificati emessi dalla piattaforma).
     *
     * Da applicare DOPO il middleware admin.auth, perché presume
     * che session('admin_email') sia già popolata.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $adminEmail = session('admin_email');
        $legalRepEmail = config('atheneum.legal_representative_email');

        if (!$adminEmail || strtolower($adminEmail) !== strtolower($legalRepEmail)) {
            abort(403, 'Solo il legale rappresentante può firmare i certificati.');
        }

        return $next($request);
    }
}
