<?php

namespace App\Services;

use App\Models\Course;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Genera una mappa concettuale (NON gerarchica, à la Novak/Cmap) di un corso
 * via Claude API. Output: array {nodes:[...], edges:[...]} pronto per vis-network.
 *
 * Pattern e stack identici a MindMapGenerationService — qui adattato a:
 *  - input aggregato di tutti i Module.content del corso
 *  - output JSON strutturato (non markdown gerarchico)
 *  - prompt che FORZA label esplicite sugli archi (è il punto della concept map)
 */
class ConceptMapGenerationService
{
    private const CLAUDE_API_URL = 'https://api.anthropic.com/v1/messages';
    private const CLAUDE_MODEL = 'claude-sonnet-4-5';
    private const MAX_TOKENS = 4000;
    private const TEMPERATURE = 0.3;
    private const MAX_CONTENT_CHARS = 30000;
    private const MAX_NODES = 25;
    private const MAX_EDGES = 40;

    /**
     * @return array{nodes: array<int,array>, edges: array<int,array>}
     */
    public function generate(Course $course): array
    {
        $apiKey = config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY');

        if (empty($apiKey)) {
            throw new RuntimeException('Anthropic API key non configurata (config/services.php o env ANTHROPIC_API_KEY)');
        }

        $modules = $course->modules()->orderBy('sort_order')->get();
        if ($modules->isEmpty()) {
            throw new RuntimeException('Il corso non ha moduli su cui costruire la mappa concettuale');
        }

        $aggregated = $modules
            ->map(fn ($m) => '## ' . $m->title . "\n" . trim(strip_tags($m->content ?? '')))
            ->implode("\n\n---\n\n");

        if (mb_strlen($aggregated) > self::MAX_CONTENT_CHARS) {
            $aggregated = mb_substr($aggregated, 0, self::MAX_CONTENT_CHARS)
                . "\n[...contenuto troncato per limiti di token]";
        }

        $systemPrompt = $this->buildSystemPrompt();
        $userMessage = $this->buildUserMessage($course->name, $aggregated);

        Log::info('ConceptMap generation request', [
            'course_id' => $course->id,
            'course_name' => $course->name,
            'modules_count' => $modules->count(),
            'content_chars' => mb_strlen($aggregated),
        ]);

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(180)->post(self::CLAUDE_API_URL, [
            'model' => self::CLAUDE_MODEL,
            'max_tokens' => self::MAX_TOKENS,
            'temperature' => self::TEMPERATURE,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userMessage],
            ],
        ]);

        if (! $response->successful()) {
            Log::error('ConceptMap Claude API failed', [
                'course_id' => $course->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new RuntimeException('Errore Claude API: ' . $response->status());
        }

        $data = $response->json();
        $raw = $data['content'][0]['text'] ?? '';

        if (empty(trim($raw))) {
            throw new RuntimeException('Risposta Claude vuota');
        }

        $json = $this->extractJson($raw);
        $parsed = json_decode($json, true);
        if (! is_array($parsed)) {
            Log::error('ConceptMap JSON parse failed', [
                'course_id' => $course->id,
                'raw' => Str::limit($raw, 500),
            ]);
            throw new RuntimeException('Output Claude non è JSON valido');
        }

        $validated = $this->validateGraph($parsed);

        Log::info('ConceptMap generated', [
            'course_id' => $course->id,
            'nodes' => count($validated['nodes']),
            'edges' => count($validated['edges']),
            'usage' => $data['usage'] ?? null,
        ]);

        return $validated;
    }

    private function buildSystemPrompt(): string
    {
        $domainContext = function_exists('atheneum_setting')
            ? atheneum_setting('assistant_domain_context', '')
            : '';
        $contextHint = ! empty($domainContext)
            ? "Il pubblico target è: {$domainContext}."
            : '';

        $maxNodes = self::MAX_NODES;
        $maxEdges = self::MAX_EDGES;

        return <<<TXT
Sei un esperto pedagogo specializzato nella costruzione di MAPPE CONCETTUALI (modello Novak/Cmap), NON mappe mentali.

Una mappa concettuale è un GRAFO DIRETTO in cui:
- I NODI sono concetti chiave (brevi, sostantivi/locuzioni nominali)
- Gli ARCHI rappresentano relazioni SEMANTICHE EXPLICITE tra concetti, etichettate con verbi/locuzioni come: "causa", "include", "richiede", "porta a", "differisce da", "è esempio di", "precede", "implica", "si oppone a", "contiene", "produce", "definisce".

A differenza di una mappa mentale, qui i legami devono essere DICHIARATI: il significato è nell'etichetta dell'arco, non nella semplice vicinanza.

{$contextHint}

REGOLE OBBLIGATORIE:
1. Output: SOLO un oggetto JSON, niente preamble, niente code fence, niente markdown.
2. Struttura: {"nodes":[...], "edges":[...]}
3. Ogni nodo: {"id":"n1", "label":"...", "description":"..."} — id univoco (n1,n2,...), label MAX 6 parole, description MAX 25 parole.
4. Ogni arco: {"id":"e1", "from":"n1", "to":"n2", "label":"..."} — label OBBLIGATORIA (verbo/locuzione), MAX 4 parole.
5. Massimo {$maxNodes} nodi e {$maxEdges} archi.
6. Estrai SOLO concetti effettivamente presenti nel contenuto del corso. NON inventare.
7. Privilegia archi che attraversano più moduli del corso (la mappa è di CORSO, non di singolo modulo).
8. Da 1 a 3 nodi possono essere identificati come "ancore" (concetti centrali del corso) e linkati a più di 3 archi.
9. NON aggiungere posizioni x/y: layout calcolato dal frontend.

ESEMPIO di output corretto:
{"nodes":[{"id":"n1","label":"Allucinazione LLM","description":"Generazione di output plausibile ma falso."},{"id":"n2","label":"Verifica fonti","description":"Controllo umano della veridicità."}],"edges":[{"id":"e1","from":"n1","to":"n2","label":"richiede"}]}
TXT;
    }

    private function buildUserMessage(string $courseName, string $aggregatedContent): string
    {
        return <<<TXT
Corso: **{$courseName}**

Contenuti aggregati dei moduli:
---
{$aggregatedContent}
---

Costruisci la mappa concettuale del corso. Rispondi con solo l'oggetto JSON.
TXT;
    }

    /**
     * Rimuove eventuali code fence o testo prima/dopo l'oggetto JSON.
     */
    private function extractJson(string $raw): string
    {
        $raw = trim($raw);
        $raw = preg_replace('/^```(?:json)?\s*\n/', '', $raw);
        $raw = preg_replace('/\n```\s*$/', '', $raw);
        $raw = trim($raw);

        // Se il modello ha preceduto/seguito con altro testo, estrai il primo { ... } esterno.
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');
        if ($start !== false && $end !== false && $end > $start) {
            return substr($raw, $start, $end - $start + 1);
        }
        return $raw;
    }

    /**
     * Valida e normalizza il grafo:
     *  - nodi con id univoco non vuoto
     *  - edges con from/to esistenti e label non vuota
     *  - rimuove duplicati di archi (stessa coppia from/to/label)
     *  - applica cap MAX_NODES e MAX_EDGES
     *
     * @return array{nodes: array, edges: array}
     */
    private function validateGraph(array $parsed): array
    {
        if (! isset($parsed['nodes']) || ! is_array($parsed['nodes'])) {
            throw new RuntimeException('JSON privo di array "nodes"');
        }
        if (! isset($parsed['edges']) || ! is_array($parsed['edges'])) {
            throw new RuntimeException('JSON privo di array "edges"');
        }

        $nodes = [];
        $idsSeen = [];
        foreach (array_slice($parsed['nodes'], 0, self::MAX_NODES) as $n) {
            if (! is_array($n)) continue;
            $id = isset($n['id']) ? trim((string) $n['id']) : '';
            $label = isset($n['label']) ? trim((string) $n['label']) : '';
            if ($id === '' || $label === '' || in_array($id, $idsSeen, true)) continue;
            $idsSeen[] = $id;
            $nodes[] = [
                'id' => $id,
                'label' => mb_substr($label, 0, 120),
                'description' => isset($n['description']) ? mb_substr(trim((string) $n['description']), 0, 500) : '',
                'link_type' => null,
                'link_module_id' => null,
                'link_material_id' => null,
                'link_url' => null,
            ];
        }

        if (empty($nodes)) {
            throw new RuntimeException('Nessun nodo valido prodotto');
        }

        $edges = [];
        $sigSeen = [];
        $autoId = 1;
        foreach (array_slice($parsed['edges'], 0, self::MAX_EDGES) as $e) {
            if (! is_array($e)) continue;
            $from = isset($e['from']) ? trim((string) $e['from']) : '';
            $to = isset($e['to']) ? trim((string) $e['to']) : '';
            $label = isset($e['label']) ? trim((string) $e['label']) : '';
            if ($from === '' || $to === '' || $label === '') continue;
            if (! in_array($from, $idsSeen, true) || ! in_array($to, $idsSeen, true)) continue;
            $sig = $from . '|' . $to . '|' . $label;
            if (in_array($sig, $sigSeen, true)) continue;
            $sigSeen[] = $sig;
            $id = isset($e['id']) && trim((string) $e['id']) !== '' ? trim((string) $e['id']) : 'e' . $autoId++;
            $edges[] = [
                'id' => $id,
                'from' => $from,
                'to' => $to,
                'label' => mb_substr($label, 0, 60),
                'arrows' => 'to',
            ];
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'physics' => ['enabled' => true],
        ];
    }
}
