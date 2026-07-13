<?php

namespace App\Services\Ai;

/**
 * Wrapper immutabile della risposta Anthropic. I call-site leggono text()/json()
 * e i token senza conoscere la forma grezza dell'API.
 */
class ClaudeResponse
{
    public function __construct(
        public readonly bool $ok,
        public readonly array $raw = [],
        public readonly ?int $status = null,
        public readonly ?string $error = null,
        // Dettaglio leggibile dall'API (body error.message, es. "credit balance is too low").
        public readonly ?string $errorDetail = null,
    ) {}

    public function failed(): bool
    {
        return !$this->ok;
    }

    /** Testo del primo blocco content (il caso d'uso comune). */
    public function text(): string
    {
        return $this->raw['content'][0]['text'] ?? '';
    }

    /**
     * Testo ripulito dai fence markdown e decodificato come JSON (i call-site
     * che si aspettano JSON strutturato). Null se non parsabile.
     */
    public function jsonFromText(): ?array
    {
        $t = trim(preg_replace('/```(?:json)?\s*|\s*```/i', '', $this->text()));
        $data = json_decode($t, true);

        return is_array($data) ? $data : null;
    }

    public function tokensIn(): int
    {
        return (int) ($this->raw['usage']['input_tokens'] ?? 0);
    }

    public function tokensOut(): int
    {
        return (int) ($this->raw['usage']['output_tokens'] ?? 0);
    }
}
