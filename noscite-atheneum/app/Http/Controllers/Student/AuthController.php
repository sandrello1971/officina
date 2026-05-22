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
            \Illuminate\Support\Facades\Log::warning('[student] login failed', [
                'email_attempted' => $request->input('email'),
                'ip' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 200),
            ]);
            return back()->withErrors(['email' => 'Credenziali non valide.'])->withInput();
        }

        session([
            'student_id' => $student->id,
            'student_name' => $student->name,
            'student_email' => $student->email,
        ]);

        $student->update(['last_login_at' => now()]);

        \Illuminate\Support\Facades\Log::info('[student] login success', [
            'student_id' => $student->id,
            'email' => $student->email,
            'ip' => $request->ip(),
            'user_agent' => substr($request->userAgent() ?? '', 0, 200),
        ]);

        if ($student->must_change_password) {
            return redirect()->route('student.change-password');
        }

        return redirect()->route('student.dashboard');
    }

    public function logout(Request $request)
    {
        $studentId = session('student_id');
        $studentEmail = session('student_email');
        session()->forget(['student_id', 'student_name', 'student_email']);
        if ($studentId) {
            \Illuminate\Support\Facades\Log::info('[student] logout', [
                'student_id' => $studentId,
                'email' => $studentEmail,
                'ip' => $request->ip(),
            ]);
        }
        return redirect()->route('student.login');
    }

    public function showChangePassword()
    {
        return view('student.auth.change-password');
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ], [
            'password.required' => 'Inserisci la nuova password.',
            'password.confirmed' => 'Le password non coincidono.',
        ]);

        $student = Student::findOrFail(session('student_id'));
        $student->update([
            'password' => Hash::make($request->password),
            'must_change_password' => false,
        ]);

        \Illuminate\Support\Facades\Log::info('[student] password changed', [
            'student_id' => $student->id,
            'email' => $student->email,
            'ip' => $request->ip(),
        ]);

        return redirect()->route('student.dashboard')
            ->with('success', 'Password aggiornata!');
    }
}
