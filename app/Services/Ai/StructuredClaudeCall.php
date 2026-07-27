<?php

namespace App\Services\Ai;

use App\Services\Freshness\AnthropicError;
use RuntimeException;

/**
 * Output strutturato via tool-use: forza il modello a compilare uno schema JSON,
 * eliminando ALLA RADICE il JSON malformato da serializzazione manuale — in particolare
 * le citazioni VERBATIM che contengono virgolette doppie non-escapate (es. il testo
 * «le cosiddette "nudifier"» copiato dentro un valore JSON, che chiudeva la stringa in
 * anticipo → Syntax error). Con tool_use l'input arriva già come array associativo:
 * niente parsing di testo/fence né escaping a mano.
 *
 * Mirror dell'idioma di Schola\LessonPresentationService::callClaudeStructured. Ritenta
 * su output non conforme o troncato (la generazione LLM è non deterministica) e, cosa
 * importante, DISTINGUE il troncamento reale (stop_reason=max_tokens) dal "non JSON":
 * il troncamento non si maschera più da errore di parsing.
 *
 * Richiede la proprietà `$this->claude` (App\Services\Ai\ClaudeClient) sul servizio host.
 *
 * Vive in `Services\Ai` (accanto a ClaudeClient) perché è l'idioma trasversale della
 * piattaforma per ottenere JSON affidabile: lo usano sia il freshness sia Schola.
 */
trait StructuredClaudeCall
{
    /**
     * @param  array<string,mixed>  $schema  input_schema JSON del tool
     * @return array<string,mixed>  l'input del blocco tool_use (già decodificato)
     */
    private function callClaudeStructured(
        string $model,
        int $maxTokens,
        string $system,
        string $userMessage,
        string $toolName,
        string $toolDescription,
        array $schema,
        string $feature,
        ?string $errorLabel = null,
        int $attempts = 3,
        // Token dell'ultimo tentativo riuscito, per chi li espone in generation_meta.
        ?array &$usage = null,
    ): array {
        // Etichetta leggibile per i messaggi d'errore (es. "Fase 1"); `feature` resta
        // l'identificatore tecnico usato per il metering.
        $errorLabel ??= $feature;
        $lastError = 'nessun tentativo eseguito';

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $res = $this->claude->messages([
                'model' => $model,
                'max_tokens' => $maxTokens,
                'system' => $system,
                'tools' => [[
                    'name' => $toolName,
                    'description' => $toolDescription,
                    'input_schema' => $schema,
                ]],
                'tool_choice' => ['type' => 'tool', 'name' => $toolName],
                'messages' => [['role' => 'user', 'content' => $userMessage]],
            ], ['feature' => $feature]);

            if ($res->failed()) {
                $lastError = AnthropicError::messageFrom($res->status, $res->errorDetail, $errorLabel);
                continue;
            }

            $stop = (string) ($res->raw['stop_reason'] ?? '');
            $input = $this->extractToolInput($res->raw['content'] ?? [], $toolName);

            // Troncamento (max_tokens) → il tool_use può essere incompleto: scartalo e ritenta.
            if (is_array($input) && $stop !== 'max_tokens') {
                $usage = ['in' => $res->tokensIn(), 'out' => $res->tokensOut()];

                return $input;
            }

            $lastError = $input === null
                ? "tool '{$toolName}' non invocato (stop={$stop})"
                : 'output troncato (max_tokens)';
        }

        throw new RuntimeException("Output strutturato {$errorLabel} non conforme dopo {$attempts} tentativi: {$lastError}.");
    }

    /**
     * Estrae l'input del primo blocco tool_use col nome atteso.
     *
     * @param  mixed  $content  blocchi content della risposta
     * @return array<string,mixed>|null
     */
    private function extractToolInput(mixed $content, string $toolName): ?array
    {
        if (!is_array($content)) {
            return null;
        }
        foreach ($content as $block) {
            if (is_array($block)
                && ($block['type'] ?? null) === 'tool_use'
                && ($block['name'] ?? null) === $toolName
                && is_array($block['input'] ?? null)) {
                return $block['input'];
            }
        }

        return null;
    }
}
