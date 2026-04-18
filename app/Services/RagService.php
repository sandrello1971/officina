<?php

namespace App\Services;

use App\Models\DocumentRag;

class RagService
{
    /**
     * Indicizza un documento nel RAG.
     * Stub: salva il documento per chunk. L'embedding verra generato quando pgvector e attivo.
     */
    public function indexDocument(string $text, string $title, string $courseId, ?string $moduleId, ?string $filePath): void
    {
        $chunks = $this->chunkText($text, 1000, 200);

        foreach ($chunks as $index => $chunk) {
            DocumentRag::create([
                'title' => $title,
                'content' => $chunk,
                'course_id' => $courseId,
                'module_id' => $moduleId,
                'file_path' => $filePath,
                'chunk_index' => $index,
                'metadata' => [
                    'chunks_total' => count($chunks),
                    'source_title' => $title,
                ],
            ]);
        }
    }

    /**
     * Suddivide il testo in chunk con overlap.
     */
    private function chunkText(string $text, int $chunkSize = 1000, int $overlap = 200): array
    {
        $text = trim($text);
        if (strlen($text) <= $chunkSize) {
            return [$text];
        }

        $chunks = [];
        $start = 0;
        $length = strlen($text);

        while ($start < $length) {
            $chunk = substr($text, $start, $chunkSize);
            $chunks[] = $chunk;
            $start += $chunkSize - $overlap;
        }

        return $chunks;
    }
}
