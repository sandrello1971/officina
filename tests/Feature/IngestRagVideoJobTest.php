<?php

namespace Tests\Feature;

use App\Jobs\IngestRagVideoJob;
use App\Models\Course;
use App\Models\DocumentRag;
use App\Services\VideoAIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Motore generazione corsi da KB — un video caricato come fonte va trascritto
 * (async, VideoAIService::transcribeAudio fa polling bloccante: mai in una
 * request HTTP) e indicizzato come DocumentRag, stesso trattamento dei
 * documenti testuali caricati da RagController.
 */
class IngestRagVideoJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_trascrive_e_indicizza_il_video_come_document_rag(): void
    {
        Storage::fake('public');
        $course = Course::create(['name' => 'Corso video', 'slug' => 'corso-' . Str::lower(Str::random(8)), 'is_active' => true]);

        Storage::disk('public')->put('rag-documents/lezione.mp4', 'contenuto video finto');

        $this->app->bind(VideoAIService::class, fn () => new class extends VideoAIService {
            public function transcribeAudio(string $filePath, string $filename): array
            {
                return ['transcript' => 'Trascrizione automatica della lezione video.'];
            }
        });

        IngestRagVideoJob::dispatchSync('rag-documents/lezione.mp4', 'Lezione video', $course->id);

        $doc = DocumentRag::where('course_id', $course->id)->where('title', 'Lezione video')->first();
        $this->assertNotNull($doc);
        $this->assertStringContainsString('Trascrizione automatica', $doc->content);
    }

    public function test_trascrizione_fallita_non_crea_documenti(): void
    {
        Storage::fake('public');
        $course = Course::create(['name' => 'Corso video 2', 'slug' => 'corso-' . Str::lower(Str::random(8)), 'is_active' => true]);
        Storage::disk('public')->put('rag-documents/lezione2.mp4', 'contenuto video finto');

        $this->app->bind(VideoAIService::class, fn () => new class extends VideoAIService {
            public function transcribeAudio(string $filePath, string $filename): array
            {
                throw new \RuntimeException('VideoAI down');
            }
        });

        IngestRagVideoJob::dispatchSync('rag-documents/lezione2.mp4', 'Lezione 2', $course->id);

        $this->assertSame(0, DocumentRag::where('course_id', $course->id)->count());
    }
}
