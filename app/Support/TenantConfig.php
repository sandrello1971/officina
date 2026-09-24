<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\Tenant;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;

/**
 * Configurazione che dipende dall'ente corrente: SMTP, chiavi API (Anthropic,
 * Brevo, ElevenLabs), nome istanza, host per la generazione degli URL.
 *
 * Viene applicata al boot e RI-applicata a ogni cambio di contesto
 * (TenancyBootstrapped / RevertedToCentralContext), ripartendo sempre dai
 * valori .env salvati al primo giro: senza il reset, la chiave o l'SMTP di un
 * ente resterebbero attivi per l'ente successivo servito dallo stesso worker.
 */
class TenantConfig
{
    /** Chiave nel container dei valori .env delle config sovrascritte. */
    private const BASELINE = 'tenant-config.baseline';

    private const PATHS = [
        'mail.mailers.smtp.host', 'mail.mailers.smtp.port', 'mail.mailers.smtp.username',
        'mail.mailers.smtp.encryption', 'mail.mailers.smtp.password',
        'mail.from.address', 'mail.from.name',
        'services.anthropic.key', 'services.anthropic.key_source',
        'services.brevo.key', 'mail.mailers.brevo.key', 'services.elevenlabs.key',
        'filesystems.disks.public.url',
    ];

    public static function apply(): void
    {
        if (! app()->bound(self::BASELINE)) {
            self::rebaseline();
        }
        Config::set(self::baseline());

        $tenant = tenant();

        self::applyMail($tenant);
        self::applyApiKeys($tenant);

        // Disco public degli enti secondari servito da TenantMediaController:
        // public/storage è il symlink ai file dell'ente primario.
        if ($tenant && ! $tenant->isPrimary()) {
            Config::set('filesystems.disks.public.url', '/media');
        }
        \Illuminate\Support\Facades\Storage::forgetDisk('public');

        View::share('instanceName', Setting::resolve('instance_name', 'Officina'));
        URL::defaults(['tenant_host' => $tenant?->base_host ?? config('domains.base')]);
    }

    /** Fotografa i valori correnti come base .env (al boot; nei test dopo aver cambiato la config). */
    public static function rebaseline(): void
    {
        app()->instance(self::BASELINE, collect(self::PATHS)->mapWithKeys(fn ($p) => [$p => config($p)])->all());
    }

    private static function baseline(): array
    {
        return app(self::BASELINE);
    }

    private static function applyMail(?Tenant $tenant): void
    {
        $overrides = [];

        if ($host = Setting::resolve('mail_host')) {
            $overrides = [
                'mail.mailers.smtp.host' => $host,
                'mail.mailers.smtp.port' => Setting::resolve('mail_port', 587),
                'mail.mailers.smtp.username' => Setting::resolve('mail_username'),
                'mail.mailers.smtp.encryption' => Setting::resolve('mail_encryption', 'tls') ?: null,
            ];
            if ($password = self::decrypt(Setting::resolve('mail_password_encrypted'))) {
                $overrides['mail.mailers.smtp.password'] = $password;
            }
        }

        if ($fromAddress = Setting::resolve('mail_from_address')) {
            $overrides['mail.from.address'] = $fromAddress;
        }
        // Un ente secondario non deve mai firmare le mail col nome della piattaforma.
        $fromName = Setting::resolve('mail_from_name')
            ?: ($tenant && ! $tenant->isPrimary() ? Setting::resolve('instance_name', $tenant->name) : null);
        if ($fromName) {
            $overrides['mail.from.name'] = $fromName;
        }

        if ($overrides) {
            Config::set($overrides);
        }
    }

    private static function applyApiKeys(?Tenant $tenant): void
    {
        $brevo = self::decrypt(Setting::resolve('api_key_brevo_encrypted'));
        if ($brevo) {
            Config::set(['services.brevo.key' => $brevo, 'mail.mailers.brevo.key' => $brevo]);
        }
        if ($eleven = self::decrypt(Setting::resolve('api_key_elevenlabs_encrypted'))) {
            Config::set('services.elevenlabs.key', $eleven);
        }

        // Anthropic: la chiave effettiva dipende dalla modalità dell'ente. Senza
        // tenant (CLI legacy, vetrina) vale il comportamento storico = 'both'.
        $mode = $tenant?->ai_key_mode ?? Tenant::AI_KEY_BOTH;
        $own = self::decrypt(Setting::resolve('api_key_anthropic_encrypted'));
        $platform = (string) (self::baseline()['services.anthropic.key'] ?? '');

        [$key, $source] = match ($mode) {
            Tenant::AI_KEY_TENANT => [$own ?: '', 'tenant'],
            Tenant::AI_KEY_PLATFORM => [$platform, 'platform'],
            default => $own ? [$own, 'tenant'] : [$platform, 'platform'],
        };

        // '' e non null: i call-site fanno `config(...) ?? env('ANTHROPIC_API_KEY')`
        // e con null ricadrebbero sulla chiave di piattaforma.
        Config::set(['services.anthropic.key' => $key, 'services.anthropic.key_source' => $source]);
    }

    private static function decrypt(?string $encrypted): ?string
    {
        if (! $encrypted) {
            return null;
        }
        try {
            return Crypt::decryptString($encrypted) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
