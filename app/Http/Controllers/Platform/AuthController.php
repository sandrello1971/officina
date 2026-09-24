<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformUser;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use PragmaRX\Google2FA\Google2FA;

/**
 * Accesso alla console di piattaforma: password, poi TOTP. Chi non ha ancora
 * il 2FA lo configura subito: senza, la console non si apre.
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        return view('platform.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $user = PlatformUser::where('email', strtolower($data['email']))->first();

        if (! $user || ! $user->is_active || ! Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['email' => 'Credenziali non valide.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        session(['platform_pending_id' => $user->id]);

        return redirect()->route('platform.2fa');
    }

    public function showTwoFactor(Google2FA $google2fa)
    {
        $user = $this->pending();
        if (! $user) {
            return redirect()->route('platform.login');
        }

        if ($user->hasTwoFactorEnabled()) {
            return view('platform.two-factor', ['setup' => false]);
        }

        // Primo accesso: genera il secret (non ancora confermato) e mostra il QR.
        if (! $user->twoFactorSecret()) {
            $user->setTwoFactorSecret($google2fa->generateSecretKey());
            $user->save();
        }
        $qr = Builder::create()->writer(new SvgWriter())
            ->data($google2fa->getQRCodeUrl('Officina piattaforma', $user->email, $user->twoFactorSecret()))
            ->size(240)->margin(8)->build()->getString();

        return view('platform.two-factor', ['setup' => true, 'qrSvg' => $qr, 'secret' => $user->twoFactorSecret()]);
    }

    public function verifyTwoFactor(Request $request, Google2FA $google2fa)
    {
        $user = $this->pending();
        if (! $user) {
            return redirect()->route('platform.login');
        }

        $code = preg_replace('/\s+/', '', (string) $request->input('code'));
        if (! $user->twoFactorSecret() || ! $google2fa->verifyKey($user->twoFactorSecret(), $code)) {
            return back()->withErrors(['code' => 'Codice non valido.']);
        }

        if (! $user->hasTwoFactorEnabled()) {
            $user->two_factor_confirmed_at = now();
        }
        $user->last_login_at = now();
        $user->save();

        $request->session()->regenerate();
        session()->forget('platform_pending_id');
        session(['platform_user_id' => $user->id]);
        Log::info('[platform] accesso console', ['email' => $user->email]);

        return redirect()->route('platform.tenants.index');
    }

    public function logout(Request $request)
    {
        session()->forget(['platform_user_id', 'platform_pending_id']);
        $request->session()->regenerate();

        return redirect()->route('platform.login');
    }

    private function pending(): ?PlatformUser
    {
        $id = session('platform_pending_id');

        return $id ? PlatformUser::where('id', $id)->where('is_active', true)->first() : null;
    }
}
