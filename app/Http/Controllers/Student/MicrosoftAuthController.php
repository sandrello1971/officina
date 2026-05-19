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

        $rawEmail = $azureUser->getEmail();
        $azureId = $azureUser->getId();

        // Protocol failure: Azure non ha restituito email. Distinto da fallimenti di
        // authorization perché è un problema tecnico, non di policy.
        if (empty($rawEmail)) {
            Log::warning('SSO: email Azure mancante', ['azure_id' => $azureId]);
            return redirect()->route('student.login')
                ->with('error', 'Autenticazione non riuscita. Contatta l\'amministratore.');
        }

        $email = strtolower($rawEmail);
        $isNoscite = str_ends_with($email, '@noscite.it');

        // Tutti i fallimenti di authorization restituiscono lo stesso messaggio utente:
        // l'attaccante non deve poter distinguere account inesistenti, non abilitati,
        // o già linkati ad altro Azure ID. La distinzione resta nei log strutturati.
        $denyMessage = 'Account non abilitato per accesso Microsoft. Contatta l\'amministratore.';

        // 1) Lookup primario: già bound a questo microsoft_id → login diretto.
        $student = Student::where('microsoft_id', $azureId)->first();

        if (!$student) {
            // 2) Fallback per email.
            $student = Student::where('email', $email)->first();

            if ($student) {
                // 3) Mismatch: l'email matcha uno Student già bound a un altro Azure ID.
                //    Possibile takeover via riassegnazione email tenant Azure → blocco.
                if ($student->microsoft_id !== null && $student->microsoft_id !== $azureId) {
                    Log::warning('SSO: microsoft_id mismatch', [
                        'email' => $email,
                        'attempted_microsoft_id' => $azureId,
                        'existing_microsoft_id' => $student->microsoft_id,
                    ]);
                    return redirect()->route('student.login')->with('error', $denyMessage);
                }

                // 4) Primo SSO su Student preesistente con microsoft_id null:
                //    bind ammesso solo per email whitelist @noscite.it.
                if ($student->microsoft_id === null && !$isNoscite) {
                    Log::warning('SSO: bind blocked, account not whitelisted', [
                        'email' => $email,
                        'student_id' => $student->id,
                        'attempted_microsoft_id' => $azureId,
                    ]);
                    return redirect()->route('student.login')->with('error', $denyMessage);
                }
            } else {
                // 5) Email mai vista: signup automatico solo per @noscite.it (instructor).
                if (!$isNoscite) {
                    Log::warning('SSO: signup blocked, email not registered', [
                        'email' => $email,
                        'attempted_microsoft_id' => $azureId,
                    ]);
                    return redirect()->route('student.login')->with('error', $denyMessage);
                }

                $student = new Student();
                $student->email = $email;
                $student->name = $azureUser->getName() ?: $email;
                $student->password = bcrypt(bin2hex(random_bytes(32)));
                if (Schema::hasColumn('students', 'must_change_password')) {
                    $student->must_change_password = false;
                }
            }
        }

        // Da qui: $student è valido e autorizzato a procedere.
        $student->microsoft_id = $azureId;

        if ($isNoscite) {
            $student->role = 'instructor';
            // auto_enroll_all_courses NON viene più impostato qui:
            // è un privilegio riservato all'amministratore, gestito
            // manualmente e persistito a DB. Vedi config('atheneum.admins').
        } elseif (empty($student->role)) {
            $student->role = 'student';
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
