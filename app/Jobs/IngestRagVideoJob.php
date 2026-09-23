<?php

namespace App\Jobs;

use App\Services\RagService;
use App\Services\VideoAIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Trascrive un video caricato come fonte KB e lo indicizza come DocumentRag.
 * VideoAIService::transcribeAudio() fa polling bloccante lato videoai: va
 * SEMPRE chiamato da un job in coda, mai da una request HTTP (stesso motivo
 * per cui TeachingDocumentExtractor::extractAudio lo fa già, lato Schola).
 * Il file caricato resta in storage privato (rag-documents), non cancellato:
 * stesso trattamento dei documenti testuali caricati da RagController.
 */
class IngestRagVideoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;
    public int $tries = 1;

    public function __construct(
        public string $storagePath,
        public string $title,
        public string $courseId,
        public ?string $moduleId = null,
    ) {}

    public function handle(VideoAIService $videoAI, RagService $rag): void
    {
        if (!Storage::disk('public')->exists($this->storagePath)) {
            Log::warning('[officina] IngestRagVideoJob: file mancante', ['path' => $this->storagePath]);
            return;
        }

        $absPath = Storage::disk('public')->path($this->storagePath);

        try {
            $result = $videoAI->transcribeAudio($absPath, basename($this->storagePath));
        } catch (Throwable $e) {
            Log::warning('[officina] trascrizione video KB fallita', ['path' => $this->storagePath, 'error' => $e->getMessage()]);
            return;
        }

        $text = trim((string) ($result['transcript'] ?? ''));
        if ($text === '') {
            Log::warning('[officina] trascrizione video KB vuota', ['path' => $this->storagePath]);
            return;
        }

        $rag->indexDocument($text, $this->title, $this->courseId, $this->moduleId, $this->storagePath);
    }
}
