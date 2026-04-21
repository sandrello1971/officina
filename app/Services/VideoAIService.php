<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class VideoAIService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = config('services.videoai.url');
    }

    public function ingestVideo(string $filePath, string $filename): array
    {
        $response = Http::timeout(300)
            ->attach('file', file_get_contents($filePath), $filename)
            ->post("{$this->baseUrl}/api/videos/ingest");

        if ($response->failed()) {
            throw new \Exception('VideoAI ingest failed: ' . $response->body());
        }

        return $response->json();
    }

    public function getStatus(string $videoId): array
    {
        $response = Http::timeout(10)
            ->get("{$this->baseUrl}/api/videos/{$videoId}/status");

        if ($response->failed()) {
            return ['status' => 'error', 'progress' => 0, 'can_chat' => false];
        }

        return $response->json();
    }

    public function chat(string $videoId, string $question, array $history = []): array
    {
        $response = Http::timeout(60)
            ->post("{$this->baseUrl}/api/videos/{$videoId}/chat", [
                'question' => $question,
                'history' => $history,
            ]);

        if ($response->failed()) {
            throw new \Exception('VideoAI chat failed: ' . $response->body());
        }

        return $response->json();
    }

    public function getTranscript(string $videoId): array
    {
        $response = Http::timeout(30)
            ->get("{$this->baseUrl}/api/videos/{$videoId}/transcript");

        if ($response->failed()) return ['segments' => []];
        return $response->json();
    }

    public function getThumbnailUrl(string $videoId): string
    {
        return "{$this->baseUrl}/api/videos/{$videoId}/thumbnail";
    }

    public function getStreamUrl(string $videoId): string
    {
        return "/learn/video/{$videoId}/stream";
    }

    public function deleteVideo(string $videoId): bool
    {
        $response = Http::timeout(30)
            ->delete("{$this->baseUrl}/api/videos/{$videoId}");
        return $response->successful();
    }

    public function search(string $query, array $videoIds): array
    {
        if (empty($videoIds)) return [];

        $response = Http::timeout(15)
            ->post("{$this->baseUrl}/api/search", [
                'question' => $query,
                'video_ids' => array_values(array_unique($videoIds)),
            ]);

        if ($response->failed()) return [];
        return $response->json() ?? [];
    }
}
