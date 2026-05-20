<?php

namespace App\Providers;

use App\Models\Certificate;
use App\Models\Setting;
use App\Models\Student;
use App\Observers\CertificateObserver;
use App\Support\ExamState;
use App\Support\StudentCourseAccess;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->applyMailSettingsOverride();

        Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
            $event->extendSocialite('azure', \SocialiteProviders\Azure\Provider::class);
        });

        Certificate::observe(CertificateObserver::class);

        // Rate limiter per la verifica pubblica del certificato. Per-IP esplicito,
        // così il budget non è condiviso tra utenti diversi dietro la stessa rotta.
        RateLimiter::for('certificate-verify', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        $this->shareInstanceName();

        View::composer('layouts.student', function ($view) {
            $studentId = session('student_id');
            $student = $studentId ? Student::find($studentId) : null;

            $sidebarCourses = $student
                ? app(StudentCourseAccess::class)->navigableCourses($student)
                : collect();

            $examLock = $studentId
                ? app(ExamState::class)->hasActiveExam($studentId)
                : false;

            $view->with([
                'sidebarStudent' => $student,
                'sidebarCourses' => $sidebarCourses,
                'examLock'       => $examLock,
            ]);
        });
    }

    /**
     * Override runtime della config mail dal settings store, SOLO se le
     * chiavi sono valorizzate. Difensivo: chiavi vuote → nessuna modifica
     * → si continua a usare .env (no regressione produzione).
     */
    private function applyMailSettingsOverride(): void
    {
        $host = Setting::resolve('mail_host');
        if (!$host) {
            return; // se host non c'è, non tocchiamo nulla
        }

        $overrides = [
            'mail.mailers.smtp.host'       => $host,
            'mail.mailers.smtp.port'       => Setting::resolve('mail_port', 587),
            'mail.mailers.smtp.username'   => Setting::resolve('mail_username'),
            'mail.mailers.smtp.encryption' => Setting::resolve('mail_encryption', 'tls') ?: null,
        ];

        $fromAddress = Setting::resolve('mail_from_address');
        $fromName    = Setting::resolve('mail_from_name');
        if ($fromAddress) $overrides['mail.from.address'] = $fromAddress;
        if ($fromName)    $overrides['mail.from.name']    = $fromName;

        $encPwd = Setting::resolve('mail_password_encrypted');
        if ($encPwd) {
            try {
                $overrides['mail.mailers.smtp.password'] = Crypt::decryptString($encPwd);
            } catch (\Throwable $e) {
                // Password cifrata illeggibile → preferisco NON sovrascrivere
                // password .env piuttosto che disabilitare la mail in prod.
            }
        }

        Config::set($overrides);
    }

    private function shareInstanceName(): void
    {
        $instanceName = Setting::resolve('instance_name', 'Atheneum');
        View::share('instanceName', $instanceName);
    }
}
