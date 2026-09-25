<?php

namespace App\Http\Middleware;

use App\Models\PlatformUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Console di piattaforma: operatore attivo, password + 2FA superati. */
class PlatformAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = ($id = session('platform_user_id')) ? PlatformUser::find($id) : null;

        if (! $user || ! $user->is_active || tenant() !== null) {
            session()->forget('platform_user_id');

            return redirect()->route('platform.login');
        }

        $request->attributes->set('platform_user', $user);

        return $next($request);
    }
}
