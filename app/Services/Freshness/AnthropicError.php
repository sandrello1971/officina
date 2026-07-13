<?php

namespace App\Services\Freshness;

use Illuminate\Http\Client\Response;

/**
 * Costruisce un messaggio d'errore LEGGIBILE da una risposta non-ok dell'API Anthropic,
 * includendo il corpo (`error.message`) — es. "Your credit balance is too low…".
 * Senza questo, l'agente registrava in `failure_reason` solo "HTTP 400", inutile a schermo.
 */
class AnthropicError
{
    public static function message(Response $response, string $phase): string
    {
        return self::messageFrom($response->status(), $response->json('error.message'), $phase);
    }

    /**
     * Variante disaccoppiata dalla Response, per i call-site migrati su ClaudeClient
     * (che espone status + errorDetail invece dell'oggetto Response). Stesso formato.
     */
    public static function messageFrom(?int $status, ?string $detail, string $phase): string
    {
        $base = "Anthropic API errore {$phase}: HTTP " . ($status ?? '?');

        if (is_string($detail) && trim($detail) !== '') {
            return $base . ' — ' . trim($detail);
        }

        return $base;
    }
}
