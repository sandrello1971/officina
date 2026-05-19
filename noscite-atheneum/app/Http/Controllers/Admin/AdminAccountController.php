<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AdminAccountController extends Controller
{
    public function index()
    {
        $admins = Admin::orderBy('name')->get();
        return view('admin.admins.index', compact('admins'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:admins,email',
            'password' => 'required|string|min:12',
        ]);

        $admin = Admin::create([
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => $data['password'],
            'is_active' => true,
        ]);

        Log::warning('[admin] account creato da UI', [
            'by'    => session('admin_email') ?? 'unknown',
            'email' => $admin->email,
        ]);

        return redirect()->route('admin.admins.index')
            ->with('success', "Admin {$admin->email} creato.");
    }

    public function update(Request $request, Admin $admin)
    {
        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email|unique:admins,email,' . $admin->id,
        ]);

        $admin->update($data);

        return back()->with('success', 'Admin aggiornato.');
    }

    public function password(Request $request, Admin $admin)
    {
        $data = $request->validate([
            'password'              => 'required|string|min:12|confirmed',
        ]);

        $admin->update(['password' => $data['password']]);

        Log::warning('[admin] password cambiata', [
            'by'     => session('admin_email') ?? 'unknown',
            'target' => $admin->email,
        ]);

        return back()->with('success', 'Password aggiornata.');
    }

    public function toggle(Request $request, Admin $admin)
    {
        $currentEmail = session('admin_email');
        $isSelf = $currentEmail && strtolower($admin->email) === strtolower((string) $currentEmail);
        $activeCount = Admin::where('is_active', true)->count();

        // Anti-lockout: l'unico admin attivo non può disattivare sé stesso.
        if ($admin->is_active && $isSelf && $activeCount <= 1) {
            return back()->with('error',
                'Non puoi disattivare l\'ultimo amministratore attivo. Crea o riattiva un altro admin prima.');
        }

        $admin->update(['is_active' => !$admin->is_active]);

        Log::warning('[admin] is_active toggled', [
            'by'        => session('admin_email') ?? 'unknown',
            'target'    => $admin->email,
            'new_state' => $admin->is_active ? 'active' : 'disabled',
        ]);

        return back()->with('success',
            $admin->is_active ? 'Admin riattivato.' : 'Admin disattivato.');
    }
}
