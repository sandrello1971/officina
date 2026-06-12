<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseChangelog;
use App\Models\CourseSource;
use App\Models\DocumentRag;
use App\Models\Material;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class InstructorManualService
{
    public function __construct(
        protected RagService $rag,
        protected InstructorManualSplitterService $splitter,
        protected CourseSourceExtractor $sourceExtractor
    ) {}

    public function import(
        string $sourcePath,
        Course $course,
        string $title,
        ?string $description = null,
        ?Material $existing = null
    ): Material {
        if (!file_exists($sourcePath)) {
            throw new \InvalidArgumentException("File non trovato: {$sourcePath}");
        }

        $html = $this->convertDocxToHtml($sourcePath);
        if ($html === null) {
            throw new \RuntimeException('Conversione pandoc fallita');
        }

        $ext = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'docx';
        $filename = Str::slug($title) . '-' . time() . '.' . $ext;
        $storedPath = "instructor-manuals/{$course->slug}/{$filename}";

        Storage::disk('local')->put($storedPath, file_get_contents($sourcePath));
        $fileSize = filesize($sourcePath);

        $oldPath = $existing?->file_path;

        $data = [
            'course_id'           => $course->id,
            'module_id'           => null,
            'title'               => $title,
            'description'         => $description ?? "Manuale riservato ai docenti del corso {$course->name}",
            'file_path'           => $storedPath,
            'file_type'           => $ext,
            'file_size'           => $fileSize,
            'content_html'        => $html,
            'sort_order'          => $existing->sort_order ?? 0,
            'is_downloadable'     => true,
            'is_instructor_only'  => true,
        ];

        if ($existing) {
            $existing->update($data);
            $material = $existing->fresh();
        } else {
            $material = Material::create($data);
        }

        if ($oldPath && $oldPath !== $storedPath && Storage::disk('local')->exists($oldPath)) {
            Storage::disk('local')->delete($oldPath);
        }

        $this->reindexInRag($material);
        $this->splitter->split($material);

        // F-a — il corso diventa "freshness-ready": dallo STESSO docx appena persistito
        // si genera il sorgente strutturato (course_sources), accanto alle sezioni del
        // formatore. Additivo e non bloccante: vedi syncStructuredSource().
        $this->syncStructuredSource($course, Storage::disk('local')->path($storedPath));

        return $material;
    }

    public function regenerateHtml(Material $material): Material
    {
        if (!$material->file_path || !Storage::disk('local')->exists($material->file_path)) {
            throw new \RuntimeException('File .docx non presente su disco: ' . $material->file_path);
        }

        $absolutePath = Storage::disk('local')->path($material->file_path);
        $html = $this->convertDocxToHtml($absolutePath);
        if ($html === null) {
            throw new \RuntimeException('Conversione pandoc fallita');
        }

        $material->update(['content_html' => $html]);
        $material = $material->fresh();

        $this->reindexInRag($material);
        $this->splitter->split($material);

        // F-a — ri-genera anche il sorgente strutturato dallo stesso .docx esistente.
        $this->syncStructuredSource($material->course, $absolutePath);

        return $material;
    }

    public function delete(Material $material): void
    {
        DocumentRag::where('course_id', $material->course_id)
            ->where('is_instructor_only', true)
            ->where('title', $material->title)
            ->delete();

        if ($material->file_path && Storage::disk('local')->exists($material->file_path)) {
            Storage::disk('local')->delete($material->file_path);
        }
        $material->delete();
    }

    private function reindexInRag(Material $material): void
    {
        if (!$material->is_instructor_only || empty($material->content_html)) {
            return;
        }

        DocumentRag::where('course_id', $material->course_id)
            ->where('is_instructor_only', true)
            ->where('title', $material->title)
            ->delete();

        $plainText = html_entity_decode(
            strip_tags($material->content_html),
            ENT_QUOTES | ENT_HTML5,
            'UTF-8'
        );
        $plainText = preg_replace('/\s+/', ' ', $plainText);
        $plainText = trim($plainText);

        if (mb_strlen($plainText) < 200) return;

        $this->rag->indexDocument(
            $plainText,
            $material->title,
            $material->course_id,
            null,
            $material->file_path,
            true
        );
    }

    public function uploadAndImport(
        UploadedFile $file,
        Course $course,
        string $title,
        ?string $description = null,
        ?Material $existing = null
    ): Material {
        $ext = strtolower($file->getClientOriginalExtension());
        if (!in_array($ext, ['docx', 'doc'])) {
            throw new \InvalidArgumentException('Solo file .docx o .doc sono supportati');
        }

        $tempPath = $file->getRealPath();

        return $this->import($tempPath, $course, $title, $description, $existing);
    }

    /**
     * F-a — Genera il sorgente strutturato (course_sources) dal .docx del manuale formatore.
     *
     * Append-only e non distruttivo:
     *  - corso senza course_sources         → crea v1.0;
     *  - corso PRISTINO (con sorgente, ma   → bump MAGGIORE (es. "1.0"→"2.0", "2.2"→"3.0"):
     *    senza storia di apply dell'agente)    nuova riga che diventa corrente, le vecchie restano.
     *  - corso CON storia di apply          → NON tocca nulla in F-a: è il caso F-b (richiede
     *    (course_changelog kind=apply,         conferma esplicita + gestione proposte orfane).
     *    content_source=instructor)            Qui logga e rinvia.
     *
     * 0 blocchi (heading non riconosciuti) → non genera il sorgente, segnala soltanto.
     * Additività assoluta: qualsiasi errore è catturato e loggato — l'import del manuale
     * (Material + sezioni) NON deve mai fallire per colpa dell'estrazione.
     */
    private function syncStructuredSource(Course $course, string $docxAbsolutePath): void
    {
        try {
            // Gate F-a: mai su un corso con aggiornamenti dell'agente già applicati.
            $hasApplyHistory = CourseChangelog::where('course_id', $course->id)
                ->where('kind', 'apply')
                ->where('content_source', 'instructor')
                ->exists();
            if ($hasApplyHistory) {
                Log::info('[freshness-ready] corso con aggiornamenti agente, estrazione rinviata a conferma (F-b)', [
                    'course_id' => $course->id,
                ]);
                return;
            }

            $result = $this->sourceExtractor->extractFromDocx($docxAbsolutePath);
            $blocks = $result['blocks'] ?? [];
            if (empty($blocks)) {
                Log::warning('[freshness-ready] sorgente strutturato non generato (0 blocchi estratti)', [
                    'course_id' => $course->id,
                ]);
                return;
            }

            // Versione corrente = ultima riga per created_at (tie-break id), come il resto del codice.
            $current = CourseSource::where('course_id', $course->id)
                ->orderByDesc('created_at')->orderByDesc('id')->first();
            $version = $current === null ? '1.0' : $this->nextMajorVersion($current->version);

            CourseSource::create([
                'course_id' => $course->id,
                'version' => $version,
                'blocks' => $blocks,
            ]);

            Log::info('[freshness-ready] course_sources generato dall\'import del manuale', [
                'course_id' => $course->id, 'version' => $version, 'blocks' => count($blocks),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[freshness-ready] estrazione course_sources fallita (non bloccante per l\'import)', [
                'course_id' => $course->id, 'error' => $e->getMessage(),
            ]);
        }
    }

    /** Bump MAGGIORE come stringa: "1.0"→"2.0", "2.2"→"3.0", "2"→"3.0". */
    private function nextMajorVersion(string $v): string
    {
        if (preg_match('/^(\d+)(?:\.\d+)?$/', $v, $m)) {
            return ((int) $m[1] + 1) . '.0';
        }
        throw new \RuntimeException("Versione non incrementabile in modo deterministico: {$v}");
    }

    private function convertDocxToHtml(string $docxPath): ?string
    {
        $process = new Process([
            'pandoc',
            $docxPath,
            '--from=docx',
            '--to=html5',
            '--wrap=none',
        ]);
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            Log::error('pandoc failed', [
                'input' => $docxPath,
                'error' => $process->getErrorOutput(),
            ]);
            return null;
        }

        return $process->getOutput();
    }
}
