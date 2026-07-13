<?php

namespace App\Services\Ai;

use App\Models\AiUsage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client Anthropic unico per tutta la piattaforma. Centralizza URL, versione,
 * modello di default, timeout, retry (429/5xx/overloaded) e — cruciale per il
 * pricing multi-tenant — il METERING dei token su ai_usage.
 *
 * Uso:
 *   $res = $claude->messages([
 *       'system'     => $system,
 *       'messages'   => [['role' => 'user', 'content' => $prompt]],
 *       'max_tokens' => 4096,
 *       // 'model'   => 'claude-opus-4-8',   // opzionale, override del default
 *   ], context: ['feature' => 'quiz.generate', 'course_id' => $id]);
 *
 *   if ($res->failed()) { ... }
 *   $text = $res->text();  // oppure $res->jsonFromText()
 */
class ClaudeClient
{
    private const RETRY_STATUSES = [429, 500, 502, 503, 529];

    /**
     * @param  array  $params   payload Anthropic (model opzionale → default da config)
     * @param  array  $context  ['feature','school_id','course_id','actor_type','actor_id','meta']
     */
    public function messages(array $params, array $context = []): ClaudeResponse
    {
        $cfg = config('services.anthropic');
        $model = $params['model'] ?? $cfg['model'];
        $params['model'] = $model;
        $params['max_tokens'] ??= 4096;

        $maxRetries = (int) ($cfg['max_retries'] ?? 2);
        $attempt = 0;
        $response = null;
        $lastError = null;

        while (true) {
            try {
                $response = Http::withHeaders([
                    'x-api-key' => $cfg['key'] ?? env('ANTHROPIC_API_KEY'),
                    'anthropic-version' => $cfg['version'] ?? '2023-06-01',
                    'content-type' => 'application/json',
                ])->timeout((int) ($cfg['timeout'] ?? 120))
                  ->post($cfg['base_url'] ?? 'https://api.anthropic.com/v1/messages', $params);

                if ($response->successful()) {
                    break;
                }

                $lastError = "HTTP {$response->status()}";
                if (!in_array($response->status(), self::RETRY_STATUSES, true) || $attempt >= $maxRetries) {
                    break;
                }
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                if ($attempt >= $maxRetries) {
                    $this->meter($model, 0, 0, 'error', $context, $lastError);
                    Log::error('ClaudeClient exception', ['feature' => $context['feature'] ?? '?', 'error' => $lastError]);

                    return new ClaudeResponse(ok: false, error: $lastError);
                }
            }

            $attempt++;
            $this->backoff($attempt);
        }

        if ($response === null || !$response->successful()) {
            // Dettaglio leggibile dal body (error.message) per messaggi utili in UI/failure_reason.
            $detail = null;
            if ($response !== null) {
                $d = $response->json('error.message');
                $detail = (is_string($d) && trim($d) !== '') ? trim($d) : null;
            }
            $this->meter($model, 0, 0, 'error', $context, $detail ?? $lastError);
            Log::warning('ClaudeClient call failed', ['feature' => $context['feature'] ?? '?', 'error' => $detail ?? $lastError]);

            return new ClaudeResponse(ok: false, status: $response?->status(), error: $lastError, errorDetail: $detail);
        }

        $raw = $response->json();
        $res = new ClaudeResponse(ok: true, raw: is_array($raw) ? $raw : [], status: $response->status());
        $this->meter($model, $res->tokensIn(), $res->tokensOut(), 'ok', $context);

        return $res;
    }

    /**
     * Variante STREAMING (SSE) per le chiamate lunghe (es. ingestion): accumula il
     * testo dai text_delta, cattura i token dagli eventi usage e li mette a metering.
     * Preserva le eccezioni storiche (troncamento max_tokens / errore streaming).
     */
    public function stream(array $params, array $context = []): string
    {
        $cfg = config('services.anthropic');
        $model = $params['model'] ?? $cfg['model'];
        $params['model'] = $model;
        $params['stream'] = true;
        $params['max_tokens'] ??= 4096;

        $response = Http::withHeaders([
            'x-api-key' => $cfg['key'] ?? env('ANTHROPIC_API_KEY'),
            'anthropic-version' => $cfg['version'] ?? '2023-06-01',
            'content-type' => 'application/json',
        ])->withOptions(['stream' => true])
          ->connectTimeout(30)
          ->timeout((int) ($cfg['stream_timeout'] ?? 600))
          ->post($cfg['base_url'] ?? 'https://api.anthropic.com/v1/messages', $params);

        if ($response->status() >= 400) {
            $body = (string) $response->toPsrResponse()->getBody();
            $j = json_decode($body, true);
            $detail = is_array($j) && is_string($j['error']['message'] ?? null) ? $j['error']['message'] : null;
            $this->meter($model, 0, 0, 'error', $context, $detail ?? ('HTTP ' . $response->status()));
            Log::error('ClaudeClient stream failed', ['feature' => $context['feature'] ?? '?', 'status' => $response->status()]);
            throw new \RuntimeException('Claude API error (HTTP ' . $response->status() . ').');
        }

        [$text, $in, $out] = $this->readSse($response->toPsrResponse()->getBody());
        $this->meter($model, $in, $out, 'ok', $context);

        return $text;
    }

