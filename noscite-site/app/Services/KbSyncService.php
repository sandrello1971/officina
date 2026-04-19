<?php

namespace App\Services;

use App\Models\KbDocument;
use Illuminate\Support\Facades\Log;

class KbSyncService
{
    private string $vaultPath;
    private string $metadataPath;
    private string $archivePath;

    public function __construct()
    {
        $this->vaultPath = config('kb.vault_path', '/var/www/noscite-kb');
        $this->metadataPath = $this->vaultPath . '/_metadata';
        $this->archivePath = $this->vaultPath . '/_archive';
    }

    public function sync(): array
    {
        $stats = ['updated' => 0, 'created' => 0, 'errors' => 0];

        if (!is_dir($this->metadataPath)) {
            return $stats;
        }

        foreach (glob($this->metadataPath . '/*.md') as $mdFile) {
            $stem = pathinfo($mdFile, PATHINFO_FILENAME);
            if (in_array($stem, ['_INDEX', 'DASHBOARD', 'dashboard'])) continue;

            try {
                $data = $this->parseFrontmatter($mdFile);
                if (empty($data)) continue;

                $doc = KbDocument::updateOrCreate(
                    ['file_stem' => $stem],
                    array_merge($data, ['last_synced_at' => now()])
                );

                $stats[$doc->wasRecentlyCreated ? 'created' : 'updated']++;
            } catch (\Exception $e) {
                Log::error("KB sync error for {$stem}: " . $e->getMessage());
                $stats['errors']++;
            }
        }

        return $stats;
    }

    private function parseFrontmatter(string $path): array
    {
        $content = file_get_contents($path);
        if (!str_starts_with($content, '---')) return [];

        $end = strpos($content, '---', 3);
        if ($end === false) return [];

        $fm = substr($content, 3, $end - 3);
        $data = [];

        foreach (explode("\n", $fm) as $line) {
            $line = trim($line);
            if (!$line || str_starts_with($line, '#')) continue;
            if (!str_contains($line, ':')) continue;

            [$key, $val] = explode(':', $line, 2);
            $key = trim($key);
            $val = trim(trim($val, '"\''));

            if ($val) $data[$key] = $val;
        }

        foreach (['tags', 'argomenti'] as $field) {
            $data[$field] = $this->parseYamlList($fm, $field);
        }

        return [
            'title' => $data['title'] ?? null,
            'tipo_documento' => $data['tipo_documento'] ?? null,
            'lingua' => $data['lingua'] ?? 'it',
            'sommario' => $data['sommario'] ?? null,
            'tags' => $data['tags'] ?? [],
            'argomenti' => $data['argomenti'] ?? [],
            'file_originale' => $data['file_originale'] ?? null,
            'data_catalogazione' => $data['data_catalogazione'] ?? null,
            'file_path' => $this->findOriginalFile($data['file_originale'] ?? ''),
            'file_type' => $this->getFileType($data['file_originale'] ?? ''),
        ];
    }

    private function parseYamlList(string $fm, string $field): array
    {
        $items = [];
        $inField = false;

        foreach (explode("\n", $fm) as $line) {
            $stripped = trim($line);
            if ($stripped === $field . ':') {
                $inField = true;
                continue;
            }
            if ($inField) {
                if (str_starts_with($stripped, '- ')) {
                    $items[] = trim(substr($stripped, 2), '"\'');
                } elseif ($stripped && !str_starts_with($stripped, '-')) {
                    break;
                }
            }
        }

        return $items;
    }

    private function findOriginalFile(string $fileOriginal): ?string
    {
        if (!$fileOriginal) return null;
        preg_match('/\[\[([^\]]+)\]\]/', $fileOriginal, $matches);
        $relativePath = $matches[1] ?? $fileOriginal;
        $fullPath = $this->vaultPath . '/' . $relativePath;
        return file_exists($fullPath) ? $fullPath : null;
    }

    private function getFileType(string $fileOriginal): ?string
    {
        preg_match('/\.([a-zA-Z0-9]+)[\]|]/', $fileOriginal, $matches);
        return !empty($matches[1]) ? strtolower($matches[1]) : null;
    }

    public function getVaultStats(): array
    {
        $inboxCount = is_dir($this->vaultPath . '/_inbox')
            ? count(glob($this->vaultPath . '/_inbox/*'))
            : 0;
        $archiveCount = is_dir($this->archivePath)
            ? count(glob($this->archivePath . '/*'))
            : 0;
        $metadataCount = is_dir($this->metadataPath)
            ? count(array_filter(glob($this->metadataPath . '/*.md'), fn($f) =>
                !in_array(pathinfo($f, PATHINFO_FILENAME), ['_INDEX', 'DASHBOARD'])))
            : 0;

        return [
            'inbox' => $inboxCount,
            'archive' => $archiveCount,
            'metadata' => $metadataCount,
            'last_git_pull' => $this->getLastGitPull(),
        ];
    }

    private function getLastGitPull(): ?string
    {
        $logFile = '/var/log/kb-sync.log';
        if (!file_exists($logFile)) return null;
        $lines = file($logFile);
        return $lines ? trim(end($lines)) : null;
    }
}
