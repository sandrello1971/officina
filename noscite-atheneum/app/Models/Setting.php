<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    private const CACHE_PREFIX = 'atheneum.setting:';
    private const CACHE_TTL = 3600;

    /**
     * Lettura difensiva: se la tabella non esiste (es. durante migrazioni
     * iniziali) o la chiave manca, ritorna il default senza eccezioni.
     * Il caller — incluso il boot dell'app — non deve mai rompersi.
     */
    public static function resolve(string $key, $default = null)
    {
        return Cache::remember(
            self::CACHE_PREFIX . $key,
            self::CACHE_TTL,
            function () use ($key, $default) {
                try {
                    if (!Schema::hasTable('settings')) {
                        return $default;
                    }
                    $row = static::query()->find($key);
                    if (!$row) {
                        return $default;
                    }
                    return $row->value ?? $default;
                } catch (\Throwable $e) {
                    return $default;
                }
            }
        );
    }

    public static function put(string $key, $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
        Cache::forget(self::CACHE_PREFIX . $key);
    }

    public static function forget(string $key): void
    {
        static::where('key', $key)->delete();
        Cache::forget(self::CACHE_PREFIX . $key);
    }
}
