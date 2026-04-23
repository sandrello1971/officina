<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class MicrosoftAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('azure')
            ->redirectUrl(url('/admin/auth/microsoft/callback'))
            ->scopes(['User.Read'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $azureUser = Socialite::driver('azure')
                ->redirectUrl(url('/admin/auth/microsoft/callback'))
                ->user();
        } catch (\Exception $e) {
            Log::error('Atheneum Admin Microsoft OAuth error: ' . $e->getMessage());
            return redirect()->route('admin.login')
                ->with('error', 'Autenticazione Microsoft non riuscita. Riprova o usa email/password.');
        }

        $email = strtolower($azureUser->getEmail());
        $whitelist = array_map('strtolower', config('atheneum.admins', []));

        if (!in_array($email, $whitelist)) {
            Log::warning('Atheneum Admin SSO: email non autorizzata', [
                'email' => $email,
                'microsoft_id' => $azureUser->getId(),
                'ip' => $request->ip(),
            ]);
            return redirect()->route('admin.login')
                ->with('error', 'Accesso amministratore non autorizzato per ' . $email);
        }

        Log::info('Atheneum Admin SSO: login autorizzato', [
            'email' => $email,
            'microsoft_id' => $azureUser->getId(),
            'ip' => $request->ip(),
        ]);

        session([
            'admin_logged_in' => true,
            'admin_email'     => $email,
        ]);

        return redirect()->route('admin.dashboard');
    }
}
