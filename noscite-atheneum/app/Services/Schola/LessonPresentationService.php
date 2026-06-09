<?php

namespace App\Services\Schola;

use App\Models\Lesson;
use App\Models\LessonPresentation;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Genera la presentazione .pptx di una lezione (Schola P21).
 *
 * Due passi: (1) Claude trasforma lessons.content in una SPEC di slide (JSON,
 * una slide per sezione, bullet sintetici, registro scuola superiore); (2)
 * python-pptx renderizza la spec in un .pptx (non si reinventa il formato OOXML).
 * Il file vive in storage PRIVATO; il branding scuola, se presente, va sulla
 * slide di titolo. Stesso pattern AI degli altri servizi (Http::post Anthropic,
 * RuntimeException, Log).
 */
class LessonPresentationService
{
    private const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';
    private const TEMPERATURE = 0.3;
    private const MAX_TOKENS = 4000;
    private const MAX_CONTENT_CHARS = 30000;
    private const PROMPT_VERSION = 'pptx-2026-06';

    /**
     * Costruisce il .pptx per una presentazione di lezione.
     *
     * @return array{file_path: string, meta: array}
     */
    public function build(LessonPresentation $presentation): array
    {
        $lesson = $presentation->lesson()->with('topic.subject')->first();
        if (!$lesson) {
            throw new RuntimeException('Lezione della presentazione non trovata.');
        }
        $content = trim((string) $lesson->content);
        if ($content === '') {
            throw new RuntimeException('La lezione non ha un corpo da trasformare in presentazione.');
        }

        $spec = $this->generateSpec($content, $lesson->title, [
            'topic' => $lesson->topic?->name,
            'subject' => $lesson->topic?->subject?->name,
            'log_context' => ['lesson_id' => $lesson->id, 'presentation_id' => $presentation->id],
        ]);

        // Branding scuola sulla slide di titolo (sopra il default piattaforma).
        $branding = SchoolBranding::for($lesson->teacher?->school);
        $spec['school'] = $branding->instanceName();
        $spec['subtitle'] = trim(implode(' · ', array_filter([
            $lesson->topic?->name, $lesson->topic?->subject?->name,
        ])));
        $spec['title'] = $lesson->title;
        $spec['accent'] = '55B1AE';

        $relPath = "lesson-presentations/{$lesson->id}/{$presentation->id}.pptx";
        $absPath = Storage::disk('local')->path($relPath);
        Storage::disk('local')->makeDirectory("lesson-presentations/{$lesson->id}");

        $this->renderPptx($spec, $absPath);

        if (!Storage::disk('local')->exists($relPath)) {
            throw new RuntimeException('Il file della presentazione non è stato creato.');
        }

        return [
            'file_path' => $relPath,
            'meta' => array_merge($spec['meta'] ?? [], [
                'slides' => count($spec['slides'] ?? []) + 1, // + slide di titolo
                'prompt_version' => self::PROMPT_VERSION,
                'filename' => Str::slug($lesson->title) . '.pptx',
            ]),
        ];
    }

    /**
     * Genera la spec delle slide via Claude.
     *
     * @return array{slides: array, meta: array}
     */
    public function generateSpec(string $content, string $title, array $options = []): array
    {
        $apiKey = config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY');
        if (empty($apiKey)) {
            throw new RuntimeException('Anthropic API key non configurata.');
        }

        if (mb_strlen($content) > self::MAX_CONTENT_CHARS) {
            $content = mb_substr($content, 0, self::MAX_CONTENT_CHARS) . "\n[...troncato]";
        }

        $subject = trim((string) ($options['subject'] ?? ''));
        $systemPrompt = $this->buildSystemPrompt($subject);
        $userMessage = "Titolo lezione: **{$title}**\n\nCorpo della lezione (markdown):\n---\n{$content}\n---\n\nProduci la presentazione in JSON.";

        Log::info('Lesson pptx spec request', $options['log_context'] ?? []);

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(120)->post(self::CLAUDE_API_URL, [
            'model' => config('services.pptx.model', 'claude-sonnet-4-5'),
            'max_tokens' => self::MAX_TOKENS,
            'temperature' => self::TEMPERATURE,
            'system' => $systemPrompt,
            'messages' => [['role' => 'user', 'content' => $userMessage]],
        ]);

        if (!$response->successful()) {
            Log::error('Lesson pptx Claude API failed', ['status' => $response->status()]);
            throw new RuntimeException('Errore Claude API: ' . $response->status());
        }

        $text = (string) ($response->json('content.0.text') ?? '');
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/```\s*$/', '', $text);
        $data = json_decode(trim($text), true);

        if (!is_array($data) || !isset($data['slides']) || !is_array($data['slides']) || empty($data['slides'])) {
            throw new RuntimeException('Spec presentazione non valida (JSON slides mancante).');
        }

        // Normalizza: ogni slide ha title + bullets[].
        $slides = [];
        foreach ($data['slides'] as $s) {
            if (!is_array($s)) {
                continue;
            }
            $slides[] = [
                'title' => trim((string) ($s['title'] ?? '')),
                'bullets' => array_values(array_filter(array_map(
                    fn ($b) => trim((string) $b),
                    is_array($s['bullets'] ?? null) ? $s['bullets'] : []
                ))),
                'notes' => isset($s['notes']) ? trim((string) $s['notes']) : null,
            ];
        }

        return [
            'slides' => $slides,
            'meta' => [
                'model' => config('services.pptx.model', 'claude-sonnet-4-5'),
                'tokens_in' => (int) ($response->json('usage.input_tokens') ?? 0),
                'tokens_out' => (int) ($response->json('usage.output_tokens') ?? 0),
            ],
        ];
    }

    /** Renderizza la spec in .pptx via python-pptx (Symfony Process, JSON su stdin). */
    public function renderPptx(array $spec, string $absOutPath): void
    {
        $python = config('services.pptx.python', '/home/noscite/venv/bin/python');
        $script = base_path('resources/python/build_pptx.py');

        $process = new Process([$python, $script, $absOutPath]);
        $process->setInput(json_encode($spec, JSON_UNESCAPED_UNICODE));
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException('Render pptx fallito: ' . trim($process->getErrorOutput() ?: $process->getOutput()));
        }
    }

    private function buildSystemPrompt(string $subject): string
    {
        $subjectLine = $subject !== '' ? "Materia: {$subject}." : '';

        return <<<TXT
Sei un docente di scuola superiore che prepara le slide di una lezione. {$subjectLine}

Trasforma il corpo della lezione in una presentazione efficace:
- UNA slide per ogni SEZIONE principale della lezione (segui i titoli ## del markdown).
- Ogni slide: un titolo breve e 3-6 bullet SINTETICI (frasi corte, non paragrafi).
- Mantieni formule/espressioni matematiche in forma testuale leggibile (es. E = m·c²).
- Registro adatto a studenti di scuola superiore: chiaro e ordinato.
- Non inventare: usa solo i contenuti della lezione.

Rispondi SOLO con JSON valido, senza testo extra, in questo formato:
{
  "slides": [
    {"title": "Titolo sezione", "bullets": ["punto 1", "punto 2"], "notes": "nota per il docente (opzionale)"}
  ]
}
TXT;
    }
}
