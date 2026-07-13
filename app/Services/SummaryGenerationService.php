<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Genera testi didattici lavorati (riassunto e scaletta/outline) a partire dal
 * testo estratto di un teaching_document Schola, via Claude API.
 *
 * Stesso pattern di MindMapGenerationService/ConceptMapGenerationService:
 * Http::post su /v1/messages, modello claude-sonnet-4-5, RuntimeException + Log,
 * chiave da config/services.php. Registro adatto a studenti di scuola superiore.
 */
class SummaryGenerationService
{
    public function __construct(private \App\Services\Ai\ClaudeClient $claude) {}

    private const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';
    private const CLAUDE_MODEL = 'claude-sonnet-4-5';
    private const TEMPERATURE = 0.3;
    private const MAX_CONTENT_CHARS = 30000;
    private const PROMPT_VERSION = 'summary-2026-06';

    // Livelli di riassunto → (max_tokens, descrizione per il prompt).
    private const LEVELS = [
        'breve'    => [1200, 'un riassunto BREVE: 8-12 frasi, solo i concetti chiave essenziali.'],
        'medio'    => [2500, 'un riassunto di lunghezza MEDIA: i concetti principali con i dettagli di supporto, organizzato in paragrafi e sezioni.'],
        'dispensa' => [4000, 'una DISPENSA di studio completa: spiegazione approfondita, esempi, definizioni, suddivisa in sezioni con titoli, adatta a ripassare per un\'interrogazione o una verifica.'],
    ];

    /**
     * Genera un riassunto in markdown.
     *
     * @param  string  $sourceText    testo estratto dal materiale
     * @param  string  $contextLabel  titolo del materiale/documento
     * @param  array   $options       ['level' => 'breve'|'medio'|'dispensa', 'log_context' => array]
     * @return array{content: string, meta: array}
     */
    public function generateFromText(string $sourceText, string $contextLabel, array $options = []): array
    {
        $level = $options['level'] ?? 'medio';
        if (!isset(self::LEVELS[$level])) {
            $level = 'medio';
        }
        [$maxTokens, $levelHint] = self::LEVELS[$level];

        $systemPrompt = $this->buildSummarySystemPrompt($levelHint);
        $userMessage = $this->buildUserMessage($contextLabel, $sourceText, 'Scrivi il riassunto in italiano, in formato Markdown.');

        return $this->call($systemPrompt, $userMessage, $maxTokens, array_merge(
            $options['log_context'] ?? [],
            ['kind' => 'summary', 'level' => $level]
        ), ['level' => $level]);
    }

    /**
     * Genera una scaletta/outline gerarchica in markdown (indice ragionato del
     * materiale): titoli e punti, utile per orientarsi e per ripassare.
     *
     * @return array{content: string, meta: array}
     */
    public function generateOutline(string $sourceText, string $contextLabel, array $options = []): array
    {
        $systemPrompt = $this->buildOutlineSystemPrompt();
        $userMessage = $this->buildUserMessage($contextLabel, $sourceText, 'Genera la scaletta in italiano, in formato Markdown.');

        return $this->call($systemPrompt, $userMessage, 2000, array_merge(
            $options['log_context'] ?? [],
            ['kind' => 'outline']
        ), []);
    }

    /**
     * Esegue la chiamata Claude condivisa da riassunto e outline.
     *
     * @return array{content: string, meta: array}
     */
    private function call(string $systemPrompt, string $userMessage, int $maxTokens, array $logContext, array $extraMeta): array
    {
        $apiKey = config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            throw new RuntimeException('Anthropic API key non configurata (config/services.php o env ANTHROPIC_API_KEY)');
        }

        Log::info('Summary generation request', $logContext);

        $res = $this->claude->messages([
            'max_tokens' => $maxTokens,
            'temperature' => self::TEMPERATURE,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ], ['feature' => 'summary.generate']);

        if ($res->failed()) {
            Log::error('Summary Claude API failed', array_merge($logContext, ['error' => $res->error]));
            throw new RuntimeException('Errore Claude API: ' . ($res->status ?? $res->error));
        }

        $markdown = $res->text();

        if (empty(trim($markdown))) {
            throw new RuntimeException('Risposta Claude vuota');
        }

        // Cleanup di eventuali fence ```markdown ... ```
        $markdown = preg_replace('/^```(?:markdown)?\s*\n/', '', trim($markdown));
        $markdown = preg_replace('/\n```\s*$/', '', $markdown);
        $markdown = trim($markdown);

        Log::info('Summary generated', array_merge($logContext, [
            'output_chars' => mb_strlen($markdown),
            'usage' => $res->raw['usage'] ?? null,
        ]));

        return [
            'content' => $markdown,
            'meta' => array_merge([
                'model' => config('services.anthropic.model'),
                'tokens_in' => $res->tokensIn(),
                'tokens_out' => $res->tokensOut(),
                'prompt_version' => self::PROMPT_VERSION,
            ], $extraMeta),
        ];
    }

    private function buildSummarySystemPrompt(string $levelHint): string
    {
        return <<<TXT
Sei un docente di scuola superiore esperto nel preparare materiali di studio.

Il tuo compito è produrre {$levelHint}

REGOLE OBBLIGATORIE:
1. Registro adatto a studenti di scuola superiore: chiaro, preciso, senza gergo inutile.
2. Riassumi SOLO ciò che è presente nel testo fornito. NON aggiungere informazioni esterne.
3. Usa Markdown: titoli con ##, elenchi puntati dove utile, **grassetto** per i termini chiave.
4. Mantieni l'ordine logico degli argomenti.
5. Risposta SOLO il markdown del riassunto: NIENTE preamble, NIENTE commenti, NIENTE code fence.
TXT;
    }

    private function buildOutlineSystemPrompt(): string
    {
        return <<<TXT
Sei un docente di scuola superiore esperto nel preparare materiali di studio.

Il tuo compito è produrre una SCALETTA (indice ragionato) del materiale: la struttura
degli argomenti trattati, utile allo studente per orientarsi e ripassare.

REGOLE OBBLIGATORIE:
1. Usa Markdown gerarchico: ## per gli argomenti principali, elenchi puntati e annidati per i sotto-punti.
2. Voci BREVI ed essenziali (non frasi lunghe): è un indice, non un riassunto.
3. Includi SOLO argomenti effettivamente presenti nel testo. NON inventare.
4. Mantieni l'ordine in cui gli argomenti compaiono nel testo.
5. Risposta SOLO il markdown della scaletta: NIENTE preamble, NIENTE commenti, NIENTE code fence.
TXT;
    }

    private function buildUserMessage(string $contextLabel, string $content, string $instruction): string
    {
        $content = trim($content);
        if ($content === '') {
            throw new RuntimeException('Nessun testo sorgente da elaborare');
        }
        if (mb_strlen($content) > self::MAX_CONTENT_CHARS) {
            $content = mb_substr($content, 0, self::MAX_CONTENT_CHARS)
                . "\n[...contenuto troncato per limiti di token]";
        }

        return <<<TXT
Titolo del materiale: **{$contextLabel}**

Contenuto del materiale:
---
{$content}
---

{$instruction}
TXT;
    }
}
