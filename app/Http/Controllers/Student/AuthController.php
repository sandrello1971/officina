<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (session('student_id')) {
            return redirect()->route('student.dashboard');
        }
        return view('student.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ], [
            'email.required' => 'Inserisci la tua email.',
            'password.required' => 'Inserisci la password.',
        ]);

        $student = Student::where('email', $request->email)
            ->where('is_active', true)
            ->first();

        if (!$student || !Hash::check($request->password, $student->password)) {
            return back()->withErrors(['email' => 'Credenziali non valide.'])->withInput();
        }

        session([
            'student_id' => $student->id,
            'student_name' => $student->name,
            'student_email' => $student->email,
        ]);

        $student->update(['last_login_at' => now()]);

        if ($student->must_change_password) {
            return redirect()->route('student.change-password');
        }

        return redirect()->route('student.dashboard');
    }

    public function logout(Request $request)
    {
        session()->forget(['student_id', 'student_name', 'student_email']);
        return redirect()->route('student.login');
    }

    public function showChangePassword()
    {
        return view('student.auth.change-password');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'password' => 'required|min:8|confirmed',
        ], [
            'password.required' => 'Inserisci la nuova password.',
            'password.min' => 'La password deve avere almeno 8 caratteri.',
            'password.confirmed' => 'Le password non coincidono.',
        ]);

        $student = Student::findOrFail(session('student_id'));
        $student->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        return redirect()->route('student.dashboard')
            ->with('success', 'Password aggiornata!');
    }
}
