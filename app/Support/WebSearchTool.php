<?php

namespace App\Support;

/**
 * Definizione del tool server-side `web_search`, scelta IN BASE AL MODELLO.
 *
 * Esistono due versioni e non sono intercambiabili:
 *  - `_20260209` (dynamic filtering): il filtraggio dei risultati avviene PRIMA che
 *    finiscano nel contesto → molti meno token di input a parità di ricerche. È la
 *    versione da preferire, ma richiede Opus 4.6+ / Sonnet 4.6+.
 *  - `_20250305` (base): accettata da tutti i modelli, inclusi Sonnet 4.5.
 *
 * Inviare la `_20260209` a un modello che non la supporta è un HTTP 400: per questo la
 * scelta è centralizzata qui e non hardcodata nei singoli servizi, che usano modelli
 * diversi e configurabili da .env (freshness Opus, news Sonnet 4.5, gap Sonnet 4.6).
 */
final class WebSearchTool
{
    private const DYNAMIC = 'web_search_20260209';
    private const BASIC = 'web_search_20250305';

    /** Tipo di tool compatibile col modello indicato. */
    public static function typeFor(?string $model): string
    {
        $model = (string) $model;
        foreach ((array) config('services.anthropic.dynamic_web_search_models', []) as $prefix) {
            if ($prefix !== '' && str_starts_with($model, (string) $prefix)) {
                return self::DYNAMIC;
            }
        }

        return self::BASIC;
    }

    /**
     * Blocco `tools` pronto da inserire nel payload Anthropic.
     *
     * @return array{type:string, name:string, max_uses:int}
     */
    public static function definition(?string $model, int $maxUses): array
    {
        return [
            'type' => self::typeFor($model),
            'name' => 'web_search',
            'max_uses' => max(1, $maxUses),
        ];
    }
}
