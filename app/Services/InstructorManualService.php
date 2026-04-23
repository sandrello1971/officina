<?php

namespace App\Services;

use App\Models\Course;
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
        protected InstructorManualSplitterService $splitter
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
