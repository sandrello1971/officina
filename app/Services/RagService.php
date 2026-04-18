<?php

namespace App\Services;

use App\Models\DocumentRag;
use App\Models\Module;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RagService
{
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

    public function search(string $query, ?string $courseId = null, int $limit = 5)
    {
        $terms = array_filter(array_map('trim', preg_split('/\s+/', $query)), fn($t) => mb_strlen($t) >= 3);
        if (empty($terms)) $terms = [$query];

        $q = DocumentRag::query();
        if ($courseId) $q->where('course_id', $courseId);

        $q->where(function ($w) use ($terms) {
            foreach ($terms as $term) {
                $w->orWhere('content', 'ILIKE', '%' . $term . '%')
                  ->orWhere('title', 'ILIKE', '%' . $term . '%');
            }
        });

        return $q->limit($limit)->get();
    }

    public function searchVideos(string $query, ?string $courseId = null, int $limit = 3): array
    {
        try {
            $modulesQuery = Module::whereNotNull('video_ai_id');
            if ($courseId) {
                $modulesQuery->where('course_id', $courseId);
            }
            $modules = $modulesQuery->with('course')->get();

            if ($modules->isEmpty()) return [];

            $videoIds = $modules->pluck('video_ai_id')->toArray();

            $response = Http::timeout(15)
                ->post(config('services.videoai.url') . '/api/search', [
                    'question' => $query,
                    'video_ids' => $videoIds,
                ]);

            if ($response->failed()) return [];

            $results = $response->json();

            $formatted = [];
            foreach (array_slice($results ?? [], 0, $limit) as $result) {
                $module = $modules->firstWhere('video_ai_id', $result['video_id']);
                foreach (array_slice($result['matches'] ?? [], 0, 2) as $match) {
                    $formatted[] = [
                        'content' => $match['text'],
                        'title' => '🎬 Video: ' . ($result['title'] ?? $module?->title ?? 'Video'),
                        'type' => 'video',
                        'timestamp' => $match['timestamp_str'] ?? null,
                        'video_ai_id' => $result['video_id'],
                        'module_id' => $module?->id,
                        'course_slug' => $module?->course?->slug,
                    ];
                }
            }

            return $formatted;
        } catch (\Exception $e) {
            Log::error('VideoAI search error: ' . $e->getMessage());
            return [];
        }
    }

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
