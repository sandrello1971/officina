<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function showLogin()
    {
        if (session('admin_logged_in')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($request->email === config('admin.email') &&
            Hash::check($request->password, config('admin.password_hash') ?? '')) {
            session(['admin_logged_in' => true, 'admin_email' => $request->email]);
            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors(['email' => 'Credenziali non valide.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        session()->forget(['admin_logged_in', 'admin_email']);
        return redirect()->route('admin.login');
    }
}
