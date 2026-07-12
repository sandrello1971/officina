<?php

namespace App\Services;

use App\Models\InstructorManualSection;
use App\Models\Material;
use App\Models\Module;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Ri-calcola la mappatura sezione-formatore → modulo-discente per un manuale
 * esistente, SENZA re-splittare il contenuto (tocca solo module_id).
 *
 *  - Le mappature AUTO (module_assigned_manually = false) vengono ricalcolate da
 *    zero con l'euristica corretta dello splitter: quelle sbagliate (vecchio
 *    off-by-one su sort_order) vengono corrette o azzerate.
 *  - Le mappature MANUALI (module_assigned_manually = true) sono SEMPRE preservate.
 *  - Con $useAi, le sezioni che l'euristica non sa mappare (tassonomie diverse,
 *    es. formatore "Modulo N" vs discente "Parte N") vengono proposte da Claude
 *    per tema (una sola chiamata per manuale); le sezioni globali (front-matter,
 *    glossario, esame) restano non mappate.
 */
class InstructorManualRemapService
{
    private const CLAUDE_MODEL = 'claude-sonnet-4-5';

    public function __construct(protected InstructorManualSplitterService $splitter) {}

    /**
     * @return array{
     *   material:string, total:int, manual_kept:int, changes:array<int,array>,
     *   ai_used:bool, ai_assigned:int, unmapped_after:int, applied:bool
     * }
     */
    public function remap(Material $material, bool $useAi = false, bool $dryRun = false): array
    {
        $modules = Module::where('course_id', $material->course_id)
            ->orderBy('sort_order')->get(['id', 'sort_order', 'title']);
        $modulesById = $modules->keyBy('id');
        $modulesBySort = $modules->keyBy('sort_order');

        $sections = InstructorManualSection::where('material_id', $material->id)
            ->orderBy('sort_order')->get();

        // 1) Euristica: ricalcola ogni sezione AUTO. Manuali intatte.
        $target = [];        // section_id => new module_id|null
        $manualKept = 0;
        foreach ($sections as $s) {
            if ($s->module_assigned_manually) {
                $manualKept++;
                continue;
            }
            $target[$s->id] = $this->splitter->autoMapToModule($s->title, $modules);
        }

        // 2) Fallback AI per le sezioni AUTO ancora senza modulo.
        $aiAssigned = 0;
        $aiUsed = false;
        $stillNull = $sections->filter(
            fn ($s) => !$s->module_assigned_manually && $target[$s->id] === null
        )->values();

        if ($useAi && $stillNull->isNotEmpty() && $modules->isNotEmpty()) {
            $aiUsed = true;
            $proposals = $this->proposeWithAi($material, $stillNull, $modules); // section_id => sort_order|null
            foreach ($proposals as $sid => $sort) {
                if ($sort !== null && $modulesBySort->has($sort)) {
                    $target[$sid] = $modulesBySort[$sort]->id;
                    $aiAssigned++;
                }
            }
        }

        // 3) Diff + (eventuale) applicazione. Solo AUTO in $target.
        $changes = [];
        foreach ($sections as $s) {
            if (!array_key_exists($s->id, $target)) {
                continue; // manuale: saltata
            }
            $new = $target[$s->id];
            if ($new === $s->module_id) {
                continue; // nessun cambiamento
            }
            $changes[] = [
                'section'    => $s->title,
                'from'       => $s->module_id ? ($modulesById[$s->module_id]->title ?? '??') : null,
                'to'         => $new ? ($modulesById[$new]->title ?? '??') : null,
                'section_id' => $s->id,
                'new_id'     => $new,
            ];
            if (!$dryRun) {
                $s->update(['module_id' => $new]); // resta AUTO (module_assigned_manually = false)
            }
        }

        $unmappedAfter = collect($target)->filter(fn ($v) => $v === null)->count();

        return [
            'material'       => $material->title,
            'total'          => $sections->count(),
            'manual_kept'    => $manualKept,
            'changes'        => $changes,
            'ai_used'        => $aiUsed,
            'ai_assigned'    => $aiAssigned,
            'unmapped_after' => $unmappedAfter,
            'applied'        => !$dryRun,
        ];
    }

    /**
     * Una sola chiamata a Claude: propone il modulo per ciascuna sezione non
     * mappata (o null se è guida generale non legata a un modulo specifico).
     *
     * @param  \Illuminate\Support\Collection<int,InstructorManualSection>  $sections
     * @param  \Illuminate\Support\Collection<int,Module>  $modules
     * @return array<string,int|null>  section_id => module_sort_order|null
     */
    private function proposeWithAi($material, $sections, $modules): array
    {
        $moduleList = $modules->map(fn ($m) => "  [{$m->sort_order}] {$m->title}")->implode("\n");

        $secList = $sections->map(function ($s) {
            $snippet = trim(preg_replace('/\s+/', ' ', strip_tags($s->content_html ?? '')));
            $snippet = mb_substr($snippet, 0, 240);
            return "  id={$s->id} | {$s->title}\n     estratto: {$snippet}";
        })->implode("\n");

        $system = <<<SYS
Sei un assistente che mappa le sezioni del MANUALE DEL FORMATORE ai MODULI del manuale DISCENTE di un corso.
Per ogni sezione, indica il sort_order del modulo discente che quella sezione del formatore accompagna/spiega.
Regole:
- Abbina per TEMA/contenuto, non per numero: le due numerazioni possono differire (es. formatore "Modulo N" vs discente "Parte N").
- Se una sezione è guida GENERALE non legata a un singolo modulo (introduzione, fondamenti pedagogici, "come usare il manuale", glossario, esame/valutazione, indice, note di conduzione generiche), restituisci module_sort_order = null.
- Usa SOLO i sort_order presenti nell'elenco moduli.
- Rispondi SOLO con JSON valido, nessun altro testo.

Formato:
{"map":[{"section_id":"<id>","module_sort_order":<intero|null>}]}
SYS;

        $user = "CORSO: {$material->title}\n\nMODULI DISCENTE (sort_order | titolo):\n{$moduleList}\n\n"
            . "SEZIONI DEL FORMATORE da mappare:\n{$secList}\n\nRispondi SOLO con JSON valido.";

        try {
            $response = Http::withHeaders([
                'x-api-key' => config('services.anthropic.key') ?? env('ANTHROPIC_API_KEY'),
                'anthropic-version' => '2023-06-01',
                'content-type' => 'application/json',
            ])->timeout(120)->post('https://api.anthropic.com/v1/messages', [
                'model' => self::CLAUDE_MODEL,
                'max_tokens' => 2048,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $user]],
            ]);

            if ($response->failed()) {
                Log::warning('InstructorManualRemapService: AI call failed', ['status' => $response->status()]);
                return [];
            }

            $text = (string) $response->json('content.0.text', '');
            $text = trim(preg_replace('/```(?:json)?\s*|\s*```/i', '', $text));
            $data = json_decode($text, true);

            if (!is_array($data) || !isset($data['map']) || !is_array($data['map'])) {
                Log::warning('InstructorManualRemapService: JSON AI non valido');
                return [];
            }

            $out = [];
            foreach ($data['map'] as $row) {
                if (!isset($row['section_id'])) continue;
                $sort = $row['module_sort_order'] ?? null;
                $out[(string) $row['section_id']] = is_numeric($sort) ? (int) $sort : null;
            }

            return $out;
        } catch (\Throwable $e) {
            Log::error('InstructorManualRemapService error: ' . $e->getMessage());
            return [];
        }
    }
}
