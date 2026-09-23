<?php

namespace App\Services\CourseGeneration;

use App\Models\Course;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Motore generazione corsi da KB — Fase 1: propone la struttura del corso
 * (elenco di moduli/capitoli) a partire dai materiali caricati a livello di
 * corso (Course::courseLevelMaterials) e dal brief del formatore.
 *
 * Pattern e stack identici a ConceptMapGenerationService: system/user prompt
 * separati, extractJson robusto anti-troncamento, validazione output.
 */
class CourseOutlineGenerationService
{
    public function __construct(
        private \App\Services\Ai\ClaudeClient $claude,
        private CourseSourceAggregator $aggregator,
    ) {}

    private const MAX_TOKENS = 4000;
    private const TEMPERATURE = 0.4;
    private const MAX_MODULES = 20;

    /**
     * @param  array  $brief  target, level, duration_hours, modules_count, objectives, tone, language, constraints
     * @param  ?array  $selectedSources  selezione confermata dal formatore (null = tutta la KB del corso)
     * @return array<int, array{title:string, summary:string}>
     */
    public function generate(Course $course, array $brief, ?array $selectedSources = null): array
    {
        $source = $this->aggregator->aggregate($course, $selectedSources);
        if (trim($source) === '') {
            throw new RuntimeException('Nessun materiale caricato sul corso: carica prima i documenti della knowledge base.');
        }

        $system = $this->buildSystemPrompt();
        $user = $this->buildUserMessage($course, $brief, $source);

        Log::info('CourseOutline generation request', [
            'course_id' => $course->id,
            'source_chars' => mb_strlen($source),
        ]);

        $res = $this->claude->messages([
            'max_tokens' => self::MAX_TOKENS,
            'temperature' => self::TEMPERATURE,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $user]],
        ], ['feature' => 'course_generation.outline', 'course_id' => $course->id]);

        if ($res->failed()) {
            Log::error('CourseOutline Claude API failed', ['course_id' => $course->id, 'error' => $res->error]);
            throw new RuntimeException('Errore Claude API: ' . ($res->status ?? $res->error));
        }

        $raw = $res->text();
        if (empty(trim($raw))) {
            throw new RuntimeException('Risposta Claude vuota');
        }

        $json = $this->extractJson($raw);
        $parsed = json_decode($json, true);
        if (!is_array($parsed) || !isset($parsed['modules']) || !is_array($parsed['modules'])) {
            Log::error('CourseOutline JSON parse failed', [
                'course_id' => $course->id,
                'raw' => Str::limit($raw, 500),
            ]);
            throw new RuntimeException('Output Claude non è JSON valido');
        }

        return $this->validateModules($parsed['modules']);
    }

    private function buildSystemPrompt(): string
    {
        return <<<TXT
Sei un instructional designer esperto nella progettazione di corsi di formazione.

Il tuo compito è proporre la STRUTTURA di un corso (elenco di moduli/capitoli) a
partire dai materiali sorgente forniti e dal brief del formatore.

REGOLE OBBLIGATORIE:
1. Output: SOLO un oggetto JSON, niente preamble, niente code fence, niente markdown.
2. Struttura: {"modules":[{"title":"...", "summary":"..."}]}
3. "title": titolo breve e chiaro del modulo (MAX 10 parole).
4. "summary": 2-4 frasi che descrivono cosa tratta il modulo e cosa lo studente imparerà.
5. L'ordine dei moduli nell'array È l'ordine didattico del corso.
6. Copri TUTTI gli argomenti rilevanti presenti nei materiali sorgente. Non inventare argomenti assenti dal materiale.
7. Rispetta il brief del formatore quando presente (numero di moduli desiderato, argomenti da includere/escludere, ordine vincolato).
8. Massimo 20 moduli.

ESEMPIO di output corretto:
{"modules":[{"title":"Introduzione alla materia","summary":"Panoramica dei concetti fondamentali e del contesto normativo di riferimento. Lo studente acquisisce il lessico di base necessario per i moduli successivi."}]}
TXT;
    }

    private function buildUserMessage(Course $course, array $brief, string $source): string
    {
        $briefText = $this->formatBrief($brief);

        return <<<TXT
Corso: {$course->name}

Brief del formatore:
{$briefText}

Materiali sorgente:
---
{$source}
---

Proponi la struttura del corso (elenco di moduli). Rispondi con solo l'oggetto JSON.
TXT;
    }

    private function formatBrief(array $brief): string
    {
        $lines = [];
        if (!empty($brief['target'])) $lines[] = "- Pubblico/target: {$brief['target']}";
        if (!empty($brief['level'])) $lines[] = "- Livello: {$brief['level']}";
        if (!empty($brief['duration_hours'])) $lines[] = "- Durata indicativa: {$brief['duration_hours']} ore";
        if (!empty($brief['modules_count'])) $lines[] = "- Numero di moduli desiderato: {$brief['modules_count']}";
        if (!empty($brief['objectives'])) $lines[] = "- Obiettivi di apprendimento: {$brief['objectives']}";
        if (!empty($brief['tone'])) $lines[] = "- Tono/registro: {$brief['tone']}";
        if (!empty($brief['language'])) $lines[] = "- Lingua: {$brief['language']}";
        if (!empty($brief['constraints'])) $lines[] = "- Vincoli: {$brief['constraints']}";

        return empty($lines) ? '(nessun vincolo esplicito indicato dal formatore)' : implode("\n", $lines);
    }

    private function extractJson(string $raw): string
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*\n/', '', $raw);
        $raw = preg_replace('/\n```\s*$/', '', $raw);
        $raw = trim($raw);

        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            return substr($raw, $start, $end - $start + 1);
        }
        return $raw;
    }

    /** @return array<int, array{title:string, summary:string}> */
    private function validateModules(array $modules): array
    {
        $result = [];
        foreach (array_slice($modules, 0, self::MAX_MODULES) as $m) {
            if (!is_array($m)) continue;
            $title = trim((string) ($m['title'] ?? ''));
            $summary = trim((string) ($m['summary'] ?? ''));
            if ($title === '') continue;
            $result[] = [
                'title' => mb_substr($title, 0, 255),
                'summary' => mb_substr($summary, 0, 1000),
            ];
        }

        if (empty($result)) {
            throw new RuntimeException('Nessun modulo valido prodotto');
        }

        return $result;
    }
}
