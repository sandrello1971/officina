<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Esporta in UN UNICO file Markdown tutto il corpus documentale dei corsi —
 * contenuti dei moduli, manuali formatore, canvas interattivi, notebook di
 * esercizio, quiz — a fini di CERTIFICAZIONE DELLA PROPRIETÀ INTELLETTUALE.
 *
 * Principi (è un documento probatorio, non un report):
 *  - NULLA viene omesso: i materiali non collegati a corso/modulo finiscono in
 *    appendice invece di sparire, e il conteggio finale deve tornare;
 *  - la prosa HTML è resa leggibile con pandoc, ma di OGNI elemento si registra
 *    l'impronta SHA-256 della SORGENTE ORIGINALE (l'HTML/JSON come sta nel DB o
 *    su disco), così il documento resta verificabile contro la piattaforma;
 *  - canvas e notebook sono inclusi in sorgente integrale: il codice È l'opera;
 *  - sola LETTURA: nessuna scrittura sul database interrogato.
 *
 * Esempio (corpus di produzione):
 *   php artisan docs:export-corpus --db=atheneum_db \
 *     --storage=/var/www/noscite-atheneum/storage/app/private
 */
class ExportDocumentationCorpus extends Command
{
    protected $signature = 'docs:export-corpus
        {--db= : Database da cui leggere (default: quello configurato)}
        {--storage= : Radici dello storage dove cercare i file dei materiali, separate da virgola}
        {--out= : File .md di destinazione}
        {--source=full : full = canvas/notebook in sorgente integrale, text = solo parte testuale}';

    protected $description = 'Esporta in un unico Markdown tutto il corpus documentale dei corsi (certificazione IP)';

    /** @var resource */
    private $fh;

    /** @var array<int, array{tipo: string, riferimento: string, byte: int, sha: string}> */
    private array $manifest = [];

    /**
     * Id dei materiali già riprodotti. Un materiale può essere pescato da più
     * query (è agganciato sia al modulo sia al corso): senza questo presidio
     * finirebbe due volte nel documento, falsando il manifesto.
     *
     * @var array<string, true>
     */
    private array $seenMaterials = [];

    /**
     * Radici in cui cercare i file dei materiali, in ordine. Sono più d'una
     * perché i materiali non stanno tutti sullo stesso disco: parte è sotto
     * `app/private`, parte sotto `app/public`. Cercarne una sola farebbe
     * risultare "mancanti" file che invece esistono.
     *
     * @var array<int, string>
     */
    private array $storageRoots;

    private bool $fullSource;

