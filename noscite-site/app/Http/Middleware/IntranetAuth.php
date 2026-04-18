<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IntranetAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session('intranet_user')) {
            return redirect()->route('intranet.login');
        }

        $user = session('intranet_user');
        if (!str_ends_with($user['email'], '@noscite.it')) {
            session()->forget('intranet_user');
            return redirect()->route('intranet.login')
                ->with('error', 'Accesso riservato al team Noscite.');
        }

        return $next($request);
    }
}
