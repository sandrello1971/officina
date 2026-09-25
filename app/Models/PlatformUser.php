<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/** Operatore della console di piattaforma (DB central). 2FA obbligatorio. */
class PlatformUser extends Model
{
    use CentralConnection;

    protected $fillable = ['name', 'email', 'password', 'is_active', 'last_login_at'];

    protected $hidden = ['password', 'two_factor_secret'];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
        'two_factor_confirmed_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = strtolower(trim((string) $value));
    }

    public function hasTwoFactorEnabled(): bool
    {
        return ! empty($this->attributes['two_factor_secret'] ?? null) && $this->two_factor_confirmed_at !== null;
    }

    public function twoFactorSecret(): ?string
    {
        $raw = $this->attributes['two_factor_secret'] ?? null;
        try {
            return $raw ? Crypt::decryptString($raw) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function setTwoFactorSecret(?string $secret): void
    {
        $this->attributes['two_factor_secret'] = $secret ? Crypt::encryptString($secret) : null;
    }
}
