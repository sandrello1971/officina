<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function loginForm(): View|RedirectResponse
    {
        if (Auth::check() && Auth::user()->hasRole('admin')) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.noscite-admin-login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            if (!Auth::user()->hasRole('admin')) {
                Auth::logout();
                return back()->withErrors(['email' => 'Non hai i permessi per accedere all\'area admin.']);
            }

            return redirect()->intended(route('admin.dashboard'));
        }

        return back()->withErrors(['email' => 'Credenziali non valide.'])->onlyInput('email');
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
