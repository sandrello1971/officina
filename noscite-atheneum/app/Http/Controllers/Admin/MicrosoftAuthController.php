<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
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
            Log::error('Officina Admin Microsoft OAuth error: ' . $e->getMessage());
            return redirect()->route('admin.login')
                ->with('error', 'Autenticazione Microsoft non riuscita. Riprova o usa email/password.');
        }

        $email = strtolower($azureUser->getEmail());

        // Whitelist via DB: deve esistere un Admin attivo con questa email.
        // Refactor P1.2: prima si usava config('atheneum.admins'), ora gestione
        // 100% via UI /admin/admins (AdminAccountController + tabella admins).
        $admin = Admin::where('email', $email)->where('is_active', true)->first();

        if (!$admin) {
            Log::warning('Officina Admin SSO: email non autorizzata', [
                'email' => $email,
                'microsoft_id' => $azureUser->getId(),
                'ip' => $request->ip(),
            ]);
            return redirect()->route('admin.login')
                ->with('error', 'Accesso amministratore non autorizzato per ' . $email);
        }

        Log::info('Officina Admin SSO: login autorizzato', [
            'email' => $email,
            'admin_id' => $admin->id,
            'microsoft_id' => $azureUser->getId(),
            'ip' => $request->ip(),
        ]);

        // Se 2FA attivo: intercetta prima del session login.
        if ($admin->hasTwoFactorEnabled()) {
            $request->session()->put('admin_2fa_pending_id', $admin->id);
            Log::info('[admin] SSO OK, 2FA challenge required', [
                'admin_id' => $admin->id,
                'email' => $admin->email,
            ]);
            return redirect()->route('admin.2fa.challenge');
        }

        session([
            'admin_logged_in' => true,
            'admin_email'     => $admin->email,
        ]);

        return redirect()->route('admin.dashboard');
    }
}
