<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SettingsController extends Controller
{
    private const MAIL_KEYS = [
        'mail_host', 'mail_port', 'mail_username',
        'mail_encryption', 'mail_from_address', 'mail_from_name',
    ];

    public function index()
    {
        $settings = [
            'instance_name'     => Setting::resolve('instance_name', 'Atheneum'),
            'mail_host'         => Setting::resolve('mail_host', ''),
            'mail_port'         => Setting::resolve('mail_port', ''),
            'mail_username'     => Setting::resolve('mail_username', ''),
            'mail_encryption'   => Setting::resolve('mail_encryption', 'tls'),
            'mail_from_address' => Setting::resolve('mail_from_address', ''),
            'mail_from_name'    => Setting::resolve('mail_from_name', ''),
            // password mai esposta in plain text
            'mail_password_set' => (bool) Setting::resolve('mail_password_encrypted'),
        ];

        return view('admin.settings.index', compact('settings'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'instance_name'      => 'nullable|string|max:120',
            'mail_host'          => 'nullable|string|max:255',
            'mail_port'          => 'nullable|integer|min:1|max:65535',
            'mail_username'      => 'nullable|string|max:255',
            'mail_password'      => 'nullable|string|max:500',
            'mail_encryption'    => 'nullable|in:tls,ssl,starttls,none',
            'mail_from_address'  => 'nullable|email',
            'mail_from_name'     => 'nullable|string|max:120',
            'clear_mail_password' => 'nullable|boolean',
        ]);

        Setting::put('instance_name', $data['instance_name'] ?? 'Atheneum');

        foreach (self::MAIL_KEYS as $key) {
            $value = $data[$key] ?? null;
            if ($key === 'mail_encryption' && $value === 'none') {
                $value = null;
            }
            Setting::put($key, $value);
        }

        // Password: tre stati
        // 1) checkbox "clear" → rimuovi
        // 2) nuova password fornita → cifra e salva
        // 3) campo vuoto e nessun clear → lascia invariato
        if (!empty($data['clear_mail_password'])) {
            Setting::forget('mail_password_encrypted');
            Log::warning('[settings] mail password rimossa', [
                'by' => session('admin_email') ?? 'unknown',
            ]);
        } elseif (!empty($data['mail_password'])) {
            Setting::put(
                'mail_password_encrypted',
                Crypt::encryptString($data['mail_password'])
            );
            Log::warning('[settings] mail password aggiornata', [
                'by' => session('admin_email') ?? 'unknown',
            ]);
        }

        return back()->with('success', 'Impostazioni salvate. L\'override mail sarà attivo dalla prossima request.');
    }

    public function testMail(Request $request)
    {
        $adminEmail = session('admin_email');
        if (!$adminEmail) {
            return back()->with('error', 'Email admin non disponibile in sessione.');
        }

        try {
            Mail::raw(
                "Questa è una mail di prova inviata da Atheneum a " . now()->format('d/m/Y H:i') . ".",
                function ($m) use ($adminEmail) {
                    $m->to($adminEmail)->subject('Atheneum — test mail');
                }
            );
            return back()->with('success', "Mail di prova inviata a {$adminEmail}. Verifica la casella.");
        } catch (\Throwable $e) {
            Log::error('[settings] test-mail fallito', [
                'by'    => $adminEmail,
                'error' => $e->getMessage(),
            ]);
            return back()->with('error', "Invio fallito: " . $e->getMessage());
        }
    }
}
