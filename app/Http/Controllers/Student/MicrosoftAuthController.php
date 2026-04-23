<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Laravel\Socialite\Facades\Socialite;

class MicrosoftAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('azure')
            ->redirectUrl(url('/auth/microsoft/callback'))
            ->scopes(['User.Read'])
            ->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $azureUser = Socialite::driver('azure')
                ->redirectUrl(url('/auth/microsoft/callback'))
                ->user();
        } catch (\Exception $e) {
            Log::error('Atheneum Microsoft OAuth error: ' . $e->getMessage());
            return redirect()->route('student.login')
                ->with('error', 'Autenticazione Microsoft non riuscita. Riprova o accedi con email.');
        }

        $email = strtolower($azureUser->getEmail());
        $isNoscite = str_ends_with($email, '@noscite.it');

        $student = Student::where('microsoft_id', $azureUser->getId())->first();

        if (!$student) {
            $student = Student::where('email', $email)->first();
        }

        if (!$student) {
            $student = new Student();
            $student->email = $email;
            $student->name = $azureUser->getName() ?: $email;
            $student->password = bcrypt(bin2hex(random_bytes(32)));
            if (Schema::hasColumn('students', 'must_change_password')) {
                $student->must_change_password = false;
            }
        }

        $student->microsoft_id = $azureUser->getId();

        if ($isNoscite) {
            $student->role = 'instructor';
            $student->auto_enroll_all_courses = true;
        } else {
            if (empty($student->role)) {
                $student->role = 'student';
            }
        }

        if (Schema::hasColumn('students', 'last_login_at')) {
            $student->last_login_at = now();
        }

        $student->save();

        session([
            'student_id'    => $student->id,
            'student_name'  => $student->name,
            'student_email' => $student->email,
        ]);

        return redirect()->route('student.dashboard');
    }
}
