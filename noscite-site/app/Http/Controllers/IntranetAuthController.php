<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class IntranetAuthController extends Controller
{
    public function login()
    {
        if (session('intranet_user')) {
            return redirect()->route('intranet.dashboard');
        }
        return view('intranet.login');
    }

    public function redirect()
    {
        return Socialite::driver('microsoft')
            ->redirectUrl(url('/intranet/auth/callback'))
            ->scopes(['openid', 'profile', 'email', 'User.Read'])
            ->redirect();
    }

    public function callback()
    {
        try {
            $msUser = Socialite::driver('microsoft')
                ->redirectUrl(url('/intranet/auth/callback'))
                ->user();

            if (!str_ends_with($msUser->getEmail(), '@noscite.it')) {
                return redirect()->route('intranet.login')
                    ->with('error', 'Accesso riservato al team Noscite (@noscite.it).');
            }

            $admins = config('intranet.admins', []);
            $isAdmin = in_array($msUser->getEmail(), $admins);

            session([
                'intranet_user' => [
                    'id' => $msUser->getId(),
                    'name' => $msUser->getName(),
                    'email' => $msUser->getEmail(),
                    'avatar' => $msUser->getAvatar(),
                    'token' => $msUser->token,
                    'is_admin' => $isAdmin,
                    'role' => $isAdmin ? 'admin' : 'user',
                ],
            ]);

            return redirect()->route('intranet.dashboard');
        } catch (\Exception $e) {
            Log::error('Microsoft OAuth error: ' . $e->getMessage());
            return redirect()->route('intranet.login')
                ->with('error', 'Errore di autenticazione. Riprova.');
        }
    }

    public function logout()
    {
        session()->forget('intranet_user');
        return redirect()->route('intranet.login');
    }
}
