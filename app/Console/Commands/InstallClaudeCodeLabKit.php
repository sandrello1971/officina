<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Material;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/**
 * Installa sul corso "Claude Code — Supporto AI al lavoro sul codice" il kit
 * dei laboratori versionato in resources/course-kits/: canvas corretti
 * (Lab 1, Lab 2, Scheda di analisi bug), repository di esercitazione
 * «Magazzino» scaricabile e chiave del Lab 2 riservata al formatore.
 *
 * Idempotente: i canvas esistenti vengono salvati in .bak-<data> prima di
 * essere sovrascritti; i materiali nuovi si riconoscono dal file_path.
 */
class InstallClaudeCodeLabKit extends Command
{
    protected $signature = 'course:install-claude-code-lab-kit {--dry-run : Mostra cosa farebbe senza scrivere}';

    protected $description = 'Installa il kit laboratori (canvas corretti, repository Magazzino, chiave formatore) sul corso Claude Code';

    private const SLUG = 'claude-code-supporto-ai-al-lavoro-sul-codice';

    private const CANVASES = [
        'canvas-analisi-bug.html',
        'lab-1-configurazione-e-comprensione.html',
        'lab-2-analisi-e-debug.html',
    ];

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $kit = resource_path('course-kits/' . self::SLUG);
        $disk = Storage::disk('local');
        $dir = 'materials/' . self::SLUG;

        $course = Course::where('slug', self::SLUG)->first();
        if (!$course) {
            $this->error('Corso ' . self::SLUG . ' non trovato.');
            return self::FAILURE;
        }

        // 1) canvas corretti, sopra ai file esistenti
        $moduleOf = [];
        foreach (self::CANVASES as $file) {
            $material = Material::where('course_id', $course->id)->where('file_path', "$dir/$file")->first();
            if (!$material) {
                $this->error("Materiale con file $dir/$file non trovato: kit non applicabile.");
                return self::FAILURE;
            }
            $moduleOf[$file] = $material->module_id;

            $new = file_get_contents("$kit/canvas/$file");
            if ($disk->exists($material->file_path) && $disk->get($material->file_path) === $new) {
                $this->line("= $file già aggiornato");
                continue;
            }
            $this->info("↻ $file" . ($dry ? ' (dry-run)' : ''));
            if (!$dry) {
                if ($disk->exists($material->file_path)) {
                    $disk->copy($material->file_path, $material->file_path . '.bak-' . now()->format('Ymd-His'));
                }
                $disk->put($material->file_path, $new);
                $material->update(['file_size' => strlen($new)]);
            }
        }

        // 2) repository di esercitazione, nel modulo del Lab 2 e in quello del Lab 1
        $zipPath = "$dir/magazzino-esercitazione.zip";
        if (!$dry) {
            $this->buildZip("$kit/magazzino-esercitazione", $disk->path($zipPath));
        }
        $this->info('📦 ' . $zipPath . ($dry ? ' (dry-run)' : ''));

        $repoMaterials = [
            ['module' => $moduleOf['lab-2-analisi-e-debug.html'], 'sort' => 2],
            ['module' => $moduleOf['lab-1-configurazione-e-comprensione.html'], 'sort' => 2],
        ];
        foreach ($repoMaterials as $spec) {
            $this->upsertMaterial($dry, [
                'course_id' => $course->id,
                'module_id' => $spec['module'],
                'file_path' => $zipPath,
            ], [
                'title' => 'Repository di esercitazione «Magazzino»',
                'description' => 'Codice sconosciuto e segnalazioni di bug per i laboratori. Python 3.10+, nessuna dipendenza.',
                'file_type' => 'zip',
                'file_size' => $dry || !$disk->exists($zipPath) ? null : $disk->size($zipPath),
                'sort_order' => $spec['sort'],
                'is_downloadable' => true,
                'is_instructor_only' => false,
            ]);
        }

        // 3) chiave del Lab 2, riservata al formatore
        $keyPath = "$dir/chiave-formatore-lab-2.html";
        $key = file_get_contents("$kit/chiave-formatore-lab-2.html");
        if (!$dry) {
            $disk->put($keyPath, $key);
        }
        $this->upsertMaterial($dry, [
            'course_id' => $course->id,
            'module_id' => null,
            'file_path' => $keyPath,
        ], [
            'title' => 'Laboratorio 2 — Chiave per il formatore',
            'description' => 'Soluzioni del repository «Magazzino»: cause radice, correzioni trappola, test che isolano i bug.',
            'file_type' => 'html',
            'file_size' => strlen($key),
            'sort_order' => 10,
            'is_downloadable' => false,
            'is_instructor_only' => true,
        ]);

        if (!$dry) {
            foreach ([$zipPath, $keyPath] as $p) {
                @chmod($disk->path($p), 0664);
            }
        }

        $this->info($dry ? 'Dry-run completato: nessuna modifica.' : 'Kit installato.');
        return self::SUCCESS;
    }

    private function upsertMaterial(bool $dry, array $match, array $values): void
    {
        $existing = Material::where($match)->first();
        $label = $values['title'] . ($match['module_id'] ? " (modulo {$match['module_id']})" : ' (corso)');
        $this->line(($existing ? '↻ ' : '+ ') . $label . ($dry ? ' (dry-run)' : ''));
        if ($dry) {
            return;
        }
        $existing ? $existing->update($values) : Material::create($match + $values);
    }

    private function buildZip(string $sourceDir, string $target): void
    {
        $tmp = $target . '.tmp';
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("Impossibile creare $tmp");
        }

        $root = 'magazzino-esercitazione';
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS));
        $files = [];
        foreach ($it as $file) {
            $rel = substr($file->getPathname(), strlen($sourceDir) + 1);
            if (str_contains($rel, '__pycache__') || str_ends_with($rel, '.pyc')) {
                continue;
            }
            $files[$rel] = $file->getPathname();
        }
        ksort($files);
        foreach ($files as $rel => $abs) {
            $zip->addFile($abs, "$root/$rel");
        }
        $zip->close();
        rename($tmp, $target);
    }
}
