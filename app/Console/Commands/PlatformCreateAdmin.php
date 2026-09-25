<?php

namespace App\Console\Commands;

use App\Models\PlatformUser;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class PlatformCreateAdmin extends Command
{
    protected $signature = 'platform:create-admin {email} {--name=}';

    protected $description = 'Crea (o reimposta) un operatore della console di piattaforma con password temporanea.';

    public function handle(): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email non valida.');

            return self::FAILURE;
        }

        $password = Str::password(20);
        $user = PlatformUser::firstOrNew(['email' => $email]);
        $user->fill(['name' => $this->option('name') ?: ($user->name ?: $email), 'password' => $password, 'is_active' => true]);
        $user->setTwoFactorSecret(null);
        $user->two_factor_confirmed_at = null;
        $user->save();

        $this->info("✔ Operatore {$email} pronto. Password: {$password}");
        $this->line('  Al primo accesso configurerà il 2FA (obbligatorio): https://' . config('platform.domain') . '/login');

        return self::SUCCESS;
    }
}
