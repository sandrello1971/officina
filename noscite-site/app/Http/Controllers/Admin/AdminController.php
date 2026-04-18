<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class AdminController extends Controller
{
    public function loginForm(): View|RedirectResponse
    {
        if (session('admin_user')) {
            return redirect()->route('admin.dashboard');
        }
        return view('admin.auth.login');
    }

    public function redirectToMicrosoft()
    {
        return Socialite::driver('microsoft')
            ->redirectUrl(url('/nosciteadmin/auth/callback'))
            ->scopes(['openid', 'profile', 'email', 'User.Read'])
            ->with(['prompt' => 'select_account'])
            ->redirect();
    }

    public function microsoftCallback()
    {
        try {
            $msUser = Socialite::driver('microsoft')
                ->redirectUrl(url('/nosciteadmin/auth/callback'))
                ->user();

            $admins = config('intranet.admins', []);

            if (!in_array($msUser->getEmail(), $admins)) {
                return redirect()->route('admin.login')
                    ->with('error', 'Accesso non autorizzato. Solo ' . implode(', ', $admins) . ' può accedere.');
            }

            session([
                'admin_user' => [
                    'id' => $msUser->getId(),
                    'name' => $msUser->getName(),
                    'email' => $msUser->getEmail(),
                    'avatar' => $msUser->getAvatar(),
                ],
            ]);

            return redirect()->route('admin.dashboard');
        } catch (\Exception $e) {
            Log::error('Admin Microsoft OAuth error: ' . $e->getMessage());
            return redirect()->route('admin.login')
                ->with('error', 'Errore di autenticazione. Riprova.');
        }
    }

    public function logout(): RedirectResponse
    {
        session()->forget('admin_user');
        return redirect()->route('admin.login');
    }

    public function dashboard(): View
    {
        return view('admin.dashboard');
    }

    public function contacts(): View
    {
        $messages = ContactMessage::latest()->paginate(20);
        return view('admin.contacts', compact('messages'));
    }
}
