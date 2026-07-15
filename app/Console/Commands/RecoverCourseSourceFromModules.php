<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\CourseSource;
use App\Services\CourseSourceExtractor;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Recupero del sorgente strutturato (course_sources) dal CONTENUTO DEI MODULI, per i
 * corsi async che non hanno un manuale .docx (es. solo manuale discente → i moduli).
 *
 * Gemello di course:recover-source ma senza .docx: concatena il contenuto (HTML) dei
 * moduli ordinati e lo estrae con CourseSourceExtractor::extractFromHtml → stessi
 * blocchi {id,text,type} del docx, così il Freshness Agent funziona identico.
 *
 * NON tocca courses/modules: scrive SOLO una riga in course_sources.
 */
class RecoverCourseSourceFromModules extends Command
{
    protected $signature = 'course:recover-source-from-modules
        {course_id : ID INTERNO del corso (uuid PK) — MAI nome o slug}
        {--source-version=1.0 : Versione del sorgente strutturato (stringa, es. "1.0")}';

    protected $description = 'Ricostruisce course_sources dal contenuto dei moduli (corsi async senza manuale .docx).';

    public function handle(CourseSourceExtractor $extractor): int
    {
        $courseId = (string) $this->argument('course_id');
        $version = (string) $this->option('source-version');

        if (!Str::isUuid($courseId)) {
            $this->error("course_id non valido: \"{$courseId}\" non è un uuid.");
            $this->line('Passa l\'ID interno del corso (uuid PK), non il nome né lo slug.');
            return self::FAILURE;
        }

        $course = Course::find($courseId);
        if (!$course) {
            $this->error("course_id interno non trovato: {$courseId}");
            return self::FAILURE;
        }

        // Immutabilità: niente sovrascrittura di una versione esistente.
        if (CourseSource::where('course_id', $course->id)->where('version', $version)->exists()) {
            $this->error("Esiste già un sorgente v{$version} per il corso {$course->id}. Usa --source-version con un valore diverso.");
            return self::FAILURE;
        }

        // Contenuto dei moduli ordinati. Ogni modulo porta già i propri heading (h1/h2…).
        $modules = $course->modules()
            ->orderBy('sort_order')
            ->get(['id', 'sort_order', 'title', 'content']);

        $html = $modules
            ->map(fn ($m) => trim((string) $m->content))
            ->filter()
            ->implode("\n\n");

        if (trim($html) === '') {
            $this->error('I moduli del corso non hanno contenuto: niente da estrarre.');
            return self::FAILURE;
        }

        // File temporaneo .html per pandoc.
        $tmp = tempnam(sys_get_temp_dir(), 'coursesrc_') . '.html';
        file_put_contents($tmp, $html);

        try {
            $result = $extractor->extractFromHtml($tmp);
        } catch (\Throwable $e) {
            @unlink($tmp);
            $this->error('Estrazione fallita: ' . $e->getMessage());
            return self::FAILURE;
        }
        @unlink($tmp);

        $blocks = $result['blocks'];
        foreach ($result['warnings'] as $w) {
            $this->warn($w);
        }
        if (empty($blocks)) {
            $this->error('Nessun blocco estratto dal contenuto dei moduli: niente da salvare.');
            return self::FAILURE;
        }

        $source = CourseSource::create([
            'course_id' => $course->id,
            'version'   => $version,
            'blocks'    => $blocks,
        ]);

        $this->info("Sorgente creato dai moduli: course_sources {$source->id}");
        $this->line("  corso:    \"{$course->name}\" (id {$course->id})");
        $this->line("  versione: {$version}");
        $this->line('  moduli:   ' . $modules->count() . ' · blocchi estratti: ' . count($blocks));

        return self::SUCCESS;
    }
}