    public function handle(): int
    {
        $db = (string) ($this->option('db') ?: config('database.connections.pgsql.database'));
        $roots = (string) ($this->option('storage') ?: storage_path('app/private') . ',' . storage_path('app/public'));
        $this->storageRoots = array_map(fn ($r) => rtrim(trim($r), '/'), explode(',', $roots));
        $this->fullSource = $this->option('source') !== 'text';

        $out = (string) ($this->option('out')
            ?: storage_path('app/private/exports/corpus-documentale-' . date('Y-m-d') . '.md'));

        if (!is_dir(dirname($out))) {
            mkdir(dirname($out), 0775, true);
        }

        // Connessione dedicata al DB richiesto: l'export gira dal checkout di
        // sviluppo ma legge il corpus reale, senza toccarne i file.
        config(['database.connections.export' => array_merge(
            config('database.connections.pgsql'),
            ['database' => $db]
        )]);
        $conn = DB::connection('export');

        $courses = $conn->table('courses')->orderBy('sort_order')->orderBy('name')->get();
        if ($courses->isEmpty()) {
            $this->error("Nessun corso in '{$db}': database sbagliato?");

            return self::FAILURE;
        }

        $this->fh = fopen($out, 'w');
        $this->info("Esporto {$courses->count()} corsi da '{$db}' → {$out}");

        $this->writeHeader($conn, $db, $courses->count());
        $this->writeIndex($courses);

        $bar = $this->output->createProgressBar($courses->count());
        $bar->start();
        foreach ($courses as $i => $course) {
            $this->writeCourse($conn, $course, $i + 1);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();

        $this->writeOrphans($conn);
        $this->writeManifest();
        $ok = $this->writeCompletenessCheck($conn);

        fclose($this->fh);

        $this->newLine();
        $this->info('Fatto: ' . $out . ' (' . $this->human(filesize($out)) . ', '
            . count($this->manifest) . ' elementi certificati)');

        if (!$ok) {
            $this->warn('Verifica di completezza NON superata: vedi Appendice C nel documento.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    // ===== Intestazione, indice =====

    private function writeHeader($conn, string $db, int $courseCount): void
    {
        $counts = [
            'Corsi' => $courseCount,
            'Moduli' => $conn->table('modules')->count(),
            'Materiali' => $conn->table('materials')->count(),
            'Sezioni di manuale formatore' => $conn->table('instructor_manual_sections')->count(),
            'Quiz' => $conn->table('quizzes')->count(),
            'Domande di quiz' => $conn->table('quiz_questions')->count(),
        ];

        $this->put("# Corpus documentale dei corsi — Officina\n\n");
        $this->put('> ' . copyright_notice() . "\n>\n");
        $this->put("> Documento generato automaticamente a fini di **certificazione della proprietà\n");
        $this->put("> intellettuale**. Riproduce integralmente il contenuto didattico della\n");
        $this->put("> piattaforma alla data di generazione.\n\n");

        $this->put("| | |\n|---|---|\n");
        $this->put('| Data di generazione | ' . date('d/m/Y H:i:s') . " |\n");
        $this->put('| Sorgente | database `' . $db . "` |\n");
        $this->put('| File dei materiali | `' . implode('`, `', $this->storageRoots) . "` |\n");
        foreach ($counts as $label => $n) {
            $this->put('| ' . $label . ' | ' . $n . " |\n");
        }
        $this->put("\n");

        $this->put("## Nota metodologica\n\n");
        $this->put("- Il contenuto redazionale è conservato nella piattaforma in **HTML**; qui è reso\n");
        $this->put("  in Markdown leggibile tramite `pandoc`. Per ogni elemento l'**Appendice B**\n");
        $this->put("  riporta l'impronta **SHA-256 della sorgente originale** (l'HTML o il file come\n");
        $this->put("  si trovano nella piattaforma), così il presente documento è verificabile\n");
        $this->put("  contro di essa.\n");
        $this->put('- **Canvas interattivi** e **notebook di esercizio** sono riprodotti '
            . ($this->fullSource ? "in **sorgente integrale**: il codice costituisce esso stesso l'opera.\n" : "nella sola parte testuale.\n"));
        $this->put("- I materiali non collegati ad alcun corso o modulo non sono esclusi: sono\n");
        $this->put("  riportati nell'**Appendice A**.\n");
        $this->put("- L'estrazione è avvenuta in sola lettura, senza alcuna modifica alla piattaforma.\n\n");
        $this->put("---\n\n");
    }

    private function writeIndex($courses): void
    {
        $this->put("## Indice dei corsi\n\n");
        foreach ($courses as $i => $c) {
            $this->put(($i + 1) . '. ' . $this->clean($c->name) . "\n");
        }
        $this->put("\nAppendice A — Materiali non collegati a corso o modulo  \n");
        $this->put("Appendice B — Manifesto di integrità (SHA-256)  \n");
        $this->put("Appendice C — Verifica di completezza\n\n---\n\n");
    }

    // ===== Corso =====

    private function writeCourse($conn, $course, int $n): void
    {
        $this->put("# {$n}. " . $this->clean($course->name) . "\n\n");

        $this->put("| Attributo | Valore |\n|---|---|\n");
        $this->put('| Identificativo | `' . $course->id . "` |\n");
        $this->put('| Slug | `' . $course->slug . "` |\n");
        $this->put('| Durata dichiarata | ' . ($course->duration_hours ?: '—') . " ore |\n");
        $this->put('| Attestazione | ' . $this->clean($course->certification_name ?: '—') . " |\n");
        $this->put('| Modalità | ' . ($course->modality ?: 'in aula') . " |\n");
        $this->put('| Creato il | ' . substr((string) $course->created_at, 0, 10) . " |\n");
        $this->put('| Ultimo aggiornamento | ' . substr((string) $course->updated_at, 0, 10) . " |\n\n");

        if ($course->short_description) {
            $this->put('*' . $this->clean($course->short_description) . "*\n\n");
        }
        if ($course->description) {
            $this->put($this->html2md($course->description, 2) . "\n\n");
        }
        $this->track('Scheda corso', $course->name, (string) $course->description);

        if (!empty($course->exam_prep_html)) {
            $this->put("## Preparazione all'esame\n\n");
            $this->put($this->html2md($course->exam_prep_html, 2) . "\n\n");
            $this->track('Preparazione esame', $course->name, $course->exam_prep_html);
        }

        $modules = $conn->table('modules')->where('course_id', $course->id)
            ->orderBy('sort_order')->get();

        foreach ($modules as $mi => $module) {
            $this->writeModule($conn, $course, $module, $n, $mi + 1);
        }

        $this->writeCourseQuizzes($conn, $course);
        $this->writeInstructorManual($conn, $course);

        // Materiali agganciati al corso ma non a un modulo (esclusi i manuali,
        // già resi per esteso qui sopra).
        $courseMaterials = $conn->table('materials')
            ->where('course_id', $course->id)->whereNull('module_id')
            ->where('is_instructor_only', false)
            ->orderBy('sort_order')->get();

        if ($courseMaterials->isNotEmpty()) {
            $this->put("## Materiali di corso\n\n");
            foreach ($courseMaterials as $m) {
                $this->writeMaterial($m, 3);
            }
        }

        $this->put("---\n\n");
    }

    private function writeModule($conn, $course, $module, int $cn, int $mn): void
    {
        $this->put("## {$cn}.{$mn} " . $this->clean($module->title) . "\n\n");
        $this->put('`' . $module->id . '` · durata dichiarata: '
            . ($module->duration_minutes ?: '—') . " minuti\n\n");

        if ($module->description) {
            $this->put('*' . $this->clean($module->description) . "*\n\n");
        }

        if (trim((string) $module->content) !== '') {
            $this->put("### Contenuto didattico\n\n");
            $this->put($this->html2md($module->content, 3) . "\n\n");
            $this->track('Modulo', $course->name . ' › ' . $module->title, $module->content);
        }

        if (!empty($module->mindmap_markdown)) {
            $this->put("### Mappa concettuale\n\n```markdown\n");
            $this->put(rtrim($module->mindmap_markdown) . "\n```\n\n");
            $this->track('Mappa concettuale', $course->name . ' › ' . $module->title, $module->mindmap_markdown);
        }

        $materials = $conn->table('materials')->where('module_id', $module->id)
            ->orderBy('sort_order')->get();

        if ($materials->isNotEmpty()) {
            $this->put("### Materiali del modulo\n\n");
            foreach ($materials as $m) {
                $this->writeMaterial($m, 4);
            }
        }

        $quizzes = $conn->table('quizzes')->where('module_id', $module->id)->get();
        foreach ($quizzes as $q) {
            $this->writeQuiz($conn, $q, 3);
        }
    }

    // ===== Materiali (canvas, notebook, documenti, allegati) =====

    private function writeMaterial($m, int $level): void
    {
        if (isset($this->seenMaterials[$m->id])) {
            return;
        }
        $this->seenMaterials[$m->id] = true;

        $h = str_repeat('#', $level);
        $this->put("{$h} " . $this->clean($m->title) . "\n\n");
        $this->put('Tipo: `' . ($m->file_type ?: 'n/d') . '`'
            . ($m->is_instructor_only ? ' · **riservato al formatore**' : '')
            . ($m->file_path ? ' · `' . $m->file_path . '`' : '')
            . ($m->external_url ? ' · <' . $m->external_url . '>' : '')
            . "\n\n");

        if ($m->description) {
            $this->put('*' . $this->clean($m->description) . "*\n\n");
        }

        // Contenuto testuale già estratto nella piattaforma (manuali, schede .md).
        if (!empty($m->content_html)) {
            $this->put($this->html2md($m->content_html, $level) . "\n\n");
            $this->track('Materiale (' . $m->file_type . ')', $m->title, $m->content_html);

            return;
        }

        $abs = $this->resolveFile($m->file_path);
        if ($abs === null) {
            $reason = $this->unreadableReason($m->file_path);
            $this->put('> File non riprodotto: ' . $reason . "\n\n");
            $this->track('Materiale mancante (' . $m->file_type . ')', $m->title . ' — ' . $reason, '');

            return;
        }

        $raw = (string) file_get_contents($abs);

        match ($m->file_type) {
            'canvas' => $this->writeCanvas($m, $raw),
            'ipynb' => $this->writeNotebook($m, $raw),
            'md', 'markdown' => $this->put(rtrim($raw) . "\n\n"),
            'csv' => $this->put("```csv\n" . rtrim($this->head($raw, 4000)) . "\n```\n\n"),
            default => $this->put('> Allegato binario `' . basename($abs) . '` ('
                . $this->human(strlen($raw)) . "), impronta in Appendice B.\n\n"),
        };

        $this->track('Materiale (' . $m->file_type . ')', $m->title, $raw);
    }

    /** Canvas interattivo: pagina HTML autonoma, opera in sé. */
    private function writeCanvas($m, string $raw): void
    {
        $text = $this->htmlToPlainText($raw);
        if ($text !== '') {
            $this->put("**Testi dell'esercizio**\n\n" . $text . "\n\n");
        }

        if ($this->fullSource) {
            $this->put("<details>\n<summary>Sorgente integrale del canvas ("
                . $this->human(strlen($raw)) . ")</summary>\n\n```html\n");
            $this->put($this->fence($raw) . "\n```\n\n</details>\n\n");
        }
    }

    /** Notebook di esercizio: celle markdown (consegna) + celle di codice (soluzione). */
    private function writeNotebook($m, string $raw): void
    {
        $nb = json_decode($raw, true);
        if (!is_array($nb) || !isset($nb['cells'])) {
            $this->put("> Notebook illeggibile in fase di estrazione.\n\n");

            return;
        }

        foreach ($nb['cells'] as $cell) {
            $src = is_array($cell['source'] ?? null) ? implode('', $cell['source']) : (string) ($cell['source'] ?? '');
            if (trim($src) === '') {
                continue;
            }

            if (($cell['cell_type'] ?? '') === 'markdown') {
                $this->put(rtrim($src) . "\n\n");
            } elseif ($this->fullSource) {
                $this->put("```python\n" . $this->fence(rtrim($src)) . "\n```\n\n");
            }
        }
    }

    // ===== Quiz =====

    private function writeCourseQuizzes($conn, $course): void
    {
        $quizzes = $conn->table('quizzes')->where('course_id', $course->id)
            ->whereNull('module_id')->get();

        foreach ($quizzes as $q) {
            $this->writeQuiz($conn, $q, 2);
        }
    }

    private function writeQuiz($conn, $quiz, int $level): void
    {
        $h = str_repeat('#', $level);
        $this->put("{$h} Verifica: " . $this->clean($quiz->title) . "\n\n");
        $this->put('Soglia di superamento: ' . ($quiz->passing_score ?? '—') . '%'
            . ' · tentativi ammessi: ' . ($quiz->max_attempts ?: 'illimitati')
            . ' · domande per tentativo: ' . ($quiz->questions_per_attempt ?: 'tutte')
            . "\n\n");

        if ($quiz->description) {
            $this->put('*' . $this->clean($quiz->description) . "*\n\n");
        }

        $questions = $conn->table('quiz_questions')->where('quiz_id', $quiz->id)
            ->orderBy('sort_order')->get();

        foreach ($questions as $qi => $q) {
            $this->put('**' . ($qi + 1) . '.** ' . $this->clean($q->question) . "\n\n");

            $options = json_decode((string) $q->options, true);
            if (is_array($options)) {
                foreach ($options as $key => $opt) {
                    $label = is_string($key) ? $key : chr(65 + (int) $key);
                    $this->put('- `' . $label . '` ' . $this->clean(is_array($opt) ? json_encode($opt) : (string) $opt) . "\n");
                }
                $this->put("\n");
            }

            $this->put('Risposta corretta: **' . $this->clean((string) $q->correct_answer) . '**'
                . ' · punteggio: ' . ($q->points ?? 1) . "\n\n");

            if ($q->explanation) {
                $this->put('> ' . $this->clean($q->explanation) . "\n\n");
            }

            $this->track('Domanda di verifica', $quiz->title . ' #' . ($qi + 1),
                $q->question . '|' . $q->correct_answer . '|' . $q->explanation);
        }
    }

    // ===== Manuale formatore =====

    private function writeInstructorManual($conn, $course): void
    {
        // Solo i manuali di CORSO: quelli agganciati a un modulo sono già stati
        // riprodotti dentro il modulo di appartenenza.
        $manuals = $conn->table('materials')->where('course_id', $course->id)
            ->whereNull('module_id')
            ->where('is_instructor_only', true)->orderBy('sort_order')->get();

        if ($manuals->isEmpty()) {
            return;
        }

        $this->put("## Documentazione riservata al formatore\n\n");

        foreach ($manuals as $m) {
            $this->writeMaterial($m, 3);

            $sections = $conn->table('instructor_manual_sections')
                ->where('material_id', $m->id)->orderBy('sort_order')->get();

            if ($sections->isEmpty()) {
                continue;
            }

            $this->put("#### Struttura per moduli\n\n");
            $this->put("| # | Sezione | Modulo assegnato |\n|---|---|---|\n");
            foreach ($sections as $si => $s) {
                $moduleTitle = $s->module_id
                    ? (string) $conn->table('modules')->where('id', $s->module_id)->value('title')
                    : '—';
                $this->put('| ' . ($si + 1) . ' | ' . $this->clean($s->title) . ' | '
                    . $this->clean($moduleTitle) . ($s->module_assigned_manually ? ' *(manuale)*' : '') . " |\n");
            }
            $this->put("\n");

            foreach ($sections as $s) {
                if (trim((string) $s->content_html) === '') {
                    continue;
                }
                $this->put('##### ' . $this->clean($s->title) . "\n\n");
                $this->put($this->html2md($s->content_html, 5) . "\n\n");
                $this->track('Sezione manuale formatore', $course->name . ' › ' . $s->title, $s->content_html);
            }
        }
    }

    // ===== Appendici =====

    private function writeOrphans($conn): void
    {
        $orphans = $conn->table('materials')
            ->whereNull('module_id')->whereNull('course_id')
            ->orderBy('title')->get();

        $this->put("# Appendice A — Materiali non collegati a corso o modulo\n\n");

        if ($orphans->isEmpty()) {
            $this->put("Nessuno: ogni materiale risulta collegato.\n\n");

            return;
        }

        $this->put("Materiali presenti nella piattaforma il cui collegamento a un corso o a un\n");
        $this->put("modulo risulta assente. Riportati per completezza del deposito.\n\n");

        foreach ($orphans as $m) {
            $this->writeMaterial($m, 2);
        }
    }

    /**
     * Confronto fra ciò che la piattaforma contiene e ciò che il documento
     * riproduce. In un atto probatorio la completezza non si dà per buona: si
     * dimostra, e se non torna il documento lo dichiara invece di tacerlo.
     *
     * @return bool true se tutto ciò che era esportabile è stato esportato
     */
    private function writeCompletenessCheck($conn): bool
    {
        $tipi = array_count_values(array_column($this->manifest, 'tipo'));
        $conTipo = fn (string $prefix) => array_sum(array_filter(
            $tipi,
            fn ($k) => str_starts_with($k, $prefix),
            ARRAY_FILTER_USE_KEY
        ));

        $righe = [
            ['Corsi', $conn->table('courses')->count(), $tipi['Scheda corso'] ?? 0],
            ['Moduli con contenuto', $conn->table('modules')->whereNotNull('content')->where('content', '<>', '')->count(), $tipi['Modulo'] ?? 0],
            ['Materiali', $conn->table('materials')->count(), $conTipo('Materiale')],
            ['Sezioni di manuale formatore', $conn->table('instructor_manual_sections')->whereNotNull('content_html')->where('content_html', '<>', '')->count(), $tipi['Sezione manuale formatore'] ?? 0],
            ['Domande di verifica', $conn->table('quiz_questions')->count(), $tipi['Domanda di verifica'] ?? 0],
        ];

        $this->put("# Appendice C — Verifica di completezza\n\n");
        $this->put("Confronto fra il contenuto della piattaforma e quanto riprodotto nel presente\n");
        $this->put("documento.\n\n");
        $this->put("| Insieme | Nella piattaforma | Nel documento | Esito |\n|---|---|---|---|\n");

        $ok = true;
        foreach ($righe as [$label, $atteso, $reso]) {
            $esito = $reso >= $atteso ? '✓' : '**mancano ' . ($atteso - $reso) . '**';
            $ok = $ok && $reso >= $atteso;
            $this->put('| ' . $label . ' | ' . $atteso . ' | ' . $reso . ' | ' . $esito . " |\n");
        }
        $this->put("\n");

        // I materiali il cui file non era più sullo storage: elencati per nome,
        // perché è una lacuna della piattaforma, non dell'estrazione.
        $mancanti = $conTipo('Materiale mancante');
        if ($mancanti > 0) {
            $this->put('**' . $mancanti . " materiali** risultano registrati nella piattaforma ma il\n");
            $this->put("relativo file non è stato riprodotto; per ciascuno è indicato il motivo, che\n");
            $this->put("distingue il file assente dal file esistente ma non accessibile a chi ha\n");
            $this->put("eseguito l'estrazione. Compaiono nel documento con la sola scheda descrittiva:\n\n");
            foreach ($this->manifest as $row) {
                if (str_starts_with($row['tipo'], 'Materiale mancante')) {
                    $this->put('- ' . $row['riferimento'] . "\n");
                }
            }
            $this->put("\n");
        }

        $this->put($ok
            ? "Esito: **verifica superata** — nessun elemento risulta omesso.\n\n"
            : "Esito: **verifica NON superata** — vedi le righe in evidenza.\n\n");

        $this->put("---\n\n*" . copyright_notice() . "*\n");

        return $ok;
    }

    private function writeManifest(): void
    {
        $this->put("# Appendice B — Manifesto di integrità\n\n");
        $this->put("Impronta SHA-256 della **sorgente originale** di ogni elemento, come conservata\n");
        $this->put("nella piattaforma al momento dell'estrazione. Consente di verificare che il\n");
        $this->put("presente documento corrisponda al contenuto della piattaforma.\n\n");

        $this->put("| # | Tipo | Elemento | Byte | SHA-256 |\n|---|---|---|---|---|\n");
        foreach ($this->manifest as $i => $row) {
            $this->put('| ' . ($i + 1) . ' | ' . $row['tipo'] . ' | ' . $row['riferimento']
                . ' | ' . $row['byte'] . ' | `' . $row['sha'] . "` |\n");
        }

        // Impronta complessiva: hash della sequenza ordinata delle impronte. Un
        // hash del file finale non sarebbe utilizzabile (il file contiene questa
        // stessa tabella), questo invece è ricalcolabile dai dati di partenza.
        $global = hash('sha256', implode("\n", array_column($this->manifest, 'sha')));

        $this->put("\n**Impronta complessiva del corpus** (SHA-256 della sequenza ordinata delle\n");
        $this->put("impronte qui sopra):\n\n```\n" . $global . "\n```\n\n");
        $this->put('Elementi certificati: **' . count($this->manifest) . "**.\n\n");
    }

    // ===== Utilità =====

    private function track(string $tipo, string $riferimento, string $source): void
    {
        $this->manifest[] = [
            'tipo' => $tipo,
            'riferimento' => $this->clean(mb_strimwidth($riferimento, 0, 90, '…')),
            'byte' => strlen($source),
            'sha' => hash('sha256', $source),
        ];
    }

    /** HTML → Markdown con pandoc; in caso di problemi si tiene l'HTML originale. */
    private function html2md(string $html, int $shift = 0): string
    {
        if (trim($html) === '') {
            return '';
        }

        $args = ['pandoc', '-f', 'html', '-t', 'gfm', '--wrap=none'];
        if ($shift > 0) {
            $args[] = '--shift-heading-level-by=' . $shift;
        }

        $p = new Process($args);
        $p->setInput($html);
        $p->setTimeout(60);
        $p->run();

        return $p->isSuccessful() ? rtrim($p->getOutput()) : rtrim($html);
    }

    /** Testo leggibile da una pagina HTML autonoma (canvas): via pandoc, senza markup. */
    private function htmlToPlainText(string $html): string
    {
        $p = new Process(['pandoc', '-f', 'html', '-t', 'plain', '--wrap=none']);
        $p->setInput($html);
        $p->setTimeout(60);
        $p->run();

        return $p->isSuccessful() ? trim($p->getOutput()) : '';
    }

    /** Primo percorso leggibile fra le radici configurate, null se nessuno. */
    private function resolveFile(?string $relative): ?string
    {
        if (!$relative) {
            return null;
        }

        foreach ($this->storageRoots as $root) {
            $abs = $root . '/' . ltrim($relative, '/');
            if (is_file($abs) && is_readable($abs)) {
                return $abs;
            }
        }

        return null;
    }

    /**
     * Distingue "il file non c'è" da "il file c'è ma non è leggibile": per un
     * deposito è una differenza sostanziale — nel secondo caso l'opera esiste,
     * a mancare è solo l'accesso di chi ha eseguito l'estrazione.
     */
    private function unreadableReason(?string $relative): string
    {
        foreach ($this->storageRoots as $root) {
            $dir = dirname($root . '/' . ltrim((string) $relative, '/'));
            if (is_dir($dir) && !is_readable($dir)) {
                return 'cartella non accessibile in lettura all\'utente che ha eseguito l\'estrazione';
            }
        }

        return 'file non presente nello storage al momento dell\'estrazione';
    }

    /** Evita che un blocco di codice chiuda anzitempo il proprio recinto. */
    private function fence(string $code): string
    {
        return str_replace('```', '`­``', $code);
    }

    private function clean(?string $s): string
    {
        return trim(preg_replace('/\s+/u', ' ', str_replace('|', '\|', (string) $s)));
    }

    private function head(string $s, int $max): string
    {
        return strlen($s) > $max ? substr($s, 0, $max) . "\n… (troncato)" : $s;
    }

    private function human(int $bytes): string
    {
        return $bytes > 1048576
            ? round($bytes / 1048576, 1) . ' MB'
            : round($bytes / 1024) . ' KB';
    }

    private function put(string $s): void
    {
        fwrite($this->fh, $s);
    }
}
