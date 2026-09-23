<?php

namespace App\Services\CourseGeneration;

use App\Models\Module;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Motore generazione corsi da KB — Fase 2: genera il TESTO (HTML) del manuale
 * discente e del manuale formatore per un modulo, a partire dai materiali
 * sorgente del corso e dal brief. Pattern identico a ConceptMapGenerationService.
 *
 * Manuale discente e manuale formatore sono chiamate SEPARATE (registro e scopo
 * diversi: il primo è il contenuto che legge lo studente, il secondo sono le
 * note/istruzioni per chi eroga il corso) ma condividono lo stesso sourceText.
 */
class ModuleManualGenerationService
{
    public function __construct(private \App\Services\Ai\ClaudeClient $claude) {}

    private const MAX_TOKENS = 4096;
    private const TEMPERATURE = 0.4;

    public function generateStudentManual(Module $module, array $brief, string $sourceText): string
    {
        $system = <<<TXT
Sei un autore didattico che scrive MANUALI PER STUDENTI di corsi di formazione.

Scrivi il contenuto completo di UN modulo/capitolo, in HTML semplice (solo tag
<h3>, <h4>, <p>, <ul>, <li>, <strong>, <em> — niente <html>/<head>/<body>, niente
markdown, niente code fence). Il testo deve essere autosufficiente e chiaro per
chi studia in autonomia (corso {$this->modalityLabel($brief)}).

REGOLE:
1. Copri gli argomenti indicati nel sommario del modulo, usando SOLO i fatti presenti nei materiali sorgente forniti. Non inventare dati, numeri o riferimenti normativi assenti dal materiale.
2. Rispetta tono/registro e livello indicati nel brief.
3. Struttura con sotto-sezioni (<h4>) quando il contenuto lo richiede.
4. Output: SOLO l'HTML del contenuto, nessun preambolo, nessuna spiegazione fuori dall'HTML.
TXT;

        $user = $this->buildUserMessage($module, $brief, $sourceText, 'manuale discente');

        return $this->call($system, $user, 'course_generation.student_manual', $module);
    }

    public function generateInstructorManual(Module $module, array $brief, string $sourceText): string
    {
        $system = <<<TXT
Sei un instructional designer che scrive MANUALI PER FORMATORI (guide alla docenza).

Scrivi le note di erogazione di UN modulo/capitolo, in HTML semplice (solo tag
<h3>, <h4>, <p>, <ul>, <li>, <strong>, <em> — niente <html>/<head>/<body>, niente
markdown, niente code fence). Il destinatario è il formatore che eroga il corso,
NON lo studente.

REGOLE:
1. Includi: obiettivi didattici del modulo, punti chiave da enfatizzare in aula, possibili domande/difficoltà degli studenti, tempi indicativi per sotto-argomento.
2. Usa SOLO i fatti presenti nei materiali sorgente forniti. Non inventare dati, numeri o riferimenti normativi assenti dal materiale.
3. Rispetta tono/registro e livello indicati nel brief, con taglio operativo (istruzioni al formatore, non prosa per lo studente).
4. Output: SOLO l'HTML del contenuto, nessun preambolo, nessuna spiegazione fuori dall'HTML.
TXT;

        $user = $this->buildUserMessage($module, $brief, $sourceText, 'manuale formatore');

        return $this->call($system, $user, 'course_generation.instructor_manual', $module);
    }

    private function call(string $system, string $user, string $feature, Module $module): string
    {
        Log::info('ModuleManual generation request', ['feature' => $feature, 'module_id' => $module->id]);

        $res = $this->claude->messages([
            'max_tokens' => self::MAX_TOKENS,
            'temperature' => self::TEMPERATURE,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $user]],
        ], ['feature' => $feature, 'course_id' => $module->course_id, 'meta' => ['module_id' => $module->id]]);

        if ($res->failed()) {
            Log::error('ModuleManual Claude API failed', ['feature' => $feature, 'module_id' => $module->id, 'error' => $res->error]);
            throw new RuntimeException('Errore Claude API: ' . ($res->status ?? $res->error));
        }

        $html = trim($res->text());
        if ($html === '') {
            throw new RuntimeException('Risposta Claude vuota');
        }

        return $this->stripCodeFence($html);
    }

    private function buildUserMessage(Module $module, array $brief, string $sourceText, string $target): string
    {
        $briefText = $this->formatBrief($brief);

        return <<<TXT
Modulo: {$module->title}
Sommario del modulo: {$module->description}

Brief del formatore:
{$briefText}

Materiali sorgente del corso (usa solo la parte pertinente a QUESTO modulo):
---
{$sourceText}
---

Scrivi il {$target} di questo modulo. Rispondi con solo l'HTML del contenuto.
TXT;
    }

    private function formatBrief(array $brief): string
    {
        $lines = [];
        if (!empty($brief['target'])) $lines[] = "- Pubblico/target: {$brief['target']}";
        if (!empty($brief['level'])) $lines[] = "- Livello: {$brief['level']}";
        if (!empty($brief['tone'])) $lines[] = "- Tono/registro: {$brief['tone']}";
        if (!empty($brief['language'])) $lines[] = "- Lingua: {$brief['language']}";
        if (!empty($brief['constraints'])) $lines[] = "- Vincoli: {$brief['constraints']}";

        return empty($lines) ? '(nessun vincolo esplicito indicato dal formatore)' : implode("\n", $lines);
    }

    private function modalityLabel(array $brief): string
    {
        return !empty($brief['tone']) ? "in registro {$brief['tone']}" : 'ad ampio pubblico';
    }

    private function stripCodeFence(string $html): string
    {
        $html = preg_replace('/^```(?:html)?\s*\n/', '', $html);
        $html = preg_replace('/\n```\s*$/', '', $html);
        return trim($html);
    }
}
