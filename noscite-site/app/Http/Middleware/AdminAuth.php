<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session('admin_user')) {
            return redirect()->route('admin.login');
        }

        $admins = config('intranet.admins', []);
        $email = session('admin_user')['email'] ?? null;

        if (!$email || !in_array($email, $admins)) {
            session()->forget('admin_user');
            return redirect()->route('admin.login')
                ->with('error', 'Accesso non autorizzato.');
        }

        return $next($request);
    }
}