    /**
     * Parser SSE Anthropic: ritorna [testo, input_tokens, output_tokens].
     * @return array{0:string,1:int,2:int}
     */
    private function readSse(\Psr\Http\Message\StreamInterface $stream): array
    {
        $accumulated = '';
        $buffer = '';
        $in = 0;
        $out = 0;

        while (!$stream->eof()) {
            $chunk = $stream->read(8192);
            if ($chunk === '') {
                continue;
            }
            $buffer .= $chunk;

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $rawEvent = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                $eventName = null;
                $dataLines = [];
                foreach (explode("\n", $rawEvent) as $line) {
                    if (str_starts_with($line, 'event: ')) {
                        $eventName = substr($line, 7);
                    } elseif (str_starts_with($line, 'data: ')) {
                        $dataLines[] = substr($line, 6);
                    }
                }
                if (empty($dataLines)) {
                    continue;
                }

                $decoded = json_decode(implode("\n", $dataLines), true);
                if (!is_array($decoded)) {
                    continue;
                }

                if ($eventName === 'content_block_delta') {
                    $delta = $decoded['delta'] ?? [];
                    if (($delta['type'] ?? '') === 'text_delta') {
                        $accumulated .= $delta['text'] ?? '';
                    }
                } elseif ($eventName === 'message_start') {
                    $in = (int) ($decoded['message']['usage']['input_tokens'] ?? 0);
                } elseif ($eventName === 'message_delta') {
                    $out = (int) ($decoded['usage']['output_tokens'] ?? $out);
                    if (($decoded['delta']['stop_reason'] ?? null) === 'max_tokens') {
                        throw new \RuntimeException(
                            'Risposta Claude troncata: limite max_tokens raggiunto. Il documento potrebbe essere troppo lungo per essere processato in una singola chiamata.'
                        );
                    }
                } elseif ($eventName === 'error') {
                    $msg = $decoded['error']['message'] ?? 'Unknown streaming error';
                    throw new \RuntimeException('Claude streaming error: ' . $msg);
                }
            }
        }

        return [$accumulated, $in, $out];
    }

    /** Backoff esponenziale limitato (i call-site sono per lo più in job). */
    private function backoff(int $attempt): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        $seconds = min(2 ** $attempt, 8);
        usleep($seconds * 1_000_000);
    }

    /** Scrive il metering. Non deve MAI far fallire la chiamata AI. */
    private function meter(string $model, int $in, int $out, string $status, array $context, ?string $error = null): void
    {
        try {
            AiUsage::create([
                'feature'    => $context['feature'] ?? 'unknown',
                'model'      => $model,
                'tokens_in'  => $in,
                'tokens_out' => $out,
                'cost_usd'   => $this->cost($model, $in, $out),
                'status'     => $status,
                'error'      => $error ? mb_substr($error, 0, 255) : null,
                'school_id'  => $context['school_id'] ?? null,
                'course_id'  => $context['course_id'] ?? null,
                'actor_type' => $context['actor_type'] ?? null,
                'actor_id'   => $context['actor_id'] ?? null,
                'meta'       => $context['meta'] ?? null,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('ClaudeClient metering failed: ' . $e->getMessage());
        }
    }

    /** Costo stimato USD dal listino config; null se il modello non è mappato. */
    private function cost(string $model, int $in, int $out): ?float
    {
        $price = config("services.anthropic.prices.$model");
        if (!$price) {
            return null;
        }

        return round($in / 1_000_000 * $price['in'] + $out / 1_000_000 * $price['out'], 6);
    }
}
